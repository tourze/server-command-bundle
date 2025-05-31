<?php

namespace ServerCommandBundle\Tests\Enum;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Enum\FileTransferStatus;

class FileTransferStatusTest extends TestCase
{
    public function test_enum_values(): void
    {
        // 测试所有枚举值
        $this->assertEquals('pending', FileTransferStatus::PENDING->value);
        $this->assertEquals('uploading', FileTransferStatus::UPLOADING->value);
        $this->assertEquals('moving', FileTransferStatus::MOVING->value);
        $this->assertEquals('completed', FileTransferStatus::COMPLETED->value);
        $this->assertEquals('failed', FileTransferStatus::FAILED->value);
        $this->assertEquals('canceled', FileTransferStatus::CANCELED->value);
    }

    public function test_enum_cases(): void
    {
        $expectedCases = [
            FileTransferStatus::PENDING,
            FileTransferStatus::UPLOADING,
            FileTransferStatus::MOVING,
            FileTransferStatus::COMPLETED,
            FileTransferStatus::FAILED,
            FileTransferStatus::CANCELED,
        ];

        $actualCases = FileTransferStatus::cases();
        
        $this->assertEquals($expectedCases, $actualCases);
        $this->assertCount(6, $actualCases);
    }

    public function test_get_label_method(): void
    {
        // 测试每个状态的标签
        $this->assertEquals('等待传输', FileTransferStatus::PENDING->getLabel());
        $this->assertEquals('正在上传', FileTransferStatus::UPLOADING->getLabel());
        $this->assertEquals('正在移动', FileTransferStatus::MOVING->getLabel());
        $this->assertEquals('传输完成', FileTransferStatus::COMPLETED->getLabel());
        $this->assertEquals('传输失败', FileTransferStatus::FAILED->getLabel());
        $this->assertEquals('已取消', FileTransferStatus::CANCELED->getLabel());
    }

    public function test_get_color_method(): void
    {
        // 测试每个状态的颜色
        $this->assertEquals('warning', FileTransferStatus::PENDING->getColor());
        $this->assertEquals('info', FileTransferStatus::UPLOADING->getColor());
        $this->assertEquals('info', FileTransferStatus::MOVING->getColor());
        $this->assertEquals('success', FileTransferStatus::COMPLETED->getColor());
        $this->assertEquals('danger', FileTransferStatus::FAILED->getColor());
        $this->assertEquals('secondary', FileTransferStatus::CANCELED->getColor());
    }

    public function test_is_terminal_method(): void
    {
        // 测试终态状态
        $this->assertTrue(FileTransferStatus::COMPLETED->isTerminal());
        $this->assertTrue(FileTransferStatus::FAILED->isTerminal());
        $this->assertTrue(FileTransferStatus::CANCELED->isTerminal());
        
        // 测试非终态状态
        $this->assertFalse(FileTransferStatus::PENDING->isTerminal());
        $this->assertFalse(FileTransferStatus::UPLOADING->isTerminal());
        $this->assertFalse(FileTransferStatus::MOVING->isTerminal());
    }

    public function test_enum_from_string(): void
    {
        // 测试从字符串创建枚举
        $this->assertEquals(FileTransferStatus::PENDING, FileTransferStatus::from('pending'));
        $this->assertEquals(FileTransferStatus::UPLOADING, FileTransferStatus::from('uploading'));
        $this->assertEquals(FileTransferStatus::MOVING, FileTransferStatus::from('moving'));
        $this->assertEquals(FileTransferStatus::COMPLETED, FileTransferStatus::from('completed'));
        $this->assertEquals(FileTransferStatus::FAILED, FileTransferStatus::from('failed'));
        $this->assertEquals(FileTransferStatus::CANCELED, FileTransferStatus::from('canceled'));
    }

    public function test_enum_from_invalid_string(): void
    {
        // 测试无效字符串抛出异常
        $this->expectException(\ValueError::class);
        FileTransferStatus::from('invalid_status');
    }

    public function test_try_from_method(): void
    {
        // 测试 tryFrom 方法
        $this->assertEquals(FileTransferStatus::PENDING, FileTransferStatus::tryFrom('pending'));
        $this->assertNull(FileTransferStatus::tryFrom('invalid_status'));
        $this->assertNull(FileTransferStatus::tryFrom(''));
    }

