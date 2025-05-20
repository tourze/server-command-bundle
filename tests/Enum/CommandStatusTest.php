<?php

namespace ServerCommandBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Enum\CommandStatus;

class CommandStatusTest extends TestCase
{
    public function testEnumValues(): void
    {
        $this->assertSame('pending', CommandStatus::PENDING->value);
        $this->assertSame('running', CommandStatus::RUNNING->value);
        $this->assertSame('completed', CommandStatus::COMPLETED->value);
        $this->assertSame('failed', CommandStatus::FAILED->value);
        $this->assertSame('timeout', CommandStatus::TIMEOUT->value);
        $this->assertSame('canceled', CommandStatus::CANCELED->value);
    }

    public function testEnumCases(): void
    {
        $cases = CommandStatus::cases();
        $this->assertCount(6, $cases);
        $this->assertContains(CommandStatus::PENDING, $cases);
        $this->assertContains(CommandStatus::RUNNING, $cases);
        $this->assertContains(CommandStatus::COMPLETED, $cases);
        $this->assertContains(CommandStatus::FAILED, $cases);
        $this->assertContains(CommandStatus::TIMEOUT, $cases);
        $this->assertContains(CommandStatus::CANCELED, $cases);
    }

    public function testEnumFromString(): void
    {
        $this->assertEquals(CommandStatus::PENDING, CommandStatus::from('pending'));
        $this->assertEquals(CommandStatus::RUNNING, CommandStatus::from('running'));
        $this->assertEquals(CommandStatus::COMPLETED, CommandStatus::from('completed'));
        $this->assertEquals(CommandStatus::FAILED, CommandStatus::from('failed'));
        $this->assertEquals(CommandStatus::TIMEOUT, CommandStatus::from('timeout'));
        $this->assertEquals(CommandStatus::CANCELED, CommandStatus::from('canceled'));
    }

    public function testEnumFromInvalidString(): void
    {
        $this->expectException(\ValueError::class);
        CommandStatus::from('invalid');
    }
} 