<?php

namespace ServerCommandBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoteCommandServiceTest extends TestCase
{
    private RemoteCommandRepository|MockObject $repository;
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private MessageBusInterface|MockObject $messageBus;
    private RemoteCommandService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RemoteCommandRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $this->service = new RemoteCommandService(
            $this->repository,
            $this->entityManager,
            $this->logger,
            $this->messageBus
        );
    }

    public function testCreateCommand(): void
    {
        // 准备测试数据
        $node = $this->createMock(Node::class);
        $name = '测试命令';
        $command = 'ls -la';
        $workingDirectory = '/var/www';
        $useSudo = true;
        $timeout = 60;
        $tags = ['system', 'test'];

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(RemoteCommand::class));

        $this->entityManager->expects($this->once())
            ->method('flush');

        // 调用被测试方法
        $result = $this->service->createCommand(
            $node,
            $name,
            $command,
            $workingDirectory,
            $useSudo,
            $timeout,
            $tags
        );

        // 验证结果
        $this->assertInstanceOf(RemoteCommand::class, $result);
        $this->assertSame($node, $result->getNode());
        $this->assertSame($name, $result->getName());
        $this->assertSame($command, $result->getCommand());
        $this->assertSame($workingDirectory, $result->getWorkingDirectory());
        $this->assertSame($useSudo, $result->isUseSudo());
        $this->assertSame($timeout, $result->getTimeout());
        $this->assertSame($tags, $result->getTags());
        $this->assertSame(CommandStatus::PENDING, $result->getStatus());
    }

    public function testScheduleCommand(): void
    {
        // 准备测试数据
        $command = $this->createMock(RemoteCommand::class);
        $commandId = 123;
        
        $command->expects($this->once())
            ->method('getId')
            ->willReturn($commandId);

        // 设置消息总线的期望行为
        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function ($message) use ($commandId) {
                return $message instanceof RemoteCommandExecuteMessage
                    && $message->getCommandId() === (string)$commandId;
            }))
            ->willReturn(new Envelope(new RemoteCommandExecuteMessage((string)$commandId)));

        // 设置日志记录器的期望行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('命令已加入执行队列'),
                $this->arrayHasKey('command')
            );

        // 调用被测试方法
        $this->service->scheduleCommand($command);
    }

    public function testFindById(): void
    {
        // 准备测试数据
        $commandId = '123';
        $command = $this->createMock(RemoteCommand::class);

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('find')
            ->with($commandId)
            ->willReturn($command);

        // 调用被测试方法
        $result = $this->service->findById($commandId);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testFindPendingCommandsByNode(): void
    {
        // 准备测试数据
        $node = $this->createMock(Node::class);
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findPendingCommandsByNode')
            ->with($node)
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findPendingCommandsByNode($node);

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testFindAllPendingCommands(): void
    {
        // 准备测试数据
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findAllPendingCommands')
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findAllPendingCommands();

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testFindByTags(): void
    {
        // 准备测试数据
        $tags = ['system', 'test'];
        $commands = [
            $this->createMock(RemoteCommand::class),
            $this->createMock(RemoteCommand::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($commands);

        // 调用被测试方法
        $result = $this->service->findByTags($tags);

        // 验证结果
        $this->assertSame($commands, $result);
    }

    public function testCancelCommand(): void
    {
        // 准备测试数据
        $command = $this->createMock(RemoteCommand::class);

        // 设置命令的期望行为
        $command->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::PENDING);

        $command->expects($this->once())
            ->method('setStatus')
            ->with(CommandStatus::CANCELED);

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->once())
            ->method('flush');

        // 设置日志记录器的期望行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('命令已取消'),
                $this->arrayHasKey('command')
            );

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testCancelCommandWithNonPendingCommand(): void
    {
        // 准备测试数据
        $command = $this->createMock(RemoteCommand::class);

        // 设置命令的期望行为
        $command->expects($this->once())
            ->method('getStatus')
            ->willReturn(CommandStatus::RUNNING);

        $command->expects($this->never())
            ->method('setStatus');

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->never())
            ->method('flush');

        // 设置日志记录器的期望行为
        $this->logger->expects($this->never())
            ->method('info');

        // 调用被测试方法
        $result = $this->service->cancelCommand($command);

        // 验证结果
        $this->assertSame($command, $result);
    }

    public function testExecSshCommandWithSudoAndWorkingDirectory(): void
    {
        // 创建模拟的SSH2连接
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        // 创建模拟的节点
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('parallels');
        $node->method('getSshPassword')->willReturn('password123');
        
        // 设置期望的SSH命令执行
        $expectedCommand = "printf '%s\\n\\n' 'password123' | sudo -S bash -c 'cd /var/www && pwd'";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('/var/www');
        
        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, 'pwd', '/var/www', true, $node);
        
        // 验证结果
        $this->assertEquals('/var/www', $result);
    }

    public function testExecSshCommandWithSudoWithoutWorkingDirectory(): void
    {
        // 创建模拟的SSH2连接
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        // 创建模拟的节点
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('parallels');
        $node->method('getSshPassword')->willReturn('password123');
        
        // 设置期望的SSH命令执行
        $expectedCommand = "printf '%s\\n\\n' 'password123' | sudo -S systemctl restart nginx";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('nginx restarted successfully');
        
        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, 'systemctl restart nginx', null, true, $node);
        
        // 验证结果
        $this->assertEquals('nginx restarted successfully', $result);
    }

    public function testExecSshCommandWithoutSudo(): void
    {
        // 创建模拟的SSH2连接
        $ssh = $this->createMock(\phpseclib3\Net\SSH2::class);
        
        // 创建模拟的节点
        $node = $this->createMock(Node::class);
        
        // 设置期望的SSH命令执行
        $expectedCommand = "cd /var/www && ls -la";
        $ssh->expects($this->once())
            ->method('exec')
            ->with($expectedCommand)
            ->willReturn('total 0');
        
        // 调用被测试方法
        $result = $this->service->execSshCommand($ssh, 'ls -la', '/var/www', false, $node);
        
        // 验证结果
        $this->assertEquals('total 0', $result);
    }
} 