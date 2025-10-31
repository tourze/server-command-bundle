<?php

namespace ServerCommandBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Enum\CommandStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(CommandStatus::class)]
final class CommandStatusTest extends AbstractEnumTestCase
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

    public function testToArray(): void
    {
        // 测试toArray方法，这个方法由SelectTrait提供，需要通过实例调用
        $commandStatus = CommandStatus::PENDING;
        $array = $commandStatus->toArray();

        // toArray() 方法已经声明返回数组类型，直接验证内容

        // 验证数组包含value和label
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertEquals('pending', $array['value']);
        $this->assertEquals('待执行', $array['label']);

        // 测试其他枚举值
        $testCases = [
            ['status' => CommandStatus::RUNNING, 'expected_label' => '执行中'],
            ['status' => CommandStatus::COMPLETED, 'expected_label' => '已完成'],
            ['status' => CommandStatus::FAILED, 'expected_label' => '失败'],
            ['status' => CommandStatus::TIMEOUT, 'expected_label' => '超时'],
            ['status' => CommandStatus::CANCELED, 'expected_label' => '已取消'],
        ];

        foreach ($testCases as $testCase) {
            $array = $testCase['status']->toArray();
            $this->assertEquals($testCase['status']->value, $array['value']);
            $this->assertEquals($testCase['expected_label'], $array['label']);
        }
    }
}
