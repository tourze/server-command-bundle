<?php

namespace ServerCommandBundle\Tests\Enum;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Enum\FileTransferStatus;
use Tourze\PHPUnitEnum\AbstractEnumTestCase;

/**
 * @internal
 */
#[CoversClass(FileTransferStatus::class)]
final class FileTransferStatusTest extends AbstractEnumTestCase
{
    public function testEnumValues(): void
    {
        // 测试所有枚举值
        $this->assertEquals('pending', FileTransferStatus::PENDING->value);
        $this->assertEquals('uploading', FileTransferStatus::UPLOADING->value);
        $this->assertEquals('moving', FileTransferStatus::MOVING->value);
        $this->assertEquals('completed', FileTransferStatus::COMPLETED->value);
        $this->assertEquals('failed', FileTransferStatus::FAILED->value);
        $this->assertEquals('canceled', FileTransferStatus::CANCELED->value);
    }

    public function testEnumCases(): void
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

    public function testGetLabelMethod(): void
    {
        // 测试每个状态的标签
        $this->assertEquals('待处理', FileTransferStatus::PENDING->getLabel());
        $this->assertEquals('UPLOADING', FileTransferStatus::UPLOADING->getLabel());
        $this->assertEquals('MOVING', FileTransferStatus::MOVING->getLabel());
        $this->assertEquals('已完成', FileTransferStatus::COMPLETED->getLabel());
        $this->assertEquals('失败', FileTransferStatus::FAILED->getLabel());
        $this->assertEquals('CANCELED', FileTransferStatus::CANCELED->getLabel());
    }

    public function testEnumFromString(): void
    {
        // 测试从字符串创建枚举
        $this->assertEquals(FileTransferStatus::PENDING, FileTransferStatus::from('pending'));
        $this->assertEquals(FileTransferStatus::UPLOADING, FileTransferStatus::from('uploading'));
        $this->assertEquals(FileTransferStatus::MOVING, FileTransferStatus::from('moving'));
        $this->assertEquals(FileTransferStatus::COMPLETED, FileTransferStatus::from('completed'));
        $this->assertEquals(FileTransferStatus::FAILED, FileTransferStatus::from('failed'));
        $this->assertEquals(FileTransferStatus::CANCELED, FileTransferStatus::from('canceled'));
    }

    public function testEnumFromInvalidString(): void
    {
        // 测试无效字符串抛出异常
        $this->expectException(\ValueError::class);
        FileTransferStatus::from('invalid_status');
    }

    public function testTryFromMethod(): void
    {
        // 测试 tryFrom 方法 - 有效输入
        $validResult = FileTransferStatus::tryFrom('pending');
        $this->assertEquals(FileTransferStatus::PENDING, $validResult);

        // 测试枚举值列表
        $expectedCases = ['pending', 'uploading', 'moving', 'completed', 'failed', 'canceled'];
        foreach ($expectedCases as $case) {
            $result = FileTransferStatus::tryFrom($case);
            $this->assertInstanceOf(FileTransferStatus::class, $result);
        }
    }

    /**
     * 测试 Itemable 接口实现（如果存在）
     */
    public function testItemableInterface(): void
    {
        $pending = FileTransferStatus::PENDING;

        // 测试枚举值和标签是否正确
        $this->assertEquals('pending', $pending->value);
        $this->assertEquals('待处理', $pending->getLabel());
    }

    /**
     * 测试 Selectable 接口实现（如果存在）
     */
    public function testSelectableInterface(): void
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

    public function testAllStatusTransitions(): void
    {
        // 测试状态转换的逻辑性
        $allStatuses = FileTransferStatus::cases();

        foreach ($allStatuses as $status) {
            // 每个状态都应该有标签
            $this->assertNotEmpty($status->getLabel());
        }
    }

    public function testLabelLocalization(): void
    {
        // 测试标签不为空
        $statuses = FileTransferStatus::cases();

        foreach ($statuses as $status) {
            $label = $status->getLabel();
            // 确保标签不为空
            $this->assertNotEmpty($label);
        }
    }

    public function testEnumSerialization(): void
    {
        // 测试枚举的序列化
        $status = FileTransferStatus::PENDING;

        $serialized = serialize($status);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(FileTransferStatus::class, $unserialized);
        $this->assertEquals($status, $unserialized);
        $this->assertEquals($status->value, $unserialized->value);
        $this->assertEquals($status->getLabel(), $unserialized->getLabel());
    }

    public function testEnumJsonSerialization(): void
    {
        // 测试 JSON 序列化
        $status = FileTransferStatus::COMPLETED;

        $json = json_encode($status);
        $this->assertEquals('"completed"', $json);
    }

    public function testToArray(): void
    {
        // 测试toArray方法，这个方法由SelectTrait提供，需要通过实例调用
        $fileTransferStatus = FileTransferStatus::PENDING;
        $array = $fileTransferStatus->toArray();

        // toArray() 方法已经声明返回数组类型，直接验证内容

        // 验证数组包含value和label
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('label', $array);
        $this->assertEquals('pending', $array['value']);
        $this->assertEquals('待处理', $array['label']);

        // 测试其他枚举值
        $testCases = [
            ['status' => FileTransferStatus::UPLOADING, 'expected_label' => 'UPLOADING'],
            ['status' => FileTransferStatus::MOVING, 'expected_label' => 'MOVING'],
            ['status' => FileTransferStatus::COMPLETED, 'expected_label' => '已完成'],
            ['status' => FileTransferStatus::FAILED, 'expected_label' => '失败'],
            ['status' => FileTransferStatus::CANCELED, 'expected_label' => 'CANCELED'],
        ];

        foreach ($testCases as $testCase) {
            $array = $testCase['status']->toArray();
            $this->assertEquals($testCase['status']->value, $array['value']);
            $this->assertEquals($testCase['expected_label'], $array['label']);
        }
    }
}
