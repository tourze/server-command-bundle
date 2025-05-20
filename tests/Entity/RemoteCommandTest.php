<?php

namespace ServerCommandBundle\Tests\Entity;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerNodeBundle\Entity\Node;

class RemoteCommandTest extends TestCase
{
    private RemoteCommand $command;
    private Node $node;

    protected function setUp(): void
    {
        $this->command = new RemoteCommand();
        $this->node = $this->createMock(Node::class);
    }

    public function testGetterAndSetterForId(): void
    {
        // ID是自动生成的，默认应为0或null
        $this->assertSame(0, $this->command->getId());
    }

    public function testGetterAndSetterForNode(): void
    {
        $this->command->setNode($this->node);
        $this->assertSame($this->node, $this->command->getNode());
    }

    public function testGetterAndSetterForName(): void
    {
        $name = '测试命令';
        $this->command->setName($name);
        $this->assertSame($name, $this->command->getName());
    }

    public function testGetterAndSetterForCommand(): void
    {
        $command = 'ls -la';
        $this->command->setCommand($command);
        $this->assertSame($command, $this->command->getCommand());
    }

    public function testGetterAndSetterForWorkingDirectory(): void
    {
        $workingDirectory = '/var/www';
        $this->command->setWorkingDirectory($workingDirectory);
        $this->assertSame($workingDirectory, $this->command->getWorkingDirectory());
    }

    public function testGetterAndSetterForUseSudo(): void
    {
        $this->command->setUseSudo(true);
        $this->assertTrue($this->command->isUseSudo());

        $this->command->setUseSudo(false);
        $this->assertFalse($this->command->isUseSudo());
    }

    public function testGetterAndSetterForEnabled(): void
    {
        $this->command->setEnabled(true);
        $this->assertTrue($this->command->isEnabled());

        $this->command->setEnabled(false);
        $this->assertFalse($this->command->isEnabled());
    }

    public function testGetterAndSetterForResult(): void
    {
        $result = 'command output';
        $this->command->setResult($result);
        $this->assertSame($result, $this->command->getResult());
    }

    public function testGetterAndSetterForTimeout(): void
    {
        $timeout = 120;
        $this->command->setTimeout($timeout);
        $this->assertSame($timeout, $this->command->getTimeout());
    }

    public function testGetterAndSetterForStatus(): void
    {
        $this->command->setStatus(CommandStatus::RUNNING);
        $this->assertSame(CommandStatus::RUNNING, $this->command->getStatus());

        $this->command->setStatus(CommandStatus::COMPLETED);
        $this->assertSame(CommandStatus::COMPLETED, $this->command->getStatus());
    }

    public function testGetterAndSetterForExecutedAt(): void
    {
        $executedAt = new \DateTime();
        $this->command->setExecutedAt($executedAt);
        $this->assertSame($executedAt, $this->command->getExecutedAt());
    }

    public function testGetterAndSetterForExecutionTime(): void
    {
        $executionTime = 5.5;
        $this->command->setExecutionTime($executionTime);
        $this->assertSame($executionTime, $this->command->getExecutionTime());
    }

    public function testGetterAndSetterForTags(): void
    {
        $tags = ['system', 'maintenance'];
        $this->command->setTags($tags);
        $this->assertSame($tags, $this->command->getTags());
    }

    public function testGetterAndSetterForCreatedBy(): void
    {
        $createdBy = 'admin';
        $this->command->setCreatedBy($createdBy);
        $this->assertSame($createdBy, $this->command->getCreatedBy());
    }

    public function testGetterAndSetterForUpdatedBy(): void
    {
        $updatedBy = 'admin';
        $this->command->setUpdatedBy($updatedBy);
        $this->assertSame($updatedBy, $this->command->getUpdatedBy());
    }

    public function testGetterAndSetterForCreateTime(): void
    {
        $createTime = new \DateTime();
        $this->command->setCreateTime($createTime);
        $this->assertSame($createTime, $this->command->getCreateTime());
    }

    public function testGetterAndSetterForUpdateTime(): void
    {
        $updateTime = new \DateTime();
        $this->command->setUpdateTime($updateTime);
        $this->assertSame($updateTime, $this->command->getUpdateTime());
    }

    public function testToStringReturnsName(): void
    {
        $name = '测试命令';
        $this->command->setName($name);
        $this->assertSame($name, (string) $this->command);
    }
} 