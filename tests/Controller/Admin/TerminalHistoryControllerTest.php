<?php

namespace ServerCommandBundle\Tests\Controller\Admin;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Controller\Admin\TerminalHistoryController;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;
use ServerNodeBundle\Repository\NodeRepository;

class TerminalHistoryControllerTest extends TestCase
{
    private RemoteCommandService|MockObject $remoteCommandService;
    private NodeRepository|MockObject $nodeRepository;
    private TerminalHistoryController $controller;

    public function testInvokeWithValidNode(): void
    {
        $nodeId = 1;
        $node = $this->createMock(Node::class);
        $repository = $this->createMock(\ServerCommandBundle\Repository\RemoteCommandRepository::class);

        $command = $this->createMock(RemoteCommand::class);
        $command->method('getId')->willReturn(1);
        $command->method('getCommand')->willReturn('ls -la');
        $command->method('getResult')->willReturn('file list');
        $command->method('getStatus')->willReturn(CommandStatus::COMPLETED);
        $command->method('getExecutedAt')->willReturn(new \DateTime('2023-01-01 12:00:00'));
        $command->method('getExecutionTime')->willReturn(0.5);
        $command->method('getWorkingDirectory')->willReturn('/root');

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, 20)
            ->willReturn([$command]);

        $response = ($this->controller)($nodeId);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(1, $data['history']);

        $historyItem = $data['history'][0];
        $this->assertEquals(1, $historyItem['id']);
        $this->assertEquals('ls -la', $historyItem['command']);
        $this->assertEquals('file list', $historyItem['result']);
        $this->assertEquals('completed', $historyItem['status']);
    }

    public function testInvokeWithInvalidNode(): void
    {
        $nodeId = 999;

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn(null);

        $response = ($this->controller)($nodeId);

        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }

    public function testInvokeWithEmptyHistory(): void
    {
        $nodeId = 1;
        $node = $this->createMock(Node::class);
        $repository = $this->createMock(\ServerCommandBundle\Repository\RemoteCommandRepository::class);

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('getRepository')
            ->willReturn($repository);

        $repository->expects($this->once())
            ->method('findTerminalCommandsByNode')
            ->with($node, 20)
            ->willReturn([]);

        $response = ($this->controller)($nodeId);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['history']);
    }

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->nodeRepository = $this->createMock(NodeRepository::class);

        $this->controller = new TerminalHistoryController(
            $this->remoteCommandService,
            $this->nodeRepository
        );
    }
}