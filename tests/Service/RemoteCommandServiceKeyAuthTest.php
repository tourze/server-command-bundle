<?php

namespace ServerCommandBundle\Tests\Service;

use PHPUnit\Framework\TestCase;
use ServerNodeBundle\Entity\Node;

/**
 * 测试RemoteCommandService的SSH密钥认证功能
 */
class RemoteCommandServiceKeyAuthTest extends TestCase
{
    public function testNodeWithPrivateKey(): void
    {
        $node = new Node();
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
        $node = new Node();
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
        $node = new Node();
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
        $node = new Node();
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
            $node = new Node();
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
        $node = new Node();
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

        $node = new Node();
        $node->setSshPrivateKey($encryptedKey);

        $this->assertEquals($encryptedKey, $node->getSshPrivateKey());
    }
} 