<?php

namespace ServerCommandBundle\Tests\MessageHandler;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\MessageHandler\RemoteCommandExecuteHandler;
use ServerNodeBundle\Entity\Node;
use Tourze\GBT2659\Alpha2Code as GBT_2659_2000;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteCommandExecuteHandler::class)]
#[RunTestsInSeparateProcesses]
final class RemoteCommandExecuteHandlerTest extends AbstractIntegrationTestCase
{
    private RemoteCommand $testCommand;

    protected function onSetUp(): void
    {
        $this->createTestData();
    }

    private function createTestData(): void
    {
        // 创建测试节点
        $node = new Node();
        $node->setName('测试节点');
        $node->setSshHost('192.168.1.100');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setValid(true);
        $node->setCountry(GBT_2659_2000::CN);
        $this->persistAndFlush($node);

        // 创建测试命令
        $this->testCommand = new RemoteCommand();
        $this->testCommand->setNode($node);
        $this->testCommand->setName('测试命令');
        $this->testCommand->setCommand('echo "Hello World"');
        $this->testCommand->setWorkingDirectory('/tmp');
        $this->testCommand->setUseSudo(false);
        $this->testCommand->setEnabled(true);
        $this->testCommand->setTimeout(300);
        $this->testCommand->setStatus(CommandStatus::PENDING);
        $this->testCommand->setTags(['test', 'demo']);
        $this->persistAndFlush($this->testCommand);
    }

    public function testInvokeWithValidCommand(): void
    {
        $commandId = (string) $this->testCommand->getId();
        $message = new RemoteCommandExecuteMessage($commandId);

        $handler = self::getService(RemoteCommandExecuteHandler::class);

        // 由于这是集成测试，我们不期望抛出异常（SSH连接失败是正常的）
        // 主要测试消息处理逻辑是否正确
        $handler->__invoke($message);

        // 验证命令状态被正确更新（从数据库重新加载验证）
        $updatedCommand = self::getEntityManager()->find(RemoteCommand::class, $this->testCommand->getId());
        $this->assertNotNull($updatedCommand);
        // 命令应该被处理过，即使SSH连接失败也会更新状态
        $this->assertNotEquals(CommandStatus::PENDING, $updatedCommand->getStatus());
    }

    public function testInvokeWithNonExistentCommand(): void
    {
        $commandId = '999999'; // 不存在的命令ID
        $message = new RemoteCommandExecuteMessage($commandId);

        $handler = self::getService(RemoteCommandExecuteHandler::class);

        // 处理不存在的命令不应该抛出异常，应该记录警告并返回
        $handler->__invoke($message);

        // 验证原来的测试命令状态没有被影响
        $originalCommand = self::getEntityManager()->find(RemoteCommand::class, $this->testCommand->getId());
        $this->assertNotNull($originalCommand);
        $this->assertEquals(CommandStatus::PENDING, $originalCommand->getStatus());
    }
}
