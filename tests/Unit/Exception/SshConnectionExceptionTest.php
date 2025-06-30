<?php

namespace ServerCommandBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ServerCommandBundle\Exception\SshConnectionException;

class SshConnectionExceptionTest extends TestCase
{
    public function testConnectionFailed(): void
    {
        $exception = SshConnectionException::connectionFailed('192.168.1.1', 22);
        
        $this->assertInstanceOf(SshConnectionException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertEquals('SSH连接失败: 192.168.1.1:22', $exception->getMessage());
    }

    public function testAuthenticationFailed(): void
    {
        $exception = SshConnectionException::authenticationFailed();
        
        $this->assertInstanceOf(SshConnectionException::class, $exception);
        $this->assertEquals('SSH认证失败', $exception->getMessage());
    }

    public function testSudoSwitchFailed(): void
    {
        $exception = SshConnectionException::sudoSwitchFailed();
        
        $this->assertInstanceOf(SshConnectionException::class, $exception);
        $this->assertEquals('切换到root用户失败', $exception->getMessage());
    }

    public function testExecutionFailed(): void
    {
        $command = 'ls -la';
        $exception = SshConnectionException::executionFailed($command);
        
        $this->assertInstanceOf(SshConnectionException::class, $exception);
        $this->assertEquals('命令执行失败: ls -la', $exception->getMessage());
    }
}