<?php

namespace ServerCommandBundle\Tests\MessageHandler;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\MessageHandler\RemoteCommandExecuteHandler;
use ServerCommandBundle\Service\RemoteCommandService;

class RemoteCommandExecuteHandlerTest extends TestCase
{
    private RemoteCommandService $remoteCommandService;
    private LoggerInterface $logger;
    private RemoteCommandExecuteHandler $handler;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->handler = new RemoteCommandExecuteHandler($this->remoteCommandService, $this->logger);
    }

    public function testInvokeWithValidCommand(): void
    {
        $commandId = '123';
        $command = $this->createMock(RemoteCommand::class);
        $message = new RemoteCommandExecuteMessage($commandId);

        // 设置模拟对象的行为
        $this->remoteCommandService->expects($this->once())
            ->method('findById')
            ->with($commandId)
            ->willReturn($command);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($command);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('处理远程命令执行消息'),
                $this->arrayHasKey('commandId')
            );

        // 执行处理器
        $this->handler->__invoke($message);
    }

    public function testInvokeWithNonExistentCommand(): void
    {
        $commandId = 'non-existent';
        $message = new RemoteCommandExecuteMessage($commandId);

        // 设置模拟对象的行为
        $this->remoteCommandService->expects($this->once())
            ->method('findById')
            ->with($commandId)
            ->willReturn(null);

        $this->remoteCommandService->expects($this->never())
            ->method('executeCommand');

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('处理远程命令执行消息'),
                $this->arrayHasKey('commandId')
            );

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('未找到指定的远程命令'),
                $this->arrayHasKey('commandId')
            );

        // 执行处理器
        $this->handler->__invoke($message);
    }

    public function testInvokeWithException(): void
    {
        $commandId = '123';
        $command = $this->createMock(RemoteCommand::class);
        $message = new RemoteCommandExecuteMessage($commandId);
        $exception = new \RuntimeException('测试异常');

        // 设置模拟对象的行为
        $this->remoteCommandService->expects($this->once())
            ->method('findById')
            ->with($commandId)
            ->willReturn($command);

        $this->remoteCommandService->expects($this->once())
            ->method('executeCommand')
            ->with($command)
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('处理远程命令执行消息'),
                $this->arrayHasKey('commandId')
            );

        // 确保异常被捕获而不是传播
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('测试异常');

        // 执行处理器
        $this->handler->__invoke($message);
    }
} 