<?php

namespace ServerCommandBundle\Tests\Entity;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerNodeBundle\Entity\Node;

class RemoteFileTransferTest extends TestCase
{
    private RemoteFileTransfer $entity;
    private Node&MockObject $node;

    protected function setUp(): void
    {
        $this->entity = new RemoteFileTransfer();
        $this->node = $this->createMock(Node::class);
        $this->node->method('getName')->willReturn('test-node');
    }

    public function test_getter_setter_for_id(): void
    {
        // ID 是自动生成的，默认为 0
        $this->assertEquals(0, $this->entity->getId());
    }

    public function test_getter_setter_for_node(): void
    {
        $node = $this->node;
        $result = $this->entity->setNode($node);
        
        $this->assertSame($this->entity, $result); // 测试链式调用
        $this->assertSame($this->node, $this->entity->getNode());
    }

    public function test_getter_setter_for_name(): void
    {
        $name = '上传配置文件';
        $result = $this->entity->setName($name);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($name, $this->entity->getName());
    }

    public function test_getter_setter_for_local_path(): void
    {
        $path = '/local/path/file.txt';
        $result = $this->entity->setLocalPath($path);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($path, $this->entity->getLocalPath());
    }

    public function test_getter_setter_for_remote_path(): void
    {
        $path = '/remote/path/file.txt';
        $result = $this->entity->setRemotePath($path);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($path, $this->entity->getRemotePath());
    }

    public function test_getter_setter_for_temp_path(): void
    {
        $this->assertNull($this->entity->getTempPath()); // 默认为空
        
        $tempPath = '/tmp/upload_123456.txt';
        $result = $this->entity->setTempPath($tempPath);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($tempPath, $this->entity->getTempPath());
    }

    public function test_getter_setter_for_file_size(): void
    {
        $this->assertNull($this->entity->getFileSize()); // 默认为空
        
        $fileSize = 1024;
        $result = $this->entity->setFileSize($fileSize);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($fileSize, $this->entity->getFileSize());
    }

    public function test_getter_setter_for_use_sudo(): void
    {
        $this->assertFalse($this->entity->isUseSudo()); // 默认为 false
        
        $result = $this->entity->setUseSudo(true);
        
        $this->assertSame($this->entity, $result);
        $this->assertTrue($this->entity->isUseSudo());
        
        $this->entity->setUseSudo(false);
        $this->assertFalse($this->entity->isUseSudo());
    }

    public function test_getter_setter_for_enabled(): void
    {
        $this->assertTrue($this->entity->isEnabled()); // 默认为 true
        
        $result = $this->entity->setEnabled(false);
        
        $this->assertSame($this->entity, $result);
        $this->assertFalse($this->entity->isEnabled());
        
        $this->entity->setEnabled(true);
        $this->assertTrue($this->entity->isEnabled());
    }

    public function test_getter_setter_for_result(): void
    {
        $this->assertNull($this->entity->getResult()); // 默认为空
        
        $result = '传输成功';
        $setResult = $this->entity->setResult($result);
        
        $this->assertSame($this->entity, $setResult);
        $this->assertEquals($result, $this->entity->getResult());
    }

    public function test_getter_setter_for_timeout(): void
    {
        $this->assertEquals(300, $this->entity->getTimeout()); // 默认 300 秒
        
        $timeout = 600;
        $result = $this->entity->setTimeout($timeout);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($timeout, $this->entity->getTimeout());
    }

    public function test_getter_setter_for_status(): void
    {
        $this->assertEquals(FileTransferStatus::PENDING, $this->entity->getStatus()); // 默认为 PENDING
        
        $status = FileTransferStatus::COMPLETED;
        $result = $this->entity->setStatus($status);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($status, $this->entity->getStatus());
    }

    public function test_getter_setter_for_started_at(): void
    {
        $this->assertNull($this->entity->getStartedAt()); // 默认为空
        
        $startedAt = new \DateTime();
        $result = $this->entity->setStartedAt($startedAt);
        
        $this->assertSame($this->entity, $result);
        $this->assertSame($startedAt, $this->entity->getStartedAt());
    }

    public function test_getter_setter_for_completed_at(): void
    {
        $this->assertNull($this->entity->getCompletedAt()); // 默认为空
        
        $completedAt = new \DateTime();
        $result = $this->entity->setCompletedAt($completedAt);
        
        $this->assertSame($this->entity, $result);
        $this->assertSame($completedAt, $this->entity->getCompletedAt());
    }

