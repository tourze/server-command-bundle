<?php

namespace ServerCommandBundle\Exception;

class DockerRegistryException extends \RuntimeException
{
    public static function configurationCreateFailed(): self
    {
        return new self('无法创建Docker registry配置文件');
    }

    public static function configurationUpdateFailed(): self
    {
        return new self('无法更新Docker registry配置');
    }

    public static function directoryCreationFailed(): self
    {
        return new self('无法创建Docker registry配置目录');
    }
}
