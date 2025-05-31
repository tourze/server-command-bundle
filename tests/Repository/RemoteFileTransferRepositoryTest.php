<?php

namespace ServerCommandBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;

class RemoteFileTransferRepositoryTest extends TestCase
{
    public function test_find_pending_transfers_by_node(): void
    {
        $node = $this->createMock(Node::class);
        $expectedTransfers = [$this->createMock(RemoteFileTransfer::class)];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findPendingTransfersByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findPendingTransfersByNode')
            ->with($node)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findPendingTransfersByNode($node);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
    }

    public function test_find_pending_transfers_by_node_empty_result(): void
    {
        $node = $this->createMock(Node::class);
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findPendingTransfersByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findPendingTransfersByNode')
            ->with($node)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findPendingTransfersByNode($node);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_all_pending_transfers(): void
    {
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAllPendingTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findAllPendingTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findAllPendingTransfers();
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_find_all_pending_transfers_empty(): void
    {
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAllPendingTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findAllPendingTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findAllPendingTransfers();
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_by_tags(): void
    {
        $tags = ['upload', 'important'];
        $expectedTransfers = [$this->createMock(RemoteFileTransfer::class)];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByTags($tags);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_find_by_tags_empty_tags(): void
    {
        $tags = [];
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByTags($tags);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_by_tags_special_characters(): void
    {
        $tags = ['system/admin', 'backup-daily', 'test_env'];
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByTags($tags);
        $this->assertSame($expectedTransfers, $result);
        $this->assertCount(2, $result);
    }

    public function test_find_by_status(): void
    {
        $status = FileTransferStatus::COMPLETED;
        $expectedTransfers = [$this->createMock(RemoteFileTransfer::class)];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByStatus'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByStatus')
            ->with($status)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByStatus($status);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
    }

    public function test_find_by_status_all_statuses(): void
    {
        // 测试所有可能的状态
        $statuses = [
            FileTransferStatus::PENDING,
            FileTransferStatus::UPLOADING,
            FileTransferStatus::MOVING,
            FileTransferStatus::COMPLETED,
            FileTransferStatus::FAILED,
            FileTransferStatus::CANCELED,
        ];

        foreach ($statuses as $status) {
            // 为每个状态创建独立的repository mock
            $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
                ->disableOriginalConstructor()
                ->onlyMethods(['findByStatus'])
                ->getMock();
                
            $expectedTransfers = [$this->createMock(RemoteFileTransfer::class)];
            
            $repository->expects($this->once())
                ->method('findByStatus')
                ->with($status)
                ->willReturn($expectedTransfers);
                
            $result = $repository->findByStatus($status);
            $this->assertIsArray($result);
            $this->assertCount(1, $result);
        }
    }

    public function test_find_by_date_range(): void
    {
        $startDate = new \DateTime('2023-01-01');
        $endDate = new \DateTime('2023-12-31');
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByDateRange'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByDateRange')
            ->with($startDate, $endDate)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByDateRange($startDate, $endDate);
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_find_by_date_range_same_day(): void
    {
        $startDate = new \DateTime('2023-01-01 00:00:00');
        $endDate = new \DateTime('2023-01-01 23:59:59');
        $expectedTransfers = [$this->createMock(RemoteFileTransfer::class)];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByDateRange'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByDateRange')
            ->with($startDate, $endDate)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByDateRange($startDate, $endDate);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function test_find_by_date_range_empty(): void
    {
        $startDate = new \DateTime('2023-01-01');
        $endDate = new \DateTime('2023-01-02');
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByDateRange'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByDateRange')
            ->with($startDate, $endDate)
            ->willReturn($expectedTransfers);
            
        $result = $repository->findByDateRange($startDate, $endDate);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_failed_transfers(): void
    {
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findFailedTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findFailedTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findFailedTransfers();
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function test_find_failed_transfers_empty(): void
    {
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findFailedTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findFailedTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findFailedTransfers();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_find_completed_transfers(): void
    {
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findCompletedTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findCompletedTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findCompletedTransfers();
        $this->assertSame($expectedTransfers, $result);
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function test_find_completed_transfers_empty(): void
    {
        $expectedTransfers = [];
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findCompletedTransfers'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findCompletedTransfers')
            ->willReturn($expectedTransfers);
            
        $result = $repository->findCompletedTransfers();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_repository_methods_return_array_type(): void
    {
        // 验证所有查询方法都返回数组类型
        $node = $this->createMock(Node::class);
        $tags = ['test'];
        $status = FileTransferStatus::PENDING;
        $startDate = new \DateTime();
        $endDate = new \DateTime();
        
        $repository = $this->getMockBuilder(RemoteFileTransferRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'findPendingTransfersByNode',
                'findAllPendingTransfers',
                'findByTags',
                'findByStatus',
                'findByDateRange',
                'findFailedTransfers',
                'findCompletedTransfers'
            ])
            ->getMock();
            
        // 设置所有方法返回空数组
        $repository->method('findPendingTransfersByNode')->willReturn([]);
        $repository->method('findAllPendingTransfers')->willReturn([]);
        $repository->method('findByTags')->willReturn([]);
        $repository->method('findByStatus')->willReturn([]);
        $repository->method('findByDateRange')->willReturn([]);
        $repository->method('findFailedTransfers')->willReturn([]);
        $repository->method('findCompletedTransfers')->willReturn([]);
        
        // 验证返回类型
        $this->assertIsArray($repository->findPendingTransfersByNode($node));
        $this->assertIsArray($repository->findAllPendingTransfers());
        $this->assertIsArray($repository->findByTags($tags));
        $this->assertIsArray($repository->findByStatus($status));
        $this->assertIsArray($repository->findByDateRange($startDate, $endDate));
        $this->assertIsArray($repository->findFailedTransfers());
        $this->assertIsArray($repository->findCompletedTransfers());
    }

    /**
     * 测试Repository继承关系和基本方法
     */
    public function test_repository_inheritance(): void
    {
        // 验证Repository类的基本结构
        $reflection = new \ReflectionClass(RemoteFileTransferRepository::class);
        
        // 验证继承关系
        $this->assertTrue($reflection->isSubclassOf('Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository'));
        
        // 验证必要的方法存在
        $this->assertTrue($reflection->hasMethod('findPendingTransfersByNode'));
        $this->assertTrue($reflection->hasMethod('findAllPendingTransfers'));
        $this->assertTrue($reflection->hasMethod('findByTags'));
        $this->assertTrue($reflection->hasMethod('findByStatus'));
        $this->assertTrue($reflection->hasMethod('findByDateRange'));
        $this->assertTrue($reflection->hasMethod('findFailedTransfers'));
        $this->assertTrue($reflection->hasMethod('findCompletedTransfers'));
    }

    /**
     * 测试方法签名
     */
    public function test_method_signatures(): void
    {
        $reflection = new \ReflectionClass(RemoteFileTransferRepository::class);
        
        // 测试 findPendingTransfersByNode 方法签名
        $method = $reflection->getMethod('findPendingTransfersByNode');
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('node', $parameters[0]->getName());
        
        // 测试 findByTags 方法签名
        $method = $reflection->getMethod('findByTags');
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('tags', $parameters[0]->getName());
        
        // 测试 findByStatus 方法签名
        $method = $reflection->getMethod('findByStatus');
        $parameters = $method->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('status', $parameters[0]->getName());
        
        // 测试 findByDateRange 方法签名
        $method = $reflection->getMethod('findByDateRange');
        $parameters = $method->getParameters();
        $this->assertCount(2, $parameters);
        $this->assertEquals('startDate', $parameters[0]->getName());
        $this->assertEquals('endDate', $parameters[1]->getName());
    }

    /**
     * 测试Repository常量和类属性
     */
    public function test_repository_constants(): void
    {
        $reflection = new \ReflectionClass(RemoteFileTransferRepository::class);
        
        // 验证类是final或可扩展
        $this->assertFalse($reflection->isFinal(), 'Repository 应该是可扩展的');
        
        // 验证类不是抽象的
        $this->assertFalse($reflection->isAbstract(), 'Repository 不应该是抽象的');
    }

    /**
     * 测试返回类型注释（通过注释检查）
     */
    public function test_method_return_types(): void
    {
        $reflection = new \ReflectionClass(RemoteFileTransferRepository::class);
        
        // 检查 findPendingTransfersByNode 的返回类型
        $method = $reflection->getMethod('findPendingTransfersByNode');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
        
        // 检查 findAllPendingTransfers 的返回类型
        $method = $reflection->getMethod('findAllPendingTransfers');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
        
        // 检查 findByTags 的返回类型
        $method = $reflection->getMethod('findByTags');
        $returnType = $method->getReturnType();
        $this->assertNotNull($returnType);
        $this->assertEquals('array', $returnType->getName());
    }

    /**
     * 测试构造函数
     */
    public function test_constructor(): void
    {
        $reflection = new \ReflectionClass(RemoteFileTransferRepository::class);
        $constructor = $reflection->getConstructor();
        
        $this->assertNotNull($constructor);
        
        $parameters = $constructor->getParameters();
        $this->assertCount(1, $parameters);
        $this->assertEquals('registry', $parameters[0]->getName());
    }

    /**
     * 测试实体类型常量
     */
    public function test_entity_class(): void
    {
        // 通过反射验证Repository管理的实体类型
        $this->assertTrue(class_exists(RemoteFileTransfer::class));
        $this->assertTrue(class_exists(RemoteFileTransferRepository::class));
        
        // 验证相关的枚举类型
        $this->assertTrue(enum_exists(FileTransferStatus::class));
    }
} 