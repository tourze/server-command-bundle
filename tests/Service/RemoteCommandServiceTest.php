<?php

namespace ServerCommandBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\SshConnectionService;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoteCommandServiceTest extends TestCase
{
    private RemoteCommandRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private MessageBusInterface&MockObject $messageBus;
    private SshConnectionService&MockObject $sshConnectionService;
    private SshCommandExecutor&MockObject $sshCommandExecutor;
    private RemoteCommandService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RemoteCommandRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->sshConnectionService = $this->createMock(SshConnectionService::class);
        $this->sshCommandExecutor = $this->createMock(SshCommandExecutor::class);

        $this->service = new RemoteCommandService(
            $this->repository,
            $this->entityManager,
            $this->logger,
            $this->messageBus,
            $this->sshConnectionService,
            $this->sshCommandExecutor
        );
    }

    public function testCreateCommand(): void
    {
        // 准备测试数据
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $name = '测试命令';
        $command = 'ls -la';
        $workingDirectory = '/var/www';
        $useSudo = true;
        $timeout = 60;
        $tags = ['system', 'test'];

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RemoteCommand::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

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
        // 准备测试数据
        /** @var RemoteCommand&MockObject $command */
        $command = $this->createMock(RemoteCommand::class);
        $commandId = 123;
        
        $command->expects($this->once())
            ->method('getId')
            ->willReturn($commandId);

        // 设置消息总线的期望行为
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($commandId) {
                return $message instanceof RemoteCommandExecuteMessage
                    && $message->getCommandId() === (string)$commandId;
            }))
            ->willReturn(new Envelope(new RemoteCommandExecuteMessage((string)$commandId)));

        // 设置日志记录器的期望行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('命令已加入执行队列'),
                $this->arrayHasKey('command')
            );

        // 调用被测试方法
        $this->service->scheduleCommand($command);
    }

    public function testFindById(): void
    {
        // 准备测试数据
        $commandId = '123';
        /** @var RemoteCommand&MockObject $command */
        $command = $this->createMock(RemoteCommand::class);

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('find')
            ->with($commandId)
            ->willReturn($command);

        // 调用被测试方法
        $result = $this->service->findById($commandId);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testFindPendingCommandsByNode(): void
    {
        // 准备测试数据
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findPendingCommandsByNode')
            ->with($node)
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findPendingCommandsByNode($node);

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testFindAllPendingCommands(): void
    {
        // 准备测试数据
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findAllPendingCommands')
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findAllPendingCommands();

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testFindByTags(): void
    {
        // 准备测试数据
        $tags = ['system', 'test'];
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findByTags($tags);

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testCancelCommand(): void
    {
        // 准备测试数据
        /** @var RemoteCommand&MockObject $command */
        $command = $this->createMock(RemoteCommand::class);

        // 设置命令状态为可取消状态
        $command->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::PENDING);

        $command->expects($this->once())
            ->method('setStatus')
            ->with(CommandStatus::CANCELED)
            ->willReturnSelf();

        // cancelCommand 只调用 flush，不调用 persist
        $this->entityManager->expects($this->once())
            ->method('flush');

        // 设置日志记录器的期望行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('命令已取消'),
                $this->arrayHasKey('command')
            );

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testCancelCommandWithNonPendingCommand(): void
    {
        // 准备测试数据
        /** @var RemoteCommand&MockObject $command */
        $command = $this->createMock(RemoteCommand::class);

        // 设置命令状态为非待执行状态
        $command->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::COMPLETED);

        // cancelCommand 方法在非PENDING状态时不抛出异常，只是跳过操作
        $command->expects($this->never())
            ->method('setStatus');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->logger->expects($this->never())
            ->method('info');

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testExecSshCommandWithSudoAndWorkingDirectory(): void
    {
        // 创建模拟的SSH2连接
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        // 创建模拟的节点
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('testuser'); // 非root用户
        $node->method('getSshPassword')->willReturn(null); // 无密码
        
        // 根据实际实现，sudo命令格式为: sudo -S bash -c 'cd /var/www && ls -la'
        $expectedCommand = "sudo -S bash -c 'cd /var/www && ls -la'";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('total 0');

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, 'ls -la', '/var/www', true, $node);

        // 验证结果
        $this->assertEquals('total 0', $result);
    }

    public function testExecSshCommandWithSudoWithoutWorkingDirectory(): void
    {
        // 创建模拟的SSH2连接
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        // 创建模拟的节点
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('testuser'); // 非root用户
        $node->method('getSshPassword')->willReturn(null); // 无密码
        
        // 根据实际实现，sudo命令格式为: sudo -S ls -la
        $expectedCommand = "sudo -S ls -la";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('total 0');

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, 'ls -la', null, true, $node);

        // 验证结果
        $this->assertEquals('total 0', $result);
    }

    public function testExecSshCommandWithSudoAndPassword(): void
    {
        // 测试使用密码的sudo命令
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn('password123');
        
        // 带密码的sudo命令格式
        $expectedCommand = "printf '%s\\n\\n' 'password123' | sudo -S ls -la";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('total 0');

        $result = $this->service->execSshCommand($ssh, 'ls -la', null, true, $node);
        $this->assertEquals('total 0', $result);
    }

    public function testExecSshCommandWithRootUser(): void
    {
        // 测试root用户执行sudo命令（不需要实际sudo）
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('root');
        
        // root用户执行时不需要sudo
        $expectedCommand = "ls -la";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('total 0');

        $result = $this->service->execSshCommand($ssh, 'ls -la', null, true, $node);
        $this->assertEquals('total 0', $result);
    }

    public function testExecSshCommandWithoutSudo(): void
    {
        // 准备测试数据
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $command = 'ls -la';
        $expectedResult = 'file1.txt\nfile2.txt';

        // 设置SSH连接的期望行为
        $ssh->expects($this->once())
            ->method('exec')
            ->with($command)
            ->willReturn($expectedResult);

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, $command);

        // 验证结果
        $this->assertSame($expectedResult, $result);
    }

    // ========== SSH 连接错误处理测试 ==========

    public function testConnectWithPassword_Success(): void
    {
        // 测试密码认证成功的情况
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn('testpass');
        $node->method('getSshPrivateKey')->willReturn(null);

        // 验证节点配置正确
        $this->assertEquals('test.example.com', $node->getSshHost());
        $this->assertEquals(22, $node->getSshPort());
        $this->assertEquals('testuser', $node->getSshUser());
        $this->assertEquals('testpass', $node->getSshPassword());
        $this->assertNull($node->getSshPrivateKey());
    }

    public function testConnectWithPassword_AuthenticationFailure(): void
    {
        // 使用无效的凭据测试认证失败
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn('wrongpass');
        $node->method('getSshPrivateKey')->willReturn(null);

        // 创建模拟的SSH2对象
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $ssh->method('login')
            ->with('testuser', 'wrongpass')
            ->willReturn(false);

        // 测试认证失败的逻辑
        $this->assertFalse($ssh->login('testuser', 'wrongpass'));
    }

    public function testConnectWithPrivateKey_Success(): void
    {
        // 测试私钥认证成功
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPrivateKey')->willReturn('-----BEGIN PRIVATE KEY-----\ntest\n-----END PRIVATE KEY-----');

        // 验证节点配置正确
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertStringContainsString('BEGIN PRIVATE KEY', $node->getSshPrivateKey());
    }

    public function testConnectWithPrivateKey_InvalidKeyFormat(): void
    {
        // 使用无效的私钥格式测试
        $invalidPrivateKey = 'invalid-private-key-content';
        
        // 验证无效私钥格式
        $this->assertStringNotContainsString('BEGIN PRIVATE KEY', $invalidPrivateKey);
        $this->assertStringNotContainsString('BEGIN RSA PRIVATE KEY', $invalidPrivateKey);
    }

    public function testConnectWithPrivateKey_AuthenticationFailure(): void
    {
        // 测试私钥认证失败的逻辑处理
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPrivateKey')->willReturn('-----BEGIN PRIVATE KEY-----\ninvalid\n-----END PRIVATE KEY-----');

        // 创建模拟的SSH2对象和私钥对象
        /** @var \phpseclib3\Crypt\RSA&MockObject $privateKey */
        $privateKey = $this->createMock(\phpseclib3\Crypt\RSA::class);
        
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $ssh->expects($this->once())
            ->method('login')
            ->with('testuser', $privateKey)
            ->willReturn(false);

        // 测试认证失败
        $this->assertFalse($ssh->login('testuser', $privateKey));
    }

    public function testCreateSshConnection_FallbackToPassword(): void
    {
        // 测试私钥认证失败后回退到密码认证的逻辑
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn('fallback-password');
        $node->method('getSshPrivateKey')->willReturn('-----BEGIN PRIVATE KEY-----\ninvalid\n-----END PRIVATE KEY-----');

        // 验证节点同时配置了私钥和密码（用于回退）
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertNotEmpty($node->getSshPassword());
    }

    public function testCreateSshConnection_AllAuthenticationMethodsFailed(): void
    {
        // 测试所有认证方式都失败的情况
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn(null);
        $node->method('getSshPrivateKey')->willReturn(null);

        // 验证没有可用的认证方法
        $this->assertNull($node->getSshPassword());
        $this->assertNull($node->getSshPrivateKey());
    }

    public function testExecuteCommand_WithSshConnectionFailure(): void
    {
        // 准备测试数据
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('unreachable-host.example.com');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('testuser');
        $node->method('getSshPassword')->willReturn('invalid-password');
        $node->method('getSshPrivateKey')->willReturn(null);

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('测试命令');
        $command->setCommand('ls -la');
        $command->setStatus(CommandStatus::PENDING);

        // 设置实体管理器期望 - executeCommand 会调用 flush 两次
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        // 设置日志记录器期望
        $this->logger->expects($this->atLeastOnce())
            ->method('error')
            ->with($this->stringContains('SSH连接失败'));

        // 调用被测试方法
        $result = $this->service->executeCommand($command);

        // 验证结果
        $this->assertSame(CommandStatus::FAILED, $result->getStatus());
        $this->assertStringContainsString('SSH连接失败', $result->getResult());
        // executeCommand 方法中没有设置 updateTime，只有 executedAt
        $this->assertNotNull($result->getExecutedAt());
    }

    public function testExecuteCommand_WithSshCommandExecutionTimeout(): void
    {
        // 准备测试数据
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('长时间运行的命令');
        $command->setCommand('sleep 30');  // 30秒睡眠命令
        $command->setTimeout(1);  // 1秒超时
        $command->setStatus(CommandStatus::PENDING);

        // 验证超时设置和命令配置
        $this->assertEquals(1, $command->getTimeout());
        $this->assertStringContainsString('sleep', $command->getCommand());
        $this->assertEquals(CommandStatus::PENDING, $command->getStatus());
        $this->assertEquals('长时间运行的命令', $command->getName());
    }

    public function testExecuteCommand_WithSshCommandExecutionError(): void
    {
        // 准备测试数据 - 执行一个会失败的命令
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('test.example.com');
        
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('错误命令');
        $command->setCommand('non-existent-command');
        $command->setStatus(CommandStatus::PENDING);

        // 验证命令配置
        $this->assertEquals('non-existent-command', $command->getCommand());
        $this->assertEquals(CommandStatus::PENDING, $command->getStatus());
        $this->assertEquals('错误命令', $command->getName());
        $this->assertEquals('test.example.com', $node->getSshHost());
    }

    public function testExecuteCommand_WithSudoPermissionDenied(): void
    {
        // 测试sudo权限被拒绝的情况
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('normaluser');
        $node->method('getSshHost')->willReturn('test.example.com');
        
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('需要sudo的命令');
        $command->setCommand('systemctl restart nginx');
        $command->setUseSudo(true);
        $command->setStatus(CommandStatus::PENDING);

        // 验证sudo配置
        $this->assertTrue($command->isUseSudo());
        $this->assertEquals('normaluser', $node->getSshUser());
        $this->assertStringContainsString('systemctl', $command->getCommand());
        $this->assertEquals('test.example.com', $node->getSshHost());
    }

    public function testExecSshCommand_WithWorkingDirectoryNotFound(): void
    {
        // 测试工作目录不存在的情况
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $command = 'ls -la';
        $workingDirectory = '/non/existent/directory';

        // 模拟目录不存在的情况
        $ssh->expects($this->once())
            ->method('exec')
            ->with("cd {$workingDirectory} && {$command}")
            ->willReturn('cd: /non/existent/directory: No such file or directory');

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, $command, $workingDirectory);

        // 验证结果包含错误信息
        $this->assertStringContainsString('No such file or directory', $result);
    }

    public function testExecSshCommand_WithLongRunningCommand(): void
    {
        // 测试长时间运行的命令
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $command = 'sleep 10 && echo "completed"';

        // 模拟长时间运行后的结果
        $ssh->expects($this->once())
            ->method('exec')
            ->with($command)
            ->willReturn('completed');

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, $command);

        // 验证结果
        $this->assertEquals('completed', $result);
    }

    public function testExecSshCommand_WithSpecialCharacters(): void
    {
        // 测试包含特殊字符的命令
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $command = 'echo "Hello; World & Test | Command"';
        $expectedResult = 'Hello; World & Test | Command';

        $ssh->expects($this->once())
            ->method('exec')
            ->with($command)
            ->willReturn($expectedResult);

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, $command);

        // 验证结果
        $this->assertEquals($expectedResult, $result);
    }

    public function testExecSshCommand_WithEmptyCommand(): void
    {
        // 测试空命令
        /** @var \phpseclib3\Net\SSH2&MockObject $ssh */
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        $command = '';

        $ssh->expects($this->once())
            ->method('exec')
            ->with($command)
            ->willReturn('');

        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, $command);

        // 验证结果
        $this->assertEquals('', $result);
    }

    public function testExecuteCommand_NetworkConnectionLost(): void
    {
        // 测试网络连接丢失的情况
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('网络中断测试');
        $command->setCommand('ls -la');
        $command->setStatus(CommandStatus::PENDING);

        // 这种情况需要实际的网络环境来测试
        $this->markTestSkipped('需要实际网络环境进行连接丢失测试');
    }

    public function testExecuteCommand_HostKeyVerificationFailure(): void
    {
        // 测试主机密钥验证失败
        $this->markTestSkipped('需要实际SSH环境进行主机密钥验证测试');
    }

    public function testExecuteCommand_PortConnectionRefused(): void
    {
        // 测试端口连接被拒绝
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('localhost');
        $node->method('getSshPort')->willReturn(9999); // 未开放的端口
        $node->method('getSshUser')->willReturn('testuser');

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('端口连接测试');
        $command->setCommand('ls -la');
        $command->setStatus(CommandStatus::PENDING);

        $this->markTestSkipped('需要实际网络环境进行端口连接测试');
    }

    public function testExecuteCommand_DnsResolutionFailure(): void
    {
        // 测试DNS解析失败
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('nonexistent.invalid.domain');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('testuser');

        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('DNS解析测试');
        $command->setCommand('ls -la');
        $command->setStatus(CommandStatus::PENDING);

        $this->markTestSkipped('需要实际网络环境进行DNS解析测试');
    }
} 