    public function test_getter_setter_for_transfer_time(): void
    {
        $this->assertNull($this->entity->getTransferTime()); // 默认为空
        
        $transferTime = 15.5;
        $result = $this->entity->setTransferTime($transferTime);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($transferTime, $this->entity->getTransferTime());
    }

    public function test_getter_setter_for_tags(): void
    {
        $this->assertNull($this->entity->getTags()); // 默认为空
        
        $tags = ['deployment', 'config'];
        $result = $this->entity->setTags($tags);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($tags, $this->entity->getTags());
    }

    public function test_getter_setter_for_created_by(): void
    {
        $this->assertNull($this->entity->getCreatedBy()); // 默认为空
        
        $createdBy = 'admin';
        $result = $this->entity->setCreatedBy($createdBy);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($createdBy, $this->entity->getCreatedBy());
    }

    public function test_getter_setter_for_updated_by(): void
    {
        $this->assertNull($this->entity->getUpdatedBy()); // 默认为空
        
        $updatedBy = 'system';
        $result = $this->entity->setUpdatedBy($updatedBy);
        
        $this->assertSame($this->entity, $result);
        $this->assertEquals($updatedBy, $this->entity->getUpdatedBy());
    }

    public function test_getter_setter_for_create_time(): void
    {
        $this->assertNull($this->entity->getCreateTime()); // 默认为空
        
        $createTime = new \DateTimeImmutable();
        $result = $this->entity->setCreateTime($createTime);
        
        $this->assertSame($this->entity, $result);
        $this->assertSame($createTime, $this->entity->getCreateTime());
    }

    public function test_getter_setter_for_update_time(): void
    {
        $this->assertNull($this->entity->getUpdateTime()); // 默认为空
        
        $updateTime = new \DateTimeImmutable();
        $result = $this->entity->setUpdateTime($updateTime);
        
        $this->assertSame($this->entity, $result);
        $this->assertSame($updateTime, $this->entity->getUpdateTime());
    }

    public function test_to_string_returns_formatted_description(): void
    {
        $this->entity->setName('上传配置文件');
        $node = $this->node;
        $this->entity->setNode($node);
        $this->entity->setRemotePath('/etc/app/config.yml');
        
        $expected = '上传配置文件 -> test-node:/etc/app/config.yml';
        $this->assertEquals($expected, (string) $this->entity);
    }

    public function test_to_string_with_different_node_name(): void
    {
        // 创建一个不同名称的node
        $differentNode = $this->createMock(Node::class);
        $differentNode->method('getName')->willReturn('production-server');
        
        $this->entity->setName('部署脚本');
        $node = $differentNode;
        $this->entity->setNode($node);
        $this->entity->setRemotePath('/opt/app/deploy.sh');
        
        $expected = '部署脚本 -> production-server:/opt/app/deploy.sh';
        $this->assertEquals($expected, (string) $this->entity);
    }

    public function test_default_values(): void
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

    public function test_boolean_field_with_null_values(): void
    {
        // 测试布尔字段接受 null 值
        $this->entity->setUseSudo(null);
        $this->assertNull($this->entity->isUseSudo());
        
        $this->entity->setEnabled(null);
        $this->assertNull($this->entity->isEnabled());
    }

    public function test_large_file_size(): void
    {
        // 测试大文件尺寸（使用 BIGINT）
        $largeSize = 9223372036854775807; // PHP_INT_MAX
        $this->entity->setFileSize($largeSize);
        $this->assertEquals($largeSize, $this->entity->getFileSize());
    }

    public function test_long_paths(): void
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

    public function test_edge_case_timeout_values(): void
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

    public function test_empty_and_special_character_strings(): void
    {
        // 测试空字符串和特殊字符
        $this->entity->setName('');
        $this->assertEquals('', $this->entity->getName());
        
        $specialName = '文件传输-测试_123@#$%';
        $this->entity->setName($specialName);
        $this->assertEquals($specialName, $this->entity->getName());
        
        $this->entity->setResult('错误: 文件不存在\n换行测试');
        $this->assertStringContainsString('错误', $this->entity->getResult());
        $this->assertStringContainsString('\n', $this->entity->getResult());
    }
}
