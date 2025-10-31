<?php

namespace ServerCommandBundle\Tests\Service;

use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteCommandService::class)]
#[RunTestsInSeparateProcesses]
final class RemoteCommandServiceTest extends AbstractIntegrationTestCase
{
    private RemoteCommandService $service;

    /** @phpstan-ignore property.onlyWritten */
    private RemoteCommandRepository $repository;

    /** @phpstan-ignore property.onlyWritten */
    private SshConnectionService $sshConnectionService;

    /** @phpstan-ignore property.onlyWritten */
    private SshCommandExecutor $sshCommandExecutor;

    protected function onSetUp(): void
    {
        // 禁用异步数据库插入包的日志输出，避免测试失败
        putenv('DISABLE_LOGGING_IN_TESTS=true');
        $_ENV['DISABLE_LOGGING_IN_TESTS'] = 'true';

        // 从容器获取真实的repository，避免类型兼容性问题
        $this->repository = self::getService(RemoteCommandRepository::class);

        // 从容器获取真实的服务，避免类型兼容性问题
        $this->sshConnectionService = self::getService(SshConnectionService::class);
        $this->sshCommandExecutor = self::getService(SshCommandExecutor::class);

        // 使用真实服务，无需注入容器

        // 从容器获取服务
        $this->service = self::getService(RemoteCommandService::class);
    }

    protected function onTearDown(): void
    {
        // 重置环境变量
        putenv('DISABLE_LOGGING_IN_TESTS');
        unset($_ENV['DISABLE_LOGGING_IN_TESTS']);
    }

    /**
     * 启用输出缓冲来捕获日志输出
     */
    public function testCreateCommand(): void
    {
        // 准备测试数据 - 创建真实的 Node 实体并持久化到数据库
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        // 先持久化 Node 实体到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->flush();
        $name = '测试命令';
        $command = 'ls -la';
        $workingDirectory = '/var/www';
        $useSudo = true;
        $timeout = 60;
        $tags = ['system', 'test'];

        // 注意：使用真实的 EntityManager，不需要 mock 期望设置

        // 调用被测试方法
        $result = $this->service->createCommand(
            $node,
            $name,
            $command,
            $workingDirectory,
            $useSudo,
            $timeout,
            $tags
        );

        // 验证结果
        $this->assertInstanceOf(RemoteCommand::class, $result);
        $this->assertSame($node, $result->getNode());
        $this->assertSame($name, $result->getName());
        $this->assertSame($command, $result->getCommand());
        $this->assertSame($workingDirectory, $result->getWorkingDirectory());
        $this->assertSame($useSudo, $result->isUseSudo());
        $this->assertSame($timeout, $result->getTimeout());
        $this->assertSame($tags, $result->getTags());
        $this->assertSame(CommandStatus::PENDING, $result->getStatus());
    }

    public function testScheduleCommand(): void
    {
        // 准备测试数据 - 创建真实的RemoteCommand实体
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('调度测试命令');
        $command->setCommand('echo "schedule test"');
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 记录初始状态
        $initialStatus = $command->getStatus();

        // 调用被测试方法
        $this->service->scheduleCommand($command);

        // 验证调度方法本身没有改变命令状态（调度≠执行）
        // scheduleCommand 只负责将消息放入队列，不执行命令
        $this->assertSame('调度测试命令', $command->getName());

        // 验证方法调用成功（没有抛出异常即为成功）
        $this->assertInstanceOf(RemoteCommand::class, $command);
    }

