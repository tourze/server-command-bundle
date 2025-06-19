<?php

namespace ServerCommandBundle\Tests\Controller;

use PHPUnit\Framework\MockObject\MockObject;
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
    private RemoteCommandService|MockObject $remoteCommandService;
    private NodeRepository|MockObject $nodeRepository;
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

    public function testInvokeWithValidCommand(): void
    {
        // 准备测试数据
        $nodeId = '1';
        $command = 'ls -la';
        $workingDir = '/root';
        $useSudo = false;

        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);
        $request->request->set('workingDir', $workingDir);
        $request->request->set('useSudo', $useSudo);

        // 设置模拟对象的行为
        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->with(
                $node,
                $this->stringContains('终端命令: ls -la'),
                $command,
                $workingDir,
                $useSudo,
                30,
                ['terminal']
            )
            ->willReturn($remoteCommand);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($remoteCommand);

        // 设置返回值
        $remoteCommand->method('getResult')->willReturn('command output');
        $remoteCommand->method('getStatus')->willReturn(CommandStatus::COMPLETED);
        $remoteCommand->method('getExecutionTime')->willReturn(0.5);
        $remoteCommand->method('getId')->willReturn(123);

        // 执行测试
        $response = ($this->controller)($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('command output', $data['result']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(0.5, $data['executionTime']);
        $this->assertEquals(123, $data['commandId']);
    }

    public function testInvokeWithMissingParameters(): void
    {
        $request = new Request();
        // 故意不设置必要的参数

        $response = ($this->controller)($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点ID和命令不能为空', $data['error']);
    }

    public function testInvokeWithInvalidNode(): void
    {
        $request = new Request();
        $request->request->set('nodeId', '999');
        $request->request->set('command', 'ls -la');

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with('999')
            ->willReturn(null);

        $response = ($this->controller)($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }

    public function testInvokeWithException(): void
    {
        $nodeId = '1';
        $command = 'ls -la';

        $node = $this->createMock(Node::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->willThrowException(new \Exception('Test exception'));

        $response = ($this->controller)($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Test exception', $data['error']);
    }

    public function testInvokeWithSudoOption(): void
    {
        $nodeId = '1';
        $command = 'systemctl restart nginx';
        $workingDir = '/etc';
        $useSudo = true;

        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);
        $request->request->set('workingDir', $workingDir);
        $request->request->set('useSudo', 'true');

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->with(
                $node,
                $this->stringContains('终端命令:'),
                $command,
                $workingDir,
                $useSudo,
                30,
                ['terminal']
            )
            ->willReturn($remoteCommand);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($remoteCommand);

        $remoteCommand->method('getResult')->willReturn('Service restarted');
        $remoteCommand->method('getStatus')->willReturn(CommandStatus::COMPLETED);
        $remoteCommand->method('getExecutionTime')->willReturn(2.1);
        $remoteCommand->method('getId')->willReturn(456);

        $response = ($this->controller)($request);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('Service restarted', $data['result']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(2.1, $data['executionTime']);
        $this->assertEquals(456, $data['commandId']);
    }
}