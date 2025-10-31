<?php

namespace ServerCommandBundle\Exception;

class RemoteFileArgumentException extends \InvalidArgumentException
{
    public static function invalidArgument(string $message): self
    {
        return new self($message);
    }

    public static function fileNotFound(string $filePath): self
    {
        return new self(sprintf('文件未找到: %s', $filePath));
    }

    public static function invalidTransferType(string $type): self
    {
        return new self(sprintf('无效的传输类型: %s', $type));
    }

    public static function emptyFilePath(): self
    {
        return new self('文件路径不能为空');
    }

    public static function invalidNode(): self
    {
        return new self('无效的节点');
    }
}
