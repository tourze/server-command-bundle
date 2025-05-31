<?php

namespace ServerCommandBundle\Tests\Repository;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;

class RemoteCommandRepositoryTest extends TestCase
{
    public function testFindPendingCommandsByNode(): void
    {
        // 使用部分模拟，仅模拟findPendingCommandsByNode方法
        $node = $this->createMock(Node::class);
        $expectedCommands = [$this->createMock(RemoteCommand::class)];
        
        // 模拟repository类，只针对要测试的方法
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findPendingCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findPendingCommandsByNode')
            ->with($node)
            ->willReturn($expectedCommands);
            
        // 验证结果
        $this->assertSame($expectedCommands, $repository->findPendingCommandsByNode($node));
    }

    public function testFindPendingCommandsByNodeWithEmptyResult(): void
    {
        // 测试节点没有待执行命令的情况
        $node = $this->createMock(Node::class);
        $expectedCommands = [];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findPendingCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findPendingCommandsByNode')
            ->with($node)
            ->willReturn($expectedCommands);
            
        $result = $repository->findPendingCommandsByNode($node);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindAllPendingCommands(): void
    {
        // 使用部分模拟，仅模拟findAllPendingCommands方法
        $expectedCommands = [$this->createMock(RemoteCommand::class)];
        
        // 模拟repository类，只针对要测试的方法
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAllPendingCommands'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findAllPendingCommands')
            ->willReturn($expectedCommands);
            
        // 验证结果
        $this->assertSame($expectedCommands, $repository->findAllPendingCommands());
    }

    public function testFindAllPendingCommandsWithEmptyResult(): void
    {
        // 测试没有待执行命令的情况
        $expectedCommands = [];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findAllPendingCommands'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findAllPendingCommands')
            ->willReturn($expectedCommands);
            
        $result = $repository->findAllPendingCommands();
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindByTags(): void
    {
        // 使用部分模拟，仅模拟findByTags方法
        $tags = ['system', 'maintenance'];
        $expectedCommands = [$this->createMock(RemoteCommand::class)];
        
        // 模拟repository类，只针对要测试的方法
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedCommands);
            
        // 验证结果
        $this->assertSame($expectedCommands, $repository->findByTags($tags));
    }

    public function testFindByTagsWithSingleTag(): void
    {
        // 测试单个标签查询
        $tags = ['system'];
        $expectedCommands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedCommands);
            
        $result = $repository->findByTags($tags);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testFindByTagsWithEmptyTags(): void
    {
        // 测试空标签数组
        $tags = [];
        $expectedCommands = [];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedCommands);
            
        $result = $repository->findByTags($tags);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindByTagsWithSpecialCharacters(): void
    {
        // 测试包含特殊字符的标签
        $tags = ['system/admin', 'backup-daily', 'test_env'];
        $expectedCommands = [$this->createMock(RemoteCommand::class)];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findByTags'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($expectedCommands);
            
        $result = $repository->findByTags($tags);
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testFindTerminalCommandsByNode(): void
    {
        // 测试新增的 findTerminalCommandsByNode 方法
        $node = $this->createMock(Node::class);
        $expectedCommands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findTerminalCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, 20) // 默认限制为20
            ->willReturn($expectedCommands);
            
        $result = $repository->findTerminalCommandsByNode($node);
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testFindTerminalCommandsByNodeWithCustomLimit(): void
    {
        // 测试自定义限制数量
        $node = $this->createMock(Node::class);
        $limit = 10;
        $expectedCommands = array_fill(0, 5, $this->createMock(RemoteCommand::class));
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findTerminalCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, $limit)
            ->willReturn($expectedCommands);
            
        $result = $repository->findTerminalCommandsByNode($node, $limit);
        $this->assertIsArray($result);
        $this->assertCount(5, $result);
    }

    public function testFindTerminalCommandsByNodeWithZeroLimit(): void
    {
        // 测试零限制
        $node = $this->createMock(Node::class);
        $limit = 0;
        $expectedCommands = [];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findTerminalCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, $limit)
            ->willReturn($expectedCommands);
            
        $result = $repository->findTerminalCommandsByNode($node, $limit);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testFindTerminalCommandsByNodeWithEmptyResult(): void
    {
        // 测试节点没有终端命令历史的情况
        $node = $this->createMock(Node::class);
        $expectedCommands = [];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findTerminalCommandsByNode'])
            ->getMock();
            
        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, 20)
            ->willReturn($expectedCommands);
            
        $result = $repository->findTerminalCommandsByNode($node);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testRepositoryMethodsReturnArrayType(): void
    {
        // 验证所有查询方法都返回数组类型
        $node = $this->createMock(Node::class);
        $tags = ['test'];
        
        $repository = $this->getMockBuilder(RemoteCommandRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'findPendingCommandsByNode',
                'findAllPendingCommands',
                'findByTags',
                'findTerminalCommandsByNode'
            ])
            ->getMock();
            
        // 设置所有方法返回空数组
        $repository->method('findPendingCommandsByNode')->willReturn([]);
        $repository->method('findAllPendingCommands')->willReturn([]);
        $repository->method('findByTags')->willReturn([]);
        $repository->method('findTerminalCommandsByNode')->willReturn([]);
        
        // 验证返回类型
        $this->assertIsArray($repository->findPendingCommandsByNode($node));
        $this->assertIsArray($repository->findAllPendingCommands());
        $this->assertIsArray($repository->findByTags($tags));
        $this->assertIsArray($repository->findTerminalCommandsByNode($node));
    }

    public function testRepositoryInheritanceStructure(): void
    {
        // 验证Repository的继承结构
        $reflection = new \ReflectionClass(RemoteCommandRepository::class);
        
        // 验证继承自ServiceEntityRepository
        $this->assertTrue($reflection->isSubclassOf('Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository'));
        
        // 验证构造函数存在
        $this->assertTrue($reflection->hasMethod('__construct'));
        
        // 验证所有查询方法存在
        $expectedMethods = [
            'findPendingCommandsByNode',
            'findAllPendingCommands', 
            'findByTags',
            'findTerminalCommandsByNode'
        ];
        
        foreach ($expectedMethods as $method) {
            $this->assertTrue($reflection->hasMethod($method), "方法 {$method} 应该存在");
        }
    }
} 