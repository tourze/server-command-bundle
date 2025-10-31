<?php

namespace ServerCommandBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(SshConnectionService::class)]
#[RunTestsInSeparateProcesses]
final class SshConnectionServiceTest extends AbstractIntegrationTestCase
{
    private SshConnectionService $service;

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

        // 从容器中获取服务
        $this->service = self::getService(SshConnectionService::class);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(SshConnectionService::class, $this->service);
    }

    public function testCreateConnectionWithMockNode(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $node = new class extends Node {
            public function getSshHost(): string
            {
                return '127.0.0.1';
            }

            public function getSshPort(): int
            {
                return 22;
            }

            public function getSshUser(): string
            {
                return 'test';
            }

            public function getSshPassword(): string
            {
                return 'password';
            }
        };

        // 测试方法存在性，预期会抛出异常因为没有真实连接
        $this->expectException(\Exception::class);
        $this->service->createConnection($node);
    }

    public function testCreateConnectionWithSudo(): void
    {
        // 使用匿名类替代createStub以避免动态调用问题
        $node = new class extends Node {
            public function getSshHost(): string
            {
                return '127.0.0.1';
            }

            public function getSshPort(): int
            {
                return 22;
            }

            public function getSshUser(): string
            {
                return 'test';
            }

            public function getSshPassword(): string
            {
                return 'password';
            }
        };

        // 测试sudo模式
        $this->expectException(\Exception::class);
        $this->service->createConnection($node, true);
    }

    public function testConnectWithPassword(): void
    {
        // 测试参数
        $host = 'test.example.com';
        $port = 22;
        $user = 'testuser';
        $password = 'testpass';

        // 由于这是一个会实际尝试SSH连接的方法，我们期望它会抛出异常
        // 因为我们无法连接到真实的SSH服务器
        $this->expectException(\Exception::class);

        // 调用被测试方法
        $this->service->connectWithPassword($host, $port, $user, $password);
    }

    public function testConnectWithPasswordInvalidHost(): void
    {
        // 测试无效主机的情况
        $host = 'invalid-host-that-does-not-exist.local';
        $port = 22;
        $user = 'testuser';
        $password = 'testpass';

        // 期望抛出异常（连接失败）
        $this->expectException(\Exception::class);

        // 调用被测试方法
        $this->service->connectWithPassword($host, $port, $user, $password);
    }

    public function testConnectWithPrivateKey(): void
    {
        // 测试参数
        $host = 'test.example.com';
        $port = 22;
        $user = 'testuser';
        $privateKey = '-----BEGIN PRIVATE KEY-----
MIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC7VJTUt9Us8cKB
wQPiQHhGcn8zt13nj8T2pDNJY4m4C3VBvLGIvU1l8T7+8Y5e6XK1n1EjGWdR
-----END PRIVATE KEY-----';

        // 由于这是一个会实际尝试SSH连接的方法，我们期望它会抛出异常
        // 因为我们无法连接到真实的SSH服务器
        $this->expectException(\Exception::class);

        // 调用被测试方法
        $this->service->connectWithPrivateKey($host, $port, $user, $privateKey);
    }

    public function testConnectWithPrivateKeyInvalidKey(): void
    {
        // 测试无效私钥的情况
        $host = 'test.example.com';
        $port = 22;
        $user = 'testuser';
        $privateKey = 'invalid-private-key-content';

        // 期望抛出异常（私钥格式无效）
        $this->expectException(\Exception::class);

        // 调用被测试方法
        $this->service->connectWithPrivateKey($host, $port, $user, $privateKey);
    }

    public function testConnectWithPrivateKeyEmptyKey(): void
    {
        // 测试空私钥的情况
        $host = 'test.example.com';
        $port = 22;
        $user = 'testuser';
        $privateKey = '';

        // 期望抛出异常（私钥为空）
        $this->expectException(\Exception::class);

        // 调用被测试方法
        $this->service->connectWithPrivateKey($host, $port, $user, $privateKey);
    }
}
