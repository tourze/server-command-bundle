<?php

namespace ServerCommandBundle\Tests\Integration\Command;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Command\RemoteCommandExecuteCommand;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class RemoteCommandExecuteCommandTest extends TestCase
{
    private RemoteCommandExecuteCommand $command;
    private RemoteCommandService $remoteCommandService;
    private NodeRepository $nodeRepository;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->nodeRepository = $this->createMock(NodeRepository::class);
        $this->command = new RemoteCommandExecuteCommand(
            $this->remoteCommandService,
            $this->nodeRepository
        );
    }

    public function testCommandConfiguration(): void
    {
        $this->assertEquals(RemoteCommandExecuteCommand::NAME, $this->command->getName());
        $this->assertStringContainsString('执行远程命令', $this->command->getDescription());
    }

    public function testCommandHasRequiredArguments(): void
    {
        $definition = $this->command->getDefinition();
        $this->assertTrue($definition->hasArgument('command-id'));
    }

    public function testCommandExecution(): void
    {
        $application = new Application();
        $application->add($this->command);

        $command = $application->find(RemoteCommandExecuteCommand::NAME);
        $commandTester = new CommandTester($command);

        // Test with no arguments should not throw exception, but show error
        $commandTester->execute([]);
        
        $this->assertEquals(2, $commandTester->getStatusCode()); // Command::INVALID
    }
}