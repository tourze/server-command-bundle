<?php

namespace ServerCommandBundle\Tests\Service\Quick;

use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Contracts\ProgressModel;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerCommandBundle\Service\Quick\DockerEnvironmentService;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(DockerEnvironmentService::class)]
final class DockerEnvironmentServiceTest extends TestCase
{
    private DockerEnvironmentService $service;

    private RemoteCommandService $remoteCommandService;

    private ProgressModel $progressModel;

    private Node $node;

    protected function setUp(): void
    {
        parent::setUp();

        // 创建 stub 对象
        $remoteCommandRepositoryStub = TestCase::createStub(RemoteCommandRepository::class);
        $entityManagerStub = TestCase::createStub(EntityManagerInterface::class);
        $loggerStub = TestCase::createStub(LoggerInterface::class);
        $messageBusStub = TestCase::createStub(MessageBusInterface::class);
        $sshConnectionServiceStub = TestCase::createStub(SshConnectionService::class);
        $sshCommandExecutorStub = TestCase::createStub(SshCommandExecutor::class);

        // 使用匿名类替代createMock以符合PHPStan要求
        $this->remoteCommandService = new class($remoteCommandRepositoryStub, $entityManagerStub, $loggerStub, $messageBusStub, $sshConnectionServiceStub, $sshCommandExecutorStub) extends RemoteCommandService {
            /** @var list<RemoteCommand> */
            public array $createdCommands = [];

            public int $createCount = 0;

            public int $executeCount = 0;

            /** @var list<RemoteCommand> */
            public array $returnCommands = [];

            public function __construct(
                RemoteCommandRepository $remoteCommandRepository,
                EntityManagerInterface $entityManager,
                LoggerInterface $logger,
                MessageBusInterface $messageBus,
                SshConnectionService $sshConnectionService,
                SshCommandExecutor $sshCommandExecutor,
            ) {
                // 调用父类构造函数，使用传入的 stub 对象
                parent::__construct(
                    $remoteCommandRepository,
                    $entityManager,
                    $logger,
                    $messageBus,
                    $sshConnectionService,
                    $sshCommandExecutor
                );
            }

            public function setReturnCommands(RemoteCommand ...$commands): void
            {
                $this->returnCommands = array_values($commands);
            }

            public function createCommand(Node $node, string $name, string $command, ?string $workingDirectory = null, ?bool $useSudo = false, ?int $timeout = 300, ?array $tags = null): RemoteCommand
            {
                $cmd = $this->returnCommands[$this->createCount] ?? new RemoteCommand();
                $this->createdCommands[] = $cmd;
                ++$this->createCount;

                return $cmd;
            }

            public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
            {
                ++$this->executeCount;

                return $command;
            }
        };

        $this->progressModel = new class implements ProgressModel {
            public int $progressValue = 0;

            /** @var list<int> */
            public array $progressHistory = [];

            /** @var list<string> */
            public array $logs = [];

            public function setProgress(?int $progress): void
            {
                $this->progressValue = $progress ?? 0;
                $this->progressHistory[] = $this->progressValue;
            }

            public function getProgress(): float
            {
                return (float) $this->progressValue;
            }

            public function appendLog(string $message): void
            {
                $this->logs[] = $message;
            }

            public function isCompleted(): bool
            {
                return false;
            }

            public function markAsCompleted(): void
            {
            }

            public function markAsFailed(string $error): void
            {
            }
        };

        $this->node = new class extends Node {
            public function getName(): string
            {
                return 'test-node';
            }
        };

        $this->service = new DockerEnvironmentService($this->remoteCommandService);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(DockerEnvironmentService::class, $this->service);
    }

    public function testCheckDockerEnvironment(): void
    {
        // 测试场景：Docker已安装且正常运行
        $dockerCheckCommand = $this->createRemoteCommand('Docker version 24.0.0, build 1234567');
        $dockerInfoCommand = $this->createRemoteCommand('Docker正常');

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands($dockerCheckCommand, $dockerInfoCommand);

        $this->service->checkDockerEnvironment($this->progressModel, $this->node);

        // 验证命令创建和执行次数
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(2, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(2, $this->remoteCommandService->executeCount);

        // 验证进度和日志更新
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(5, $this->progressModel->progressValue);
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    public function testCheckDockerEnvironmentDockerNotInstalled(): void
    {
        // 测试场景：Docker未安装，需要自动安装
        $dockerCheckCommand = $this->createRemoteCommand('Docker未安装');

        // 模拟安装流程中的命令
        $systemCheckCommand = $this->createRemoteCommand('Ubuntu 20.04');
        $installCurlCommand = $this->createRemoteCommand('curl已存在');
        $downloadScriptCommand = $this->createRemoteCommand('脚本下载成功');
        $installDockerCommand = $this->createRemoteCommand('Docker安装完成');
        $addUserCommand = $this->createRemoteCommand('用户权限配置完成');
        $startDockerCommand = $this->createRemoteCommand('Docker服务启动成功');
        $verifyDockerCommand = $this->createRemoteCommand('Docker验证成功');

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands(
            $dockerCheckCommand,
            $systemCheckCommand,
            $installCurlCommand,
            $downloadScriptCommand,
            $installDockerCommand,
            $addUserCommand,
            $startDockerCommand,
            $verifyDockerCommand
        );

        $this->service->checkDockerEnvironment($this->progressModel, $this->node);

        // 验证命令创建和执行次数
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(8, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(8, $this->remoteCommandService->executeCount);

        // 验证进度更新（Docker安装流程应该有多次进度更新）
        // @phpstan-ignore property.notFound (匿名类属性)
        $progressHistory = $this->progressModel->progressHistory;
        $this->assertIsArray($progressHistory);
        $this->assertGreaterThan(1, count($progressHistory));
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    public function testCheckDockerEnvironmentDockerServiceAbnormal(): void
    {
        // 测试场景：Docker已安装但服务异常，需要启动服务
        $dockerCheckCommand = $this->createRemoteCommand('Docker version 24.0.0, build 1234567');
        $dockerInfoCommand = $this->createRemoteCommand('Docker服务异常');

        // 模拟启动服务的命令
        $systemctlCommand = $this->createRemoteCommand('Docker已启动');
        $verifyAfterStartCommand = $this->createRemoteCommand('验证成功');

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands(
            $dockerCheckCommand,
            $dockerInfoCommand,
            $systemctlCommand,
            $verifyAfterStartCommand
        );

        $this->service->checkDockerEnvironment($this->progressModel, $this->node);

        // 验证命令创建和执行次数
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(4, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(4, $this->remoteCommandService->executeCount);

        // 验证进度和日志更新
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(5, $this->progressModel->progressValue);
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    /**
     * 创建带有指定结果的RemoteCommand对象
     */
    private function createRemoteCommand(string $result): RemoteCommand
    {
        $command = new RemoteCommand();
        $command->setName('test-command');
        $command->setCommand('echo test');
        $command->setResult($result);
        $command->setStatus(CommandStatus::COMPLETED);

        return $command;
    }
}
