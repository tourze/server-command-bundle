<?php

namespace ServerCommandBundle\Tests\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Command\RemoteCommandExecuteCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Tourze\PHPUnitSymfonyKernelTest\AbstractCommandTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteCommandExecuteCommand::class)]
#[RunTestsInSeparateProcesses]
final class RemoteCommandExecuteCommandTest extends AbstractCommandTestCase
{
    private CommandTester $commandTester;

    protected function onSetUp(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $this->commandTester = new CommandTester($command);
    }

    protected function getCommandTester(): CommandTester
    {
        return $this->commandTester;
    }

    public function testCommandConfiguration(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $this->assertEquals(RemoteCommandExecuteCommand::NAME, $command->getName());
        $this->assertStringContainsString('执行远程命令', $command->getDescription());
    }

    public function testCommandHasRequiredArguments(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();
        $this->assertTrue($definition->hasArgument('command-id'));
    }

    public function testCommandExecution(): void
    {
        // Test with no arguments should not throw exception, but show error
        $this->commandTester->execute([]);

        $this->assertEquals(2, $this->commandTester->getStatusCode()); // Command::INVALID
    }

    public function testArgumentCommandId(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasArgument('command-id'));
        $argument = $definition->getArgument('command-id');
        $this->assertFalse($argument->isRequired());
        $this->assertEquals('命令ID', $argument->getDescription());
    }

    public function testOptionNodeId(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('node-id'));
        $option = $definition->getOption('node-id');
        $this->assertTrue($option->isValueRequired());
        $this->assertEquals('节点ID', $option->getDescription());
    }

    public function testOptionName(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('name'));
        $option = $definition->getOption('name');
        $this->assertTrue($option->isValueRequired());
        $this->assertEquals('命令名称', $option->getDescription());
    }

    public function testOptionCommand(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('command'));
        $option = $definition->getOption('command');
        $this->assertTrue($option->isValueRequired());
        $this->assertEquals('命令内容', $option->getDescription());
    }

    public function testOptionWorkingDir(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('working-dir'));
        $option = $definition->getOption('working-dir');
        $this->assertTrue($option->isValueOptional());
        $this->assertEquals('工作目录', $option->getDescription());
    }

    public function testOptionSudo(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('sudo'));
        $option = $definition->getOption('sudo');
        $this->assertFalse($option->acceptValue());
        $this->assertEquals('是否使用sudo执行', $option->getDescription());
    }

    public function testOptionTimeout(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('timeout'));
        $option = $definition->getOption('timeout');
        $this->assertTrue($option->isValueOptional());
        $this->assertEquals('超时时间(秒)', $option->getDescription());
        $this->assertEquals(300, $option->getDefault());
    }

    public function testOptionExecuteAllPending(): void
    {
        $command = self::getService(RemoteCommandExecuteCommand::class);
        $definition = $command->getDefinition();

        $this->assertTrue($definition->hasOption('execute-all-pending'));
        $option = $definition->getOption('execute-all-pending');
        $this->assertFalse($option->acceptValue());
        $this->assertEquals('执行所有待执行的命令', $option->getDescription());
    }
}
