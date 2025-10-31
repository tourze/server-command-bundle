<?php

namespace ServerCommandBundle\Tests\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitDoctrineEntity\AbstractEntityTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteFileTransfer::class)]
final class RemoteFileTransferTest extends AbstractEntityTestCase
{
    private RemoteFileTransfer $entity;

    private Node $node;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entity = new RemoteFileTransfer();
        // 创建真实的Node实例而不是Mock，避免类型错误
        $this->node = new Node();
        $this->node->setName('test-node');
    }

    public function testGetterSetterForId(): void
    {
        // ID 是自动生成的，默认为 0
        $this->assertEquals(0, $this->entity->getId());
    }

    public function testGetterSetterForNode(): void
    {
        $node = $this->node;
        $this->entity->setNode($node);

        $this->assertSame($this->node, $this->entity->getNode());
    }

    public function testGetterSetterForName(): void
    {
        $name = '上传配置文件';
        $this->entity->setName($name);

        $this->assertEquals($name, $this->entity->getName());
    }

    public function testGetterSetterForLocalPath(): void
    {
        $path = '/local/path/file.txt';
        $this->entity->setLocalPath($path);

        $this->assertEquals($path, $this->entity->getLocalPath());
    }

    public function testGetterSetterForRemotePath(): void
    {
        $path = '/remote/path/file.txt';
        $this->entity->setRemotePath($path);

        $this->assertEquals($path, $this->entity->getRemotePath());
    }

    public function testGetterSetterForTempPath(): void
    {
        $this->assertNull($this->entity->getTempPath()); // 默认为空

        $tempPath = '/tmp/upload_123456.txt';
        $this->entity->setTempPath($tempPath);

        $this->assertEquals($tempPath, $this->entity->getTempPath());
    }

    public function testGetterSetterForFileSize(): void
    {
        $this->assertNull($this->entity->getFileSize()); // 默认为空

        $fileSize = 1024;
        $this->entity->setFileSize($fileSize);

        $this->assertEquals($fileSize, $this->entity->getFileSize());
    }

    public function testGetterSetterForUseSudo(): void
    {
        $this->assertFalse($this->entity->isUseSudo()); // 默认为 false

        $this->entity->setUseSudo(true);

        $this->assertTrue($this->entity->isUseSudo());

        $this->entity->setUseSudo(false);
        $this->assertFalse($this->entity->isUseSudo());
    }

    public function testGetterSetterForEnabled(): void
    {
        $this->assertTrue($this->entity->isEnabled()); // 默认为 true

        $this->entity->setEnabled(false);

        $this->assertFalse($this->entity->isEnabled());

        $this->entity->setEnabled(true);
        $this->assertTrue($this->entity->isEnabled());
    }

    public function testGetterSetterForResult(): void
    {
        $this->assertNull($this->entity->getResult()); // 默认为空

        $result = '传输成功';
        $this->entity->setResult($result);

        $this->assertEquals($result, $this->entity->getResult());
    }

    public function testGetterSetterForTimeout(): void
    {
        $this->assertEquals(300, $this->entity->getTimeout()); // 默认 300 秒

        $timeout = 600;
        $this->entity->setTimeout($timeout);

        $this->assertEquals($timeout, $this->entity->getTimeout());
    }

    public function testGetterSetterForStatus(): void
    {
        $this->assertEquals(FileTransferStatus::PENDING, $this->entity->getStatus()); // 默认为 PENDING

        $status = FileTransferStatus::COMPLETED;
        $this->entity->setStatus($status);

        $this->assertEquals($status, $this->entity->getStatus());
    }

    public function testGetterSetterForStartedAt(): void
    {
        $this->assertNull($this->entity->getStartedAt()); // 默认为空

        $startedAt = new \DateTimeImmutable();
        $this->entity->setStartedAt($startedAt);

        $this->assertSame($startedAt, $this->entity->getStartedAt());
    }

    public function testGetterSetterForCompletedAt(): void
    {
        $this->assertNull($this->entity->getCompletedAt()); // 默认为空

        $completedAt = new \DateTimeImmutable();
        $this->entity->setCompletedAt($completedAt);

        $this->assertSame($completedAt, $this->entity->getCompletedAt());
    }

    public function testGetterSetterForTransferTime(): void
    {
        $this->assertNull($this->entity->getTransferTime()); // 默认为空

        $transferTime = 15.5;
        $this->entity->setTransferTime($transferTime);

        $this->assertEquals($transferTime, $this->entity->getTransferTime());
    }

    public function testGetterSetterForTags(): void
    {
        $this->assertNull($this->entity->getTags()); // 默认为空

        $tags = ['deployment', 'config'];
        $this->entity->setTags($tags);

        $this->assertEquals($tags, $this->entity->getTags());
    }

    public function testGetterSetterForCreatedBy(): void
    {
        $this->assertNull($this->entity->getCreatedBy()); // 默认为空

        $createdBy = 'admin';
        $this->entity->setCreatedBy($createdBy);

        $this->assertEquals($createdBy, $this->entity->getCreatedBy());
    }

    public function testGetterSetterForUpdatedBy(): void
    {
        $this->assertNull($this->entity->getUpdatedBy()); // 默认为空

        $updatedBy = 'system';
        $this->entity->setUpdatedBy($updatedBy);

        $this->assertEquals($updatedBy, $this->entity->getUpdatedBy());
    }

    public function testGetterSetterForCreateTime(): void
    {
        $this->assertNull($this->entity->getCreateTime()); // 默认为空

        $createTime = new \DateTimeImmutable();
        $this->entity->setCreateTime($createTime);

        $this->assertSame($createTime, $this->entity->getCreateTime());
    }

    public function testGetterSetterForUpdateTime(): void
    {
        $this->assertNull($this->entity->getUpdateTime()); // 默认为空

        $updateTime = new \DateTimeImmutable();
        $this->entity->setUpdateTime($updateTime);

        $this->assertSame($updateTime, $this->entity->getUpdateTime());
    }

    protected function createEntity(): object
    {
        $entity = new RemoteFileTransfer();
        $entity->setNode($this->node);
        $entity->setName('test-transfer');
        $entity->setLocalPath('/local/test.txt');
        $entity->setRemotePath('/remote/test.txt');

        return $entity;
    }

    /**
     * @return iterable<string, array{0: string, 1: mixed}>
     */
    public static function propertiesProvider(): iterable
    {
        // 只测试简单的、非必需的属性，避免复杂类型
        yield 'tempPath' => ['tempPath', '/tmp/test.txt'];
        yield 'fileSize' => ['fileSize', 1024];
        yield 'useSudo' => ['useSudo', true];
        yield 'enabled' => ['enabled', false];
        yield 'result' => ['result', 'success'];
        yield 'timeout' => ['timeout', 600];
        yield 'transferTime' => ['transferTime', 15.5];
        yield 'tags' => ['tags', ['test', 'deployment']];
        yield 'createdBy' => ['createdBy', 'admin'];
        yield 'updatedBy' => ['updatedBy', 'system'];
    }

    public function testToStringReturnsFormattedDescription(): void
    {
        $this->entity->setName('上传配置文件');
        $node = $this->node;
        $this->entity->setNode($node);
        $this->entity->setRemotePath('/etc/app/config.yml');

        $expected = '上传配置文件 -> test-node:/etc/app/config.yml';
        $this->assertEquals($expected, (string) $this->entity);
    }

    public function testToStringWithDifferentNodeName(): void
    {
        // 创建一个不同名称的node
        // 创建真实的Node实例而不是Mock，避免类型错误
        $differentNode = new Node();
        $differentNode->setName('production-server');

        $this->entity->setName('部署脚本');
        $node = $differentNode;
        $this->entity->setNode($node);
        $this->entity->setRemotePath('/opt/app/deploy.sh');

        $expected = '部署脚本 -> production-server:/opt/app/deploy.sh';
        $this->assertEquals($expected, (string) $this->entity);
    }

    public function testDefaultValues(): void
    {
        $entity = new RemoteFileTransfer();

        // 测试所有默认值
        $this->assertEquals(0, $entity->getId());
        $this->assertNull($entity->getTempPath());
        $this->assertNull($entity->getFileSize());
        $this->assertFalse($entity->isUseSudo());
        $this->assertTrue($entity->isEnabled());
        $this->assertNull($entity->getResult());
        $this->assertEquals(300, $entity->getTimeout());
        $this->assertEquals(FileTransferStatus::PENDING, $entity->getStatus());
        $this->assertNull($entity->getStartedAt());
        $this->assertNull($entity->getCompletedAt());
        $this->assertNull($entity->getTransferTime());
        $this->assertNull($entity->getTags());
        $this->assertNull($entity->getCreatedBy());
        $this->assertNull($entity->getUpdatedBy());
        $this->assertNull($entity->getCreateTime());
        $this->assertNull($entity->getUpdateTime());
    }

    public function testBooleanFieldWithNullValues(): void
    {
        // 测试布尔字段接受 null 值
        $this->entity->setUseSudo(null);
        $this->assertNull($this->entity->isUseSudo());

        $this->entity->setEnabled(null);
        $this->assertNull($this->entity->isEnabled());
    }

    public function testLargeFileSize(): void
    {
        // 测试大文件尺寸（使用 BIGINT）
        $largeSize = 9223372036854775807; // PHP_INT_MAX
        $this->entity->setFileSize($largeSize);
        $this->assertEquals($largeSize, $this->entity->getFileSize());
    }

    public function testLongPaths(): void
    {
        // 测试长路径（最大 500 字符）
        $longPath = str_repeat('/very/long/path', 30); // 约 450 字符

        $this->entity->setLocalPath($longPath);
        $this->assertEquals($longPath, $this->entity->getLocalPath());

        $this->entity->setRemotePath($longPath);
        $this->assertEquals($longPath, $this->entity->getRemotePath());

        $this->entity->setTempPath($longPath);
        $this->assertEquals($longPath, $this->entity->getTempPath());
    }

    public function testEdgeCaseTimeoutValues(): void
    {
        // 测试边界超时值
        $this->entity->setTimeout(0);
        $this->assertEquals(0, $this->entity->getTimeout());

        $this->entity->setTimeout(null);
        $this->assertNull($this->entity->getTimeout());

        $largeTimeout = 86400; // 24 小时
        $this->entity->setTimeout($largeTimeout);
        $this->assertEquals($largeTimeout, $this->entity->getTimeout());
    }

    public function testEmptyAndSpecialCharacterStrings(): void
    {
        // 测试空字符串和特殊字符
        $this->entity->setName('');
        $this->assertEquals('', $this->entity->getName());

        $specialName = '文件传输-测试_123@#$%';
        $this->entity->setName($specialName);
        $this->assertEquals($specialName, $this->entity->getName());

        $this->entity->setResult('错误: 文件不存在\n换行测试');
        $this->assertStringContainsString('错误', $this->entity->getResult() ?? '');
        $this->assertStringContainsString('\n', $this->entity->getResult() ?? '');
    }
}