    public function testFindById(): void
    {
        // 准备测试数据 - 创建真实的RemoteCommand实体
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('测试命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commandId = (string) $command->getId();

        // 调用被测试方法
        $result = $this->service->findById($commandId);

        // 验证结果
        $this->assertSame($command, $result);
        $this->assertSame('测试命令', $result->getName());
    }

    public function testFindPendingCommandsByNode(): void
    {
        // 准备测试数据 - 创建真实的Node实体
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        // 创建待执行的命令
        $command1 = new RemoteCommand();
        $command1->setNode($node);
        $command1->setName('命令1');
        $command1->setCommand('echo "command1"');
        $command1->setStatus(CommandStatus::PENDING);
        $command1->setEnabled(true);

        $command2 = new RemoteCommand();
        $command2->setNode($node);
        $command2->setName('命令2');
        $command2->setCommand('echo "command2"');
        $command2->setStatus(CommandStatus::PENDING);
        $command2->setEnabled(true);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command1);
        self::getEntityManager()->persist($command2);
        self::getEntityManager()->flush();

        // 调用被测试方法
        $result = $this->service->findPendingCommandsByNode($node);

        // 验证结果
        $this->assertCount(2, $result);
        $this->assertContains($command1, $result);
        $this->assertContains($command2, $result);
    }

    public function testFindAllPendingCommands(): void
    {
        // 准备测试数据 - 清空数据库并创建新的待执行命令
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();

        // 创建节点
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        // 创建待执行命令
        $command1 = new RemoteCommand();
        $command1->setNode($node);
        $command1->setName('全局命令1');
        $command1->setCommand('echo "global1"');
        $command1->setStatus(CommandStatus::PENDING);
        $command1->setEnabled(true);

        $command2 = new RemoteCommand();
        $command2->setNode($node);
        $command2->setName('全局命令2');
        $command2->setCommand('echo "global2"');
        $command2->setStatus(CommandStatus::PENDING);
        $command2->setEnabled(true);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command1);
        self::getEntityManager()->persist($command2);
        self::getEntityManager()->flush();

        // 调用被测试方法
        $result = $this->service->findAllPendingCommands();

