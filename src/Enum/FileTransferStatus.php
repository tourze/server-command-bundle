<?php

namespace ServerCommandBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

/**
 * 文件传输状态枚举
 */
enum FileTransferStatus: string implements Labelable, Itemable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';          // 等待传输
    case UPLOADING = 'uploading';      // 正在上传
    case MOVING = 'moving';            // 正在移动
    case COMPLETED = 'completed';      // 传输完成
    case FAILED = 'failed';            // 传输失败
    case CANCELED = 'canceled';        // 已取消

    /**
     * 获取状态标签
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '等待传输',
            self::UPLOADING => '正在上传',
            self::MOVING => '正在移动',
            self::COMPLETED => '传输完成',
            self::FAILED => '传输失败',
            self::CANCELED => '已取消',
        };
    }

    /**
     * 获取状态颜色
     */
    public function getColor(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::UPLOADING => 'info',
            self::MOVING => 'info',
            self::COMPLETED => 'success',
            self::FAILED => 'danger',
            self::CANCELED => 'secondary',
        };
    }

    /**
     * 检查是否是终态
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::COMPLETED, self::FAILED, self::CANCELED], true);
    }
}
