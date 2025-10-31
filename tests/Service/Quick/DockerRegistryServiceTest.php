<?php

namespace ServerCommandBundle\Tests\Service\Quick;

use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Contracts\ProgressModel;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerCommandBundle\Service\Quick\DnsConfigurationService;
use ServerCommandBundle\Service\Quick\DockerRegistryService;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DockerRegistryService::class)]
#[RunTestsInSeparateProcesses]
final class DockerRegistryServiceTest extends AbstractIntegrationTestCase
{
    private DockerRegistryService $service;

    private RemoteCommandService $remoteCommandService;

    private DnsConfigurationService $dnsConfigurationService;

    private LoggerInterface $logger;

    private ProgressModel $progressModel;

    private Node $node;

    protected function onSetUp(): void
    {
        // 创建 stub 对象
        $remoteCommandRepositoryStub = TestCase::createStub(RemoteCommandRepository::class);
        $entityManagerStub = TestCase::createStub(EntityManagerInterface::class);
        $loggerStub = TestCase::createStub(LoggerInterface::class);
        $messageBusStub = TestCase::createStub(MessageBusInterface::class);
        $sshConnectionServiceStub = TestCase::createStub(SshConnectionService::class);
        $sshCommandExecutorStub = TestCase::createStub(SshCommandExecutor::class);

        // 使用匿名类替代Mock
        $this->remoteCommandService = new class($remoteCommandRepositoryStub, $entityManagerStub, $loggerStub, $messageBusStub, $sshConnectionServiceStub, $sshCommandExecutorStub) extends RemoteCommandService {
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
                ++$this->createCount;

                return $cmd;
            }

            public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
            {
                ++$this->executeCount;

                return $command;
            }
        };

        // 创建 RemoteCommandService stub 对象
        $remoteCommandServiceStub = TestCase::createStub(RemoteCommandService::class);

        $this->dnsConfigurationService = new class($remoteCommandServiceStub) extends DnsConfigurationService {
            public int $checkAndFixDnsCount = 0;

            public function __construct(RemoteCommandService $remoteCommandService)
            {
                // 调用父类构造函数，使用传入的 stub 对象
                parent::__construct($remoteCommandService);
            }

            public function checkAndFixDns(ProgressModel $progressModel, Node $node): void
            {
                ++$this->checkAndFixDnsCount;
            }
        };

        $this->logger = new class implements LoggerInterface {
            public int $infoCount = 0;

            public function emergency(string|\Stringable $message, array $context = []): void
            {
            }

            public function alert(string|\Stringable $message, array $context = []): void
            {
            }

            public function critical(string|\Stringable $message, array $context = []): void
            {
            }

            public function error(string|\Stringable $message, array $context = []): void
            {
            }

            public function warning(string|\Stringable $message, array $context = []): void
            {
            }

            public function notice(string|\Stringable $message, array $context = []): void
            {
            }

            public function info(string|\Stringable $message, array $context = []): void
            {
                ++$this->infoCount;
            }

            public function debug(string|\Stringable $message, array $context = []): void
            {
            }

            public function log($level, string|\Stringable $message, array $context = []): void
            {
            }
        };

        $this->progressModel = new class implements ProgressModel {
            /** @var list<string> */
            public array $logs = [];

            public function setProgress(?int $progress): void
            {
            }

            public function getProgress(): float
            {
                return 0.0;
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

        // 使用匿名类构造服务
        /** @phpstan-ignore integrationTest.noDirectInstantiationOfCoveredClass */
        $this->service = new DockerRegistryService(
            $this->remoteCommandService,
            $this->dnsConfigurationService,
            $this->logger
        );
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(DockerRegistryService::class, $this->service);
    }

    public function testConfigureDockerRegistry(): void
    {
        // 测试场景：中国大陆IP，需要配置镜像加速器
        $ipCheckCommand = $this->createRemoteCommand('{"country":"China","countryCode":"CN"}');
        $createDirCommand = $this->createRemoteCommand('');
        $configMirrorCommand = $this->createRemoteCommand('');
        $restartDockerCommand = $this->createRemoteCommand('');

        // @phpstan-ignore method.notFound (匿名类扩展)
        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands(
            $ipCheckCommand,
            $createDirCommand,
            $configMirrorCommand,
            $restartDockerCommand
        );

        $this->service->configureDockerRegistry($this->progressModel, $this->node);

        // 验证DNS检查被调用
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->dnsConfigurationService->checkAndFixDnsCount);

        // 验证命令创建和执行
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(4, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(4, $this->remoteCommandService->executeCount);

        // 验证日志输出
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(3, $this->logger->infoCount);
    }

    public function testConfigureDockerRegistryNonChina(): void
    {
        // 测试场景：非中国大陆IP，不配置镜像加速器
        $ipCheckCommand = $this->createRemoteCommand('{"country":"United States","countryCode":"US"}');

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands($ipCheckCommand);

        $this->service->configureDockerRegistry($this->progressModel, $this->node);

        // 验证DNS检查被调用
        // @phpstan-ignore property.notFound (匿名类属性)
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->dnsConfigurationService->checkAndFixDnsCount);

        // 验证只创建和执行一个命令（IP检测）
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->remoteCommandService->executeCount);

        // 验证日志输出
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertGreaterThan(0, count($logs));
    }

    public function testVerifyMirrorConfiguration(): void
    {
        // 测试场景：验证镜像加速器配置
        $verifyCommand = $this->createRemoteCommand(
            "Registry Mirrors:\n https://docker.1panel.live\n https://dockerhub.azk8s.cn"
        );

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands($verifyCommand);

        $this->service->verifyMirrorConfiguration($this->progressModel, $this->node);

        // 验证命令创建和执行
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(1, $this->remoteCommandService->executeCount);

        // 验证日志输出
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertCount(2, $logs);
    }

    public function testTestImagePullPerformance(): void
    {
        // 测试场景：测试镜像拉取性能
        $pullCommand = $this->createRemoteCommand('real 0m5.234s');
        $cleanupCommand = $this->createRemoteCommand('');

        // @phpstan-ignore method.notFound (匿名类扩展)
        $this->remoteCommandService->setReturnCommands($pullCommand, $cleanupCommand);

        $this->service->testImagePullPerformance($this->progressModel, $this->node);

        // 验证命令创建和执行
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(2, $this->remoteCommandService->createCount);
        // @phpstan-ignore property.notFound (匿名类属性)
        $this->assertSame(2, $this->remoteCommandService->executeCount);

        // 验证日志输出
        // @phpstan-ignore property.notFound (匿名类属性)
        $logs = $this->progressModel->logs;
        $this->assertIsArray($logs);
        $this->assertCount(3, $logs);
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
