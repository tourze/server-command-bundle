<?php

namespace ServerCommandBundle\Exception;

use RuntimeException;

class DockerEnvironmentException extends RuntimeException
{
    public static function environmentCreateFailed(): self
    {
        return new self('无法创建Docker环境文件');
    }

    public static function environmentUpdateFailed(): self
    {
        return new self('无法更新Docker环境文件');
    }

    public static function directoryCreationFailed(): self
    {
        return new self('无法创建环境文件目录');
    }
}