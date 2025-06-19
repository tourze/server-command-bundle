<?php

namespace ServerCommandBundle\Enum;

use Tourze\EnumExtra\Itemable;
use Tourze\EnumExtra\ItemTrait;
use Tourze\EnumExtra\Labelable;
use Tourze\EnumExtra\Selectable;
use Tourze\EnumExtra\SelectTrait;

enum CommandStatus: string implements Itemable, Labelable, Selectable
{
    use ItemTrait;
    use SelectTrait;

    case PENDING = 'pending';
    case RUNNING = 'running';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case CANCELED = 'canceled';

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => '待执行',
            self::RUNNING => '执行中',
            self::COMPLETED => '已完成',
            self::FAILED => '失败',
            self::TIMEOUT => '超时',
            self::CANCELED => '已取消',
        };
    }
}
