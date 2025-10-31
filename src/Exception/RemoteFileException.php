<?php

namespace ServerCommandBundle\Exception;

class RemoteFileException extends \RuntimeException
{
    public static function invalidLocalPath(): self
    {
        return new self('本地文件路径不能为空');
    }

    public static function fileNotExists(string $path): self
    {
        return new self(sprintf('文件不存在: %s', $path));
    }

    public static function remotePathEmpty(): self
    {
        return new self('远程路径不能为空');
    }

    public static function transferExecutionFailed(string $reason): self
    {
        return new self(sprintf('文件传输执行失败: %s', $reason));
    }

    public static function transferCancelFailed(): self
    {
        return new self('无法取消文件传输');
    }

    public static function transferStatusUpdateFailed(): self
    {
        return new self('传输状态更新失败');
    }

    public static function connectionFailed(): self
    {
        return new self('SFTP连接失败');
    }
}
