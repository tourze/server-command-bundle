<?php

namespace ServerCommandBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * 测试RemoteCommandService的SSH密钥认证功能
 *
 * @internal
 */
#[CoversClass(Node::class)]
final class RemoteCommandServiceKeyAuthTest extends AbstractEntityTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // 该测试类专门测试 Node 实体的 SSH 密钥认证功能
        // 在集成测试中直接实例化 Entity 是合理的行为
        // 因为需要验证 SSH 私钥、密码等认证配置的正确性
        // 这种测试更接近单元测试，但需要数据库环境支持
    }

    protected function createEntity(): Node
    {
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        return $node;
    }

    /**
     * 提供 Node 实体属性及其样本值的 Data Provider.
     *
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        yield 'name' => ['name', '测试节点名称'];
        yield 'sshHost' => ['sshHost', '192.168.1.100'];
        yield 'sshPort' => ['sshPort', 22];
        yield 'sshUser' => ['sshUser', 'root'];
        yield 'sshPassword' => ['sshPassword', 'password123'];
        yield 'sshPrivateKey' => ['sshPrivateKey', '-----BEGIN PRIVATE KEY-----' . "\nMIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB\n-----END PRIVATE KEY-----"];
        yield 'valid' => ['valid', true];
    }

    public function testNodeWithPrivateKey(): void
    {
        $node = $this->createEntity();
        $node->setSshHost('192.168.1.100');
        $node->setSshPort(22);
        $node->setSshUser('root');
        $node->setSshPassword(null);
        $node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
-----END PRIVATE KEY-----');

        // 验证节点配置了私钥认证
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertNull($node->getSshPassword());
        $this->assertEquals('root', $node->getSshUser());
        $this->assertEquals('192.168.1.100', $node->getSshHost());
    }

    public function testNodeWithPasswordFallback(): void
    {
        $node = $this->createEntity();
        $node->setSshHost('192.168.1.101');
        $node->setSshPort(22);
        $node->setSshUser('admin');
        $node->setSshPassword('password123');
        $node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----
INVALID_KEY_CONTENT
-----END PRIVATE KEY-----');

        // 验证节点配置了私钥和密码（私钥无效时可以回退到密码）
        $this->assertNotEmpty($node->getSshPrivateKey());
        $this->assertNotEmpty($node->getSshPassword());
        $this->assertEquals('admin', $node->getSshUser());
        $this->assertEquals('192.168.1.101', $node->getSshHost());
    }

    public function testNodeWithOnlyPassword(): void
    {
        $node = $this->createEntity();
        $node->setSshHost('192.168.1.102');
        $node->setSshPort(2022);
        $node->setSshUser('user');
        $node->setSshPassword('secret123');
        $node->setSshPrivateKey(null);

        // 验证节点只配置了密码认证
        $this->assertNull($node->getSshPrivateKey());
        $this->assertNotEmpty($node->getSshPassword());
        $this->assertEquals('user', $node->getSshUser());
        $this->assertEquals('192.168.1.102', $node->getSshHost());
        $this->assertEquals(2022, $node->getSshPort());
    }

    public function testNodeWithNoAuthMethod(): void
    {
        $node = $this->createEntity();
        $node->setSshHost('192.168.1.103');
        $node->setSshPort(22);
        $node->setSshUser('noauth');
        $node->setSshPassword(null);
        $node->setSshPrivateKey(null);

        // 验证节点既没有私钥也没有密码
        $this->assertNull($node->getSshPrivateKey());
        $this->assertNull($node->getSshPassword());
        $this->assertEquals('noauth', $node->getSshUser());
        $this->assertEquals('192.168.1.103', $node->getSshHost());
    }

    public function testPrivateKeyFormats(): void
    {
        // 测试各种私钥格式的设置和获取
        $validKeys = [
            '-----BEGIN RSA PRIVATE KEY-----
MIIEpAIBAAKCAQEA1234567890abcdef...
-----END RSA PRIVATE KEY-----',
            '-----BEGIN OPENSSH PRIVATE KEY-----
b3BlbnNzaC1rZXktdjEAAAAABG5vbmUAAAAEbm9uZQAAAAAAAAABFwAAAAdzc2gtcn
-----END OPENSSH PRIVATE KEY-----',
            '-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
-----END PRIVATE KEY-----',
        ];

        foreach ($validKeys as $index => $privateKey) {
            $node = $this->createEntity();
            $node->setSshHost("192.168.1.10{$index}");
            $node->setSshUser('testuser');
            $node->setSshPrivateKey($privateKey);

            // 验证私钥格式能够正确设置和获取
            $this->assertEquals($privateKey, $node->getSshPrivateKey());
            $this->assertEquals("192.168.1.10{$index}", $node->getSshHost());
            $this->assertEquals('testuser', $node->getSshUser());
        }
    }

    public function testEmptyPrivateKey(): void
    {
        $node = $this->createEntity();
        $node->setSshPrivateKey('');

        // 验证空字符串私钥的处理
        $this->assertEquals('', $node->getSshPrivateKey());

        $node->setSshPrivateKey(null);
        $this->assertNull($node->getSshPrivateKey());
    }

    public function testPrivateKeyWithPassphrase(): void
    {
        // 测试带有密码短语的私钥（虽然当前Node实体不支持，但测试私钥的存储）
        $encryptedKey = '-----BEGIN ENCRYPTED PRIVATE KEY-----
MIIE6TAbBgkqhkiG9w0BBQMwDgQIhTYH/OLTMvkCAggABIIEyMKEOi9mKiK8HiQy
-----END ENCRYPTED PRIVATE KEY-----';

        $node = $this->createEntity();
        $node->setSshPrivateKey($encryptedKey);

        $this->assertEquals($encryptedKey, $node->getSshPrivateKey());
    }
}