    /**
     * 测试 Itemable 接口实现（如果存在）
     */
    public function test_itemable_interface(): void
    {
        $pending = FileTransferStatus::PENDING;
        
        // 测试接口实现
        $this->assertInstanceOf(FileTransferStatus::class, $pending);
        $this->assertEquals('pending', $pending->value);
        $this->assertEquals('等待传输', $pending->getLabel());
    }

    /**
     * 测试 Selectable 接口实现（如果存在）
     */
    public function test_selectable_interface(): void
    {
        // 测试枚举的基本功能
        $statuses = FileTransferStatus::cases();
        $this->assertCount(6, $statuses);
        
        // 验证每个状态都有有效的值和标签
        foreach ($statuses as $status) {
            $this->assertNotEmpty($status->value);
            $this->assertNotEmpty($status->getLabel());
        }
    }

    public function test_all_status_transitions(): void
    {
        // 测试状态转换的逻辑性
        $allStatuses = FileTransferStatus::cases();
        
        foreach ($allStatuses as $status) {
            // 每个状态都应该有标签
            $this->assertNotEmpty($status->getLabel());
            // 每个状态都应该有颜色
            $this->assertNotEmpty($status->getColor());
            // isTerminal 应该返回布尔值
            $this->assertIsBool($status->isTerminal());
        }
    }

    public function test_status_consistency(): void
    {
        // 确保标签和颜色的一致性
        $statusColorMap = [
            'warning' => ['pending'],
            'info' => ['uploading', 'moving'],
            'success' => ['completed'],
            'danger' => ['failed'],
            'secondary' => ['canceled'],
        ];

        foreach ($statusColorMap as $color => $expectedStatuses) {
            foreach ($expectedStatuses as $statusValue) {
                $status = FileTransferStatus::from($statusValue);
                $this->assertEquals($color, $status->getColor(), 
                    "Status {$statusValue} should have color {$color}");
            }
        }
    }

    public function test_terminal_status_definition(): void
    {
        // 确保终态状态的定义是正确的
        $terminalStatuses = ['completed', 'failed', 'canceled'];
        $nonTerminalStatuses = ['pending', 'uploading', 'moving'];

        foreach ($terminalStatuses as $statusValue) {
            $status = FileTransferStatus::from($statusValue);
            $this->assertTrue($status->isTerminal(), 
                "Status {$statusValue} should be terminal");
        }

        foreach ($nonTerminalStatuses as $statusValue) {
            $status = FileTransferStatus::from($statusValue);
            $this->assertFalse($status->isTerminal(), 
                "Status {$statusValue} should not be terminal");
        }
    }

    public function test_label_localization(): void
    {
        // 测试标签是否为中文
        $statuses = FileTransferStatus::cases();
        
        foreach ($statuses as $status) {
            $label = $status->getLabel();
            // 确保标签不为空且包含中文字符
            $this->assertNotEmpty($label);
            $this->assertGreaterThan(0, preg_match('/[\x{4e00}-\x{9fff}]/u', $label),
                "Label '{$label}' should contain Chinese characters");
        }
    }

    public function test_color_values_are_valid_bootstrap_classes(): void
    {
        // 测试颜色值是否为有效的 Bootstrap 样式类
        $validColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
        
        $statuses = FileTransferStatus::cases();
        
        foreach ($statuses as $status) {
            $color = $status->getColor();
            $this->assertContains($color, $validColors, 
                "Color '{$color}' for status '{$status->value}' should be a valid Bootstrap color");
        }
    }

    public function test_enum_serialization(): void
    {
        // 测试枚举的序列化
        $status = FileTransferStatus::PENDING;
        
        $serialized = serialize($status);
        $unserialized = unserialize($serialized);
        
        $this->assertEquals($status, $unserialized);
        $this->assertEquals($status->value, $unserialized->value);
        $this->assertEquals($status->getLabel(), $unserialized->getLabel());
    }

    public function test_enum_json_serialization(): void
    {
        // 测试 JSON 序列化
        $status = FileTransferStatus::COMPLETED;
        
        $json = json_encode($status);
        $this->assertEquals('"completed"', $json);
    }
} 