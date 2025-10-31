<?php

namespace ServerCommandBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Exception\DockerEnvironmentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DockerEnvironmentException::class)]
final class DockerEnvironmentExceptionTest extends AbstractExceptionTestCase
{
    public function testEnvironmentCreateFailed(): void
    {
        $exception = DockerEnvironmentException::environmentCreateFailed();

        $this->assertInstanceOf(DockerEnvironmentException::class, $exception);
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
