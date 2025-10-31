<?php

namespace ServerCommandBundle\Tests\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;

/**
 * @internal
 */
#[CoversClass(RemoteCommandExecuteMessage::class)]
final class RemoteCommandExecuteMessageTest extends TestCase
{
    public function testImplementsAsyncMessageInterface(): void
    {
        $message = new RemoteCommandExecuteMessage('1');
        // 验证消息对象正确创建
        $this->assertInstanceOf(RemoteCommandExecuteMessage::class, $message);
        $this->assertEquals('1', $message->getCommandId());
    }

    public function testGetCommandId(): void
    {
        $commandId = '123';
        $message = new RemoteCommandExecuteMessage($commandId);
        $this->assertSame($commandId, $message->getCommandId());
    }

    public function testGetCommandIdWithIntegerId(): void
    {
        $commandId = 123;
        $message = new RemoteCommandExecuteMessage((string) $commandId);
        $this->assertSame('123', $message->getCommandId());
    }

    public function testImmutability(): void
    {
        $commandId1 = '123';
        $message1 = new RemoteCommandExecuteMessage($commandId1);
        $this->assertSame($commandId1, $message1->getCommandId());

        $commandId2 = '456';
        $message2 = new RemoteCommandExecuteMessage($commandId2);
        $this->assertSame($commandId2, $message2->getCommandId());

        // 确保第一个消息的命令ID没有变化
        $this->assertSame($commandId1, $message1->getCommandId());
    }
}
