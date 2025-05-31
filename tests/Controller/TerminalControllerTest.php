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

    public function testExecuteWithValidCommand(): void
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

    public function testExecuteWithSudoParameter(): void
    {
        // 准备测试数据
        $nodeId = '1';
        $command = 'systemctl restart nginx';
        $workingDir = '/root';
        $useSudo = true;

        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);
        $request->request->set('workingDir', $workingDir);
        $request->request->set('useSudo', 'true');

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
            ->willReturn('nginx restarted successfully');

        $remoteCommand->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::COMPLETED);

        $remoteCommand->expects($this->once())
            ->method('getExecutionTime')
            ->willReturn(2.5);

        $remoteCommand->expects($this->once())
            ->method('getId')
            ->willReturn(456);

        // 执行测试
        $response = $this->controller->execute($request);

        // 验证结果
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('nginx restarted successfully', $data['result']);
        $this->assertEquals('completed', $data['status']);
        $this->assertEquals(2.5, $data['executionTime']);
        $this->assertEquals(456, $data['commandId']);
    }

    /**
     * 测试错误处理场景
     */
    public function testExecuteWithServiceException(): void
    {
        // 测试服务层抛出异常的情况
        $nodeId = '1';
        $command = 'ls';
        
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
            ->willThrowException(new \Exception('SSH连接失败'));

        $response = $this->controller->execute($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('SSH连接失败', $data['error']);
    }

    public function testExecuteWithEmptyCommand(): void
    {
        // 测试空命令的情况
        $request = new Request();
        $request->request->set('nodeId', '1');
        $request->request->set('command', '');

        $response = $this->controller->execute($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点ID和命令不能为空', $data['error']);
    }

    public function testExecuteWithWhitespaceOnlyCommand(): void
    {
        // 测试只有空格的命令
        $request = new Request();
        $request->request->set('nodeId', '1');
        $request->request->set('command', '   ');

        // 由于控制器的验证逻辑是 !$command，空格字符串不会被检测为空
        // 所以会继续执行到节点查找，我们需要mock这个调用
        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with('1')
            ->willReturn(null);

        $response = $this->controller->execute($request);
        
        // 实际上会返回404因为节点不存在，而不是400
        // 这暴露了控制器验证逻辑的不足：应该 trim 命令后再检查
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }

    public function testExecuteWithNullCommand(): void
    {
        // 测试null命令的情况
        $request = new Request();
        $request->request->set('nodeId', '1');
        $request->request->set('command', null);

        $response = $this->controller->execute($request);
        
        $this->assertEquals(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点ID和命令不能为空', $data['error']);
    }

    public function testExecuteWithCommandExecutionException(): void
    {
        // 测试命令执行时抛出异常
        $nodeId = '1';
        $command = 'invalid-command';
        
        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);
        
        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->willReturn($remoteCommand);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($remoteCommand)
            ->willThrowException(new \Exception('命令执行超时'));

        $response = $this->controller->execute($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('命令执行超时', $data['error']);
    }

    public function testExecuteWithNetworkException(): void
    {
        // 测试网络异常
        $nodeId = '1';
        $command = 'ls';
        
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
            ->willThrowException(new \Exception('Network unreachable'));

        $response = $this->controller->execute($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Network unreachable', $data['error']);
    }

    public function testExecuteWithInvalidNodeId(): void
    {
        // 测试无效的节点ID格式
        $request = new Request();
        $request->request->set('nodeId', 'invalid');
        $request->request->set('command', 'ls');

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with('invalid')
            ->willReturn(null);

        $response = $this->controller->execute($request);
        
        $this->assertEquals(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('节点不存在', $data['error']);
    }

    public function testExecuteWithCreateCommandException(): void
    {
        // 测试创建命令时的异常
        $nodeId = '1';
        $command = 'ls';
        
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
            ->willThrowException(new \RuntimeException('数据库连接失败'));

        $response = $this->controller->execute($request);
        
        $this->assertEquals(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('数据库连接失败', $data['error']);
    }

    public function testHistoryWithEmptyHistory(): void
    {
        // 测试节点没有历史命令的情况
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

        $response = $this->controller->history($nodeId);
        
        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertCount(0, $data['history']);
        $this->assertIsArray($data['history']);
    }

    public function testHistoryWithRepositoryException(): void
    {
        // 测试Repository抛出异常时控制器的处理
        $nodeId = 1;
        $node = $this->createMock(Node::class);
        
        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('getRepository')
            ->willThrowException(new \Exception('数据库连接失败'));

        // 由于控制器没有try-catch，异常会向上抛出
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('数据库连接失败');

        $this->controller->history($nodeId);
    }

    public function testExecuteWithDefaultWorkingDirectory(): void
    {
        // 测试默认工作目录设置
        $nodeId = '1';
        $command = 'pwd';
        
        $node = $this->createMock(Node::class);
        $remoteCommand = $this->createMock(RemoteCommand::class);

        $request = new Request();
        $request->request->set('nodeId', $nodeId);
        $request->request->set('command', $command);
        // 不设置workingDir，应该使用默认值 '/root'

        $this->nodeRepository->expects($this->once())
            ->method('find')
            ->with($nodeId)
            ->willReturn($node);

        $this->remoteCommandService->expects($this->once())
            ->method('createCommand')
            ->with(
                $node,
                $this->stringContains('终端命令'),
                $command,
                '/root', // 验证默认工作目录
                false,   // 默认不使用sudo
                30,      // 默认超时
                ['terminal'] // 默认标签
            )
            ->willReturn($remoteCommand);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($remoteCommand);

        $remoteCommand->expects($this->once())
            ->method('getResult')
            ->willReturn('/root');

        $remoteCommand->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::COMPLETED);

        $remoteCommand->expects($this->once())
            ->method('getExecutionTime')
            ->willReturn(0.1);

        $remoteCommand->expects($this->once())
            ->method('getId')
            ->willReturn(789);

        $response = $this->controller->execute($request);

        $this->assertEquals(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals('/root', $data['result']);
    }

    public function testExecuteWithBooleanSudoValues(): void
    {
        // 测试不同的sudo布尔值格式
        $testCases = [
            ['true', true],
            ['false', false],
            ['1', true],
            ['0', false],
            [true, true],
            [false, false],
        ];

        foreach ($testCases as [$sudoValue, $expectedSudo]) {
            $nodeId = '1';
            $command = 'ls';
            
            $node = $this->createMock(Node::class);
            $remoteCommand = $this->createMock(RemoteCommand::class);

            $request = new Request();
            $request->request->set('nodeId', $nodeId);
            $request->request->set('command', $command);
            $request->request->set('useSudo', $sudoValue);

            $this->nodeRepository->expects($this->once())
                ->method('find')
                ->with($nodeId)
                ->willReturn($node);

            $this->remoteCommandService->expects($this->once())
                ->method('createCommand')
                ->with(
                    $node,
                    $this->anything(),
                    $command,
                    '/root',
                    $expectedSudo, // 验证sudo参数正确转换
                    30,
                    ['terminal']
                )
                ->willReturn($remoteCommand);

            $this->remoteCommandService->expects($this->once())
                ->method('executeCommand')
                ->with($remoteCommand);

            $remoteCommand->method('getResult')->willReturn('test');
            $remoteCommand->method('getStatus')->willReturn(CommandStatus::COMPLETED);
            $remoteCommand->method('getExecutionTime')->willReturn(0.1);
            $remoteCommand->method('getId')->willReturn(123);

            $response = $this->controller->execute($request);
            $this->assertEquals(200, $response->getStatusCode());
            
            // 重置模拟对象以供下次测试使用
            $this->setUp();
        }
    }
} 