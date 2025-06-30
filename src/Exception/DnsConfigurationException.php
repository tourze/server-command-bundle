<?php

namespace ServerCommandBundle\Exception;

use RuntimeException;

class DnsConfigurationException extends RuntimeException
{
    public static function directoryCreationFailed(): self
    {
        return new self('无法创建systemd-resolved配置目录');
    }

    public static function configurationCreateFailed(): self
    {
        return new self('无法创建systemd-resolved配置文件');
    }

    public static function configurationUpdateFailed(): self
    {
        return new self('无法更新systemd-resolved配置');
    }

    public static function dnsmasqConfigCreateFailed(): self
    {
        return new self('无法创建dnsmasq配置文件');
    }
}