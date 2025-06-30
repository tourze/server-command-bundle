<?php

namespace ServerCommandBundle\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use ServerCommandBundle\Exception\DockerEnvironmentException;

class DockerEnvironmentExceptionTest extends TestCase
{
    public function testEnvironmentCreateFailed(): void
    {
        $exception = DockerEnvironmentException::environmentCreateFailed();
        
        $this->assertInstanceOf(DockerEnvironmentException::class, $exception);
        $this->assertInstanceOf(RuntimeException::class, $exception);
        $this->assertEquals('无法创建Docker环境文件', $exception->getMessage());
    }

    public function testEnvironmentUpdateFailed(): void
    {
        $exception = DockerEnvironmentException::environmentUpdateFailed();
        
        $this->assertInstanceOf(DockerEnvironmentException::class, $exception);
        $this->assertEquals('无法更新Docker环境文件', $exception->getMessage());
    }

    public function testDirectoryCreationFailed(): void
    {
        $exception = DockerEnvironmentException::directoryCreationFailed();
        
        $this->assertInstanceOf(DockerEnvironmentException::class, $exception);
        $this->assertEquals('无法创建环境文件目录', $exception->getMessage());
    }
}