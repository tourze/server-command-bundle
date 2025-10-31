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

    case PENDING = 'pending';
    case UPLOADING = 'uploading';
    case MOVING = 'moving';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELED = 'canceled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待处理',
            self::UPLOADING => 'UPLOADING',
            self::MOVING => 'MOVING',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
            self::CANCELED => 'CANCELED',
        };
    }

    /**
     * 检查是否为终止状态
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::COMPLETED, self::FAILED, self::CANCELED => true,
            self::PENDING, self::UPLOADING, self::MOVING => false,
        };
    }
}
