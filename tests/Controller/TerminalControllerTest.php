<?php

namespace ServerCommandBundle\Tests\Controller;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Controller\Admin\TerminalController;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Component\HttpFoundation\Request;
use Twig\Environment;

class TerminalControllerTest extends TestCase
{
    private RemoteCommandService $remoteCommandService;
    private NodeRepository $nodeRepository;
    private TerminalController $controller;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->nodeRepository = $this->createMock(NodeRepository::class);
        
        $this->controller = new TerminalController(
            $this->remoteCommandService,
            $this->nodeRepository
        );

        // Mock twig environment
        $twig = $this->createMock(Environment::class);
        $this->controller->setContainer($this->createMock(\Symfony\Component\DependencyInjection\ContainerInterface::class));
    }

    public function testExecuteWithValidCommand(): void
    {
        // 准备测试数据
        $nodeId = '1';
        $command = 'ls -la';
        $workingDir = '/root';

        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);
        $request->request->set('workingDir', $workingDir);

        // 设置模拟对象的行为
        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->willReturn($remoteCommand);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($remoteCommand);

        $remoteCommand->expects($this->once())
            ->method('getResult')
            ->willReturn('command output');

        $remoteCommand->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::COMPLETED);

        $remoteCommand->expects($this->once())
            ->method('getExecutionTime')
            ->willReturn(1.23);

        $remoteCommand->expects($this->once())
            ->method('getId')
            ->willReturn(123);

        // 执行测试
        $response = $this->controller->execute($request);

        // 验证结果
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('command output', $data['result']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(1.23, $data['executionTime']);
        $this->assertEquals(123, $data['commandId']);
    }

    public function testExecuteWithMissingParameters(): void
    {
        $request = new Request();
        
        $response = $this->controller->execute($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点ID和命令不能为空', $data['error']);
    }

    public function testExecuteWithNonExistentNode(): void
    {
        $request = new Request();
        $request->request->set('nodeId', '999');
        $request->request->set('command', 'ls');

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $response = $this->controller->execute($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }

    public function testHistoryWithValidNode(): void
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

        $response = $this->controller->history($nodeId);
        
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

    public function testHistoryWithNonExistentNode(): void
    {
        $nodeId = 999;

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn(null);

        $response = $this->controller->history($nodeId);
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }
} 