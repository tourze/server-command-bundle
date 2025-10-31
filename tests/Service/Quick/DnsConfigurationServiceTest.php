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
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(DnsConfigurationService::class)]
#[RunTestsInSeparateProcesses]
final class DnsConfigurationServiceTest extends AbstractIntegrationTestCase
{
    protected function onSetUp(): void
    {
        // 无需特殊设置
    }

    public function testServiceCreation(): void
    {
        $service = self::getService(DnsConfigurationService::class);
        $this->assertInstanceOf(DnsConfigurationService::class, $service);
    }

    public function testCheckAndFixDnsNormal(): void
    {
        // 创建进度模型实现
        $deployTask = new class implements ProgressModel {
            private int $appendLogCalls = 0;

            public function appendLog(string $message): void
            {
                ++$this->appendLogCalls;
            }

            public function setProgress(?int $progress): void
            {
            }

            public function getProgress(): float
            {
                return 0.0;
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

            public function getAppendLogCalls(): int
            {
                return $this->appendLogCalls;
            }
        };

        $node = new class extends Node {
            public function getName(): string
            {
                return 'test-node';
            }
        };

        // 创建RemoteCommand对象
        $mockCommand = new RemoteCommand();
        $mockCommand->setName('test-dns-check');
        $mockCommand->setCommand('echo "DNS check"');
        $mockCommand->setResult('CONNECT_OK');
        $mockCommand->setStatus(CommandStatus::COMPLETED);

        // 创建 stub 对象
        $remoteCommandRepositoryStub = TestCase::createStub(RemoteCommandRepository::class);
        $entityManagerStub = TestCase::createStub(EntityManagerInterface::class);
        $loggerStub = TestCase::createStub(LoggerInterface::class);
        $messageBusStub = TestCase::createStub(MessageBusInterface::class);
        $sshConnectionServiceStub = TestCase::createStub(SshConnectionService::class);
        $sshCommandExecutorStub = TestCase::createStub(SshCommandExecutor::class);

        // 创建RemoteCommandService匿名类，调用父构造函数
        $remoteCommandService = new class($mockCommand, $remoteCommandRepositoryStub, $entityManagerStub, $loggerStub, $messageBusStub, $sshConnectionServiceStub, $sshCommandExecutorStub) extends RemoteCommandService {
            private RemoteCommand $mockCommand;

            public function __construct(
                RemoteCommand $mockCommand,
                RemoteCommandRepository $remoteCommandRepository,
                EntityManagerInterface $entityManager,
                LoggerInterface $logger,
                MessageBusInterface $messageBus,
                SshConnectionService $sshConnectionService,
                SshCommandExecutor $sshCommandExecutor,
            ) {
                $this->mockCommand = $mockCommand;
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

            public function createCommand(Node $node, string $name, string $command, ?string $workingDirectory = null, ?bool $useSudo = false, ?int $timeout = 300, ?array $tags = null): RemoteCommand
            {
                return $this->mockCommand;
            }

            public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
            {
                return $this->mockCommand;
            }
        };

        // 在获取服务前设置容器中的服务
        self::getContainer()->set(RemoteCommandService::class, $remoteCommandService);
        $service = self::getService(DnsConfigurationService::class);

        // 调用被测试方法
        $service->checkAndFixDns($deployTask, $node);

        // 验证进度日志被记录
        $this->assertGreaterThan(0, $deployTask->getAppendLogCalls());
    }

    public function testCheckAndFixDnsPollutionDetected(): void
    {
        // 创建进度模型实现
        $deployTask = new class implements ProgressModel {
            private int $appendLogCalls = 0;

            public function appendLog(string $message): void
            {
                ++$this->appendLogCalls;
            }

            public function setProgress(?int $progress): void
            {
            }

            public function getProgress(): float
            {
                return 0.0;
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

            public function getAppendLogCalls(): int
            {
                return $this->appendLogCalls;
            }
        };

        $node = new class extends Node {
            public function getName(): string
            {
                return 'test-node';
            }
        };

        // 创建RemoteCommand对象 - 检测到 DNS 污染的场景
        $mockCommand = new RemoteCommand();
        $mockCommand->setName('test-dns-check');
        $mockCommand->setCommand('echo "DNS check"');
        $mockCommand->setResult('DNS_FAILED');
        $mockCommand->setStatus(CommandStatus::COMPLETED);

        // 创建 stub 对象
        $remoteCommandRepositoryStub = TestCase::createStub(RemoteCommandRepository::class);
        $entityManagerStub = TestCase::createStub(EntityManagerInterface::class);
        $loggerStub = TestCase::createStub(LoggerInterface::class);
        $messageBusStub = TestCase::createStub(MessageBusInterface::class);
        $sshConnectionServiceStub = TestCase::createStub(SshConnectionService::class);
        $sshCommandExecutorStub = TestCase::createStub(SshCommandExecutor::class);

        // 创建RemoteCommandService匿名类
        $remoteCommandService = new class($mockCommand, $remoteCommandRepositoryStub, $entityManagerStub, $loggerStub, $messageBusStub, $sshConnectionServiceStub, $sshCommandExecutorStub) extends RemoteCommandService {
            private RemoteCommand $mockCommand;

            public function __construct(
                RemoteCommand $mockCommand,
                RemoteCommandRepository $remoteCommandRepository,
                EntityManagerInterface $entityManager,
                LoggerInterface $logger,
                MessageBusInterface $messageBus,
                SshConnectionService $sshConnectionService,
                SshCommandExecutor $sshCommandExecutor,
            ) {
                $this->mockCommand = $mockCommand;
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

            public function createCommand(Node $node, string $name, string $command, ?string $workingDirectory = null, ?bool $useSudo = false, ?int $timeout = 300, ?array $tags = null): RemoteCommand
            {
                return $this->mockCommand;
            }

            public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
            {
                return $this->mockCommand;
            }
        };

        // 在获取服务前设置容器中的服务
        self::getContainer()->set(RemoteCommandService::class, $remoteCommandService);
        $service = self::getService(DnsConfigurationService::class);

        // 调用被测试方法 - 这里实际会因为模拟的DNS失败而触发修复流程
        $service->checkAndFixDns($deployTask, $node);

        // 验证进度日志被记录
        $this->assertGreaterThan(0, $deployTask->getAppendLogCalls());
    }
}
