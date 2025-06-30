<?php

namespace ServerCommandBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ServerCommandBundle\Exception\DnsConfigurationException;

class DnsConfigurationExceptionTest extends TestCase
{
    public function testDirectoryCreationFailed(): void
    {
        $exception = DnsConfigurationException::directoryCreationFailed();
        
        $this->assertInstanceOf(DnsConfigurationException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertEquals('无法创建systemd-resolved配置目录', $exception->getMessage());
    }

    public function testConfigurationCreateFailed(): void
    {
        $exception = DnsConfigurationException::configurationCreateFailed();
        
        $this->assertInstanceOf(DnsConfigurationException::class, $exception);
        $this->assertEquals('无法创建systemd-resolved配置文件', $exception->getMessage());
    }

    public function testConfigurationUpdateFailed(): void
    {
        $exception = DnsConfigurationException::configurationUpdateFailed();
        
        $this->assertInstanceOf(DnsConfigurationException::class, $exception);
        $this->assertEquals('无法更新systemd-resolved配置', $exception->getMessage());
    }

    public function testDnsmasqConfigCreateFailed(): void
    {
        $exception = DnsConfigurationException::dnsmasqConfigCreateFailed();
        
        $this->assertInstanceOf(DnsConfigurationException::class, $exception);
        $this->assertEquals('无法创建dnsmasq配置文件', $exception->getMessage());
    }
}