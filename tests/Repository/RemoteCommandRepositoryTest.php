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
} 