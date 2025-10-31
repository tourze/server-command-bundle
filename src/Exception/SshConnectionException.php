<?php

namespace ServerCommandBundle\Exception;

class SshConnectionException extends \RuntimeException
{
    public static function connectionFailed(string $host, int $port): self
    {
        return new self(sprintf('SSH连接失败: %s:%d', $host, $port));
    }

    public static function authenticationFailed(): self
    {
        return new self('SSH认证失败');
    }

    public static function sudoSwitchFailed(): self
    {
        return new self('切换到root用户失败');
    }

    public static function executionFailed(string $command): self
    {
        return new self(sprintf('命令执行失败: %s', $command));
    }
}
