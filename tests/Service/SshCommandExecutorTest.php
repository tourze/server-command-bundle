<?php

namespace ServerCommandBundle\Tests\Service;

use phpseclib3\Net\SSH2;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Service\SshCommandExecutor;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[RunTestsInSeparateProcesses]
#[CoversClass(SshCommandExecutor::class)]
final class SshCommandExecutorTest extends AbstractIntegrationTestCase
{
    private SshCommandExecutor $executor;

    protected function onTearDown(): void
    {
        // 重置环境变量
        putenv('DISABLE_LOGGING_IN_TESTS');
        unset($_ENV['DISABLE_LOGGING_IN_TESTS']);
    }

    protected function onSetUp(): void
    {
        // 禁用异步数据库插入包的日志输出，避免测试失败
        putenv('DISABLE_LOGGING_IN_TESTS=true');
        $_ENV['DISABLE_LOGGING_IN_TESTS'] = 'true';

        $this->executor = self::getService(SshCommandExecutor::class);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(SshCommandExecutor::class, $this->executor);
    }

    public function testExecute(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $ssh = new class('test') extends SSH2 {
            public function __construct(string $host)
            {
                parent::__construct($host);
            }

            /**
             * @param string|resource $command
             * @param callable|null $callback
             * @phpstan-ignore return.unusedType
             */
            #[\Override]
            public function exec($command, $callback = null): mixed
            {
                return 'total 12\ndrwxr-xr-x 3 user user 4096 Jan 1 12:00 .';
            }
        };
        $expectedResult = 'total 12\ndrwxr-xr-x 3 user user 4096 Jan 1 12:00 .';

        // 准备测试数据
        $command = 'ls -la';
        $workingDirectory = '/var/www';
        $useSudo = false;

        // 调用被测试方法
        $result = $this->executor->execute($ssh, $command, $workingDirectory, $useSudo);

        // 验证结果
        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteWithSudo(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $ssh = new class('test') extends SSH2 {
            public function __construct(string $host)
            {
                parent::__construct($host);
            }

            /**
             * @param string|resource $command
             * @param callable|null $callback
             * @phpstan-ignore return.unusedType
             */
            #[\Override]
            public function exec($command, $callback = null): mixed
            {
                return 'nginx restarted successfully';
            }
        };
        $expectedResult = 'nginx restarted successfully';

        // 使用匿名类替代createStub以避免动态调用问题
        $node = new class extends Node {
            public function getSshUser(): string
            {
                return 'testuser';
            }

            public function getId(): string
            {
                return '123';
            }
        };

        // 准备测试数据
        $command = 'systemctl restart nginx';
        $workingDirectory = '/etc/nginx';
        $useSudo = true;

        // 调用被测试方法
        $result = $this->executor->execute($ssh, $command, $workingDirectory, $useSudo, $node);

        // 验证结果
        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteWithRootUser(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $ssh = new class('test') extends SSH2 {
            public function __construct(string $host)
            {
                parent::__construct($host);
            }

            /**
             * @param string|resource $command
             * @param callable|null $callback
             * @phpstan-ignore return.unusedType
             */
            #[\Override]
            public function exec($command, $callback = null): mixed
            {
                return 'apache2 restarted successfully';
            }
        };
        $expectedResult = 'apache2 restarted successfully';

        // 使用匿名类替代createStub以避免动态调用问题
        $node = new class extends Node {
            public function getSshUser(): string
            {
                return 'root';
            }
        };

        // 准备测试数据
        $command = 'systemctl restart apache2';
        $workingDirectory = '/etc/apache2';
        $useSudo = true; // 即使设置了 sudo，root 用户也不会使用 sudo

        // 调用被测试方法
        $result = $this->executor->execute($ssh, $command, $workingDirectory, $useSudo, $node);

        // 验证结果
        $this->assertSame($expectedResult, $result);
    }

    public function testExecuteWithoutWorkingDirectory(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $ssh = new class('test') extends SSH2 {
            public function __construct(string $host)
            {
                parent::__construct($host);
            }

            /**
             * @param string|resource $command
             * @param callable|null $callback
             * @phpstan-ignore return.unusedType
             */
            #[\Override]
            public function exec($command, $callback = null): mixed
            {
                return 'testuser';
            }
        };
        $expectedResult = 'testuser';

        // 准备测试数据
        $command = 'whoami';
        $workingDirectory = null;
        $useSudo = false;

        // 调用被测试方法
        $result = $this->executor->execute($ssh, $command, $workingDirectory, $useSudo);

        // 验证结果
        $this->assertSame($expectedResult, $result);
    }
}