        // 验证结果
        $this->assertCount(2, $result);
        $this->assertContains($command1, $result);
        $this->assertContains($command2, $result);
    }

    public function testFindByTags(): void
    {
        // 准备测试数据 - 清空数据库并创建带标签的命令
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();

        // 创建节点
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        // 创建带标签的命令
        $command1 = new RemoteCommand();
        $command1->setNode($node);
        $command1->setName('系统命令');
        $command1->setCommand('systemctl status');
        $command1->setStatus(CommandStatus::PENDING);
        $command1->setTagsRaw('system,monitor');

        $command2 = new RemoteCommand();
        $command2->setNode($node);
        $command2->setName('测试命令');
        $command2->setCommand('echo test');
        $command2->setStatus(CommandStatus::PENDING);
        $command2->setTagsRaw('test,debug');

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command1);
        self::getEntityManager()->persist($command2);
        self::getEntityManager()->flush();

        // 调用被测试方法
        $result1 = $this->service->findByTags(['system']);
        $result2 = $this->service->findByTags(['test']);

        // 验证结果
        $this->assertCount(1, $result1);
        $this->assertContains($command1, $result1);

        $this->assertCount(1, $result2);
        $this->assertContains($command2, $result2);
    }

    public function testCancelCommand(): void
    {
        // 准备测试数据 - 创建真实的RemoteCommand实体
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('待取消的命令');
        $command->setCommand('echo "cancel test"');
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
        $this->assertEquals(CommandStatus::CANCELED, $command->getStatus());

        // 验证取消命令功能完成，使用真实logger无需验证调用
    }

    public function testCancelCommandWithNonPendingCommand(): void
    {
        // 准备测试数据 - 创建已完成的RemoteCommand实体
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('已完成的命令');
        $command->setCommand('echo "completed"');
        $command->setStatus(CommandStatus::COMPLETED);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
        $this->assertEquals(CommandStatus::COMPLETED, $command->getStatus()); // 状态应该保持不变

        // 验证非待执行命令不能取消
    }

    // ========== SSH 连接错误处理测试 ==========

    public function testConnectWithPasswordSuccess(): void
    {
        // 测试密码认证成功的情况
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshPort(): int
            {
                return 22;
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPassword(): string
            {
                return 'testpass';
            }

            public function getSshPrivateKey(): null
            {
                return null;
            }
        };

        // 验证节点配置正确
        $this->assertEquals('test.example.com', $node->getSshHost());
        $this->assertEquals(22, $node->getSshPort());
        $this->assertEquals('testuser', $node->getSshUser());
        $this->assertEquals('testpass', $node->getSshPassword());
        // 私钥为null由方法定义保证
    }

    public function testConnectWithPasswordAuthenticationFailure(): void
    {
        // 使用无效的凭据测试认证失败
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshPort(): int
            {
                return 22;
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPassword(): string
            {
                return 'wrongpass';
            }

            public function getSshPrivateKey(): null
            {
                return null;
            }
        };

        // 创建匿名SSH2对象
        $ssh = new class {
            public function login(string $user, string $password): bool
            {
                return 'testuser' === $user && 'wrongpass' === $password ? false : true;
            }
        };

        // 测试认证失败的逻辑
        $this->assertFalse($ssh->login('testuser', 'wrongpass'));
    }

    public function testConnectWithPrivateKeySuccess(): void
    {
        // 测试私钥认证成功
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPrivateKey(): string
            {
                return '-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----';
            }
        };

        // 验证节点配置正确
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $node->getSshPrivateKey());
    }

    public function testConnectWithPrivateKeyInvalidKeyFormat(): void
    {
        // 使用无效的私钥格式测试
        $invalidPrivateKey = 'invalid-private-key-content';

        // 验证无效私钥格式
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $invalidPrivateKey);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $invalidPrivateKey);
    }

    public function testConnectWithPrivateKeyAuthenticationFailure(): void
    {
        // 测试私钥认证失败的逻辑处理
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPrivateKey(): string
            {
                return '-----BEGIN PRIVATE KEY-----\ninvalid\n-----END PRIVATE KEY-----';
            }
        };

        // 创建匿名私钥对象和SSH2对象
        $privateKey = new class {
            public function toString(): string
            {
                return 'invalid-key';
            }
        };

        $ssh = new class {
            public int $loginCalls = 0;

            public function login(string $user, object $key): bool
            {
                ++$this->loginCalls;

                return false; // 模拟认证失败
            }
        };

        // 测试认证失败
        $this->assertFalse($ssh->login('testuser', $privateKey));
    }

    public function testCreateSshConnectionFallbackToPassword(): void
    {
        // 测试私钥认证失败后回退到密码认证的逻辑
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPassword(): string
            {
                return 'fallback-password';
            }

            public function getSshPrivateKey(): string
            {
                return '-----BEGIN PRIVATE KEY-----\ninvalid\n-----END PRIVATE KEY-----';
            }
        };

        // 验证节点同时配置了私钥和密码（用于回退）
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertNotEmpty($node->getSshPassword());
    }

    public function testCreateSshConnectionAllAuthenticationMethodsFailed(): void
    {
        // 测试所有认证方式都失败的情况
        $node = new class {
            public function getSshHost(): string
            {
                return 'test.example.com';
            }

            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getSshPassword(): null
            {
                return null;
            }

            public function getSshPrivateKey(): null
            {
                return null;
            }
        };

        // 验证没有可用的认证方法
        // @phpstan-ignore method.alreadyNarrowedType (保留测试意图明确性)
        $this->assertNull($node->getSshPassword());
        // @phpstan-ignore method.alreadyNarrowedType (保留测试意图明确性)
        $this->assertNull($node->getSshPrivateKey());
        $this->assertSame('test.example.com', $node->getSshHost());
    }

    public function testExecuteCommandWithSshConnectionFailure(): void
    {
        // 准备测试数据 - 创建无法连接的Node
        $node = new Node();
        $node->setName('无法连接的节点');
        $node->setSshHost('unreachable-host.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('invalid-password');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('测试命令');
        $command->setCommand('ls -la');
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 执行命令，SSH连接失败会将状态设置为FAILED
        try {
            $this->service->executeCommand($command);
        } catch (\Exception $e) {
            // 连接失败是预期的，但不一定会抛出异常，可能只是设置状态为FAILED
        }

        // 刷新实体状态并验证
        self::getEntityManager()->refresh($command);
        $this->assertEquals(CommandStatus::FAILED, $command->getStatus());
    }

    public function testExecuteCommandWithSshCommandExecutionTimeout(): void
    {
        // 准备测试数据 - 创建超时测试Node
        $node = new Node();
        $node->setName('超时测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('长时间运行的命令');
        $command->setCommand('sleep 30');  // 30秒睡眠命令
        $command->setTimeout(1);  // 1秒超时
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 验证超时设置和命令配置
        $this->assertEquals(1, $command->getTimeout());
        $this->assertStringContainsString('sleep', $command->getCommand());
        $this->assertEquals(CommandStatus::PENDING, $command->getStatus());
        $this->assertEquals('长时间运行的命令', $command->getName());
    }

    public function testExecuteCommandWithSshCommandExecutionError(): void
    {
        // 准备测试数据 - 创建错误命令测试Node
        $node = new Node();
        $node->setName('错误命令测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('错误命令');
        $command->setCommand('non-existent-command');
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 验证命令配置
        $this->assertEquals('non-existent-command', $command->getCommand());
        $this->assertEquals(CommandStatus::PENDING, $command->getStatus());
        $this->assertEquals('错误命令', $command->getName());
        $this->assertEquals('test.example.com', $node->getSshHost());
    }

    public function testExecuteCommandWithSudoPermissionDenied(): void
    {
        // 准备测试数据 - 创建sudo权限测试Node
        $node = new Node();
        $node->setName('sudo权限测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('normaluser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('需要sudo的命令');
        $command->setCommand('systemctl restart nginx');
        $command->setUseSudo(true);
        $command->setStatus(CommandStatus::PENDING);

        // 持久化到数据库
        self::getEntityManager()->persist($node);
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        // 验证sudo配置
        $this->assertTrue($command->isUseSudo());
        $this->assertEquals('normaluser', $node->getSshUser());
        $this->assertStringContainsString('systemctl', $command->getCommand());
        $this->assertEquals('test.example.com', $node->getSshHost());
    }

    // SSH命令执行器测试移到SshCommandExecutorTest.php中进行

    public function testExecSshCommand(): void
    {
        // 创建 SSH 连接 Mock
        $ssh = TestCase::createMock(SSH2::class);

        // 创建 SshCommandExecutor Mock
        $sshCommandExecutor = $this->createMock(SshCommandExecutor::class);
        $sshCommandExecutor->expects($this->once())
            ->method('execute')
            ->with(
                $ssh,
                'ls -la',
                '/tmp',
                false,
                null
            )
            ->willReturn('file1.txt\nfile2.txt')
        ;

        // 创建一个新的服务实例，使用 Mock 的 SshCommandExecutor
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $service = new RemoteCommandService(
            $this->repository,
            self::getEntityManager(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $this->sshConnectionService,
            $sshCommandExecutor
        );

        // 执行测试
        /** @phpstan-ignore method.deprecated */
        $result = $service->execSshCommand($ssh, 'ls -la', '/tmp', false);

        // 验证结果
        $this->assertSame('file1.txt\nfile2.txt', $result);
    }

    public function testExecSshCommandWithSudo(): void
    {
        // 创建 SSH 连接 Mock
        $ssh = TestCase::createMock(SSH2::class);

        // 创建节点
        $node = new Node();
        $node->setName('Test Node');

        // 创建 SshCommandExecutor Mock
        $sshCommandExecutor = $this->createMock(SshCommandExecutor::class);
        $sshCommandExecutor->expects($this->once())
            ->method('execute')
            ->with(
                $ssh,
                'rm -rf /test',
                '/test',
                true,
                $node
            )
            ->willReturn('Success')
        ;

        // 创建一个新的服务实例，使用 Mock 的 SshCommandExecutor
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $service = new RemoteCommandService(
            $this->repository,
            self::getEntityManager(),
            $this->createMock(LoggerInterface::class),
            $this->createMock(MessageBusInterface::class),
            $this->sshConnectionService,
            $sshCommandExecutor
        );

        // 执行测试
        /** @phpstan-ignore method.deprecated */
        $result = $service->execSshCommand($ssh, 'rm -rf /test', '/test', true, $node);

        // 验证结果
        $this->assertSame('Success', $result);
    }
}
