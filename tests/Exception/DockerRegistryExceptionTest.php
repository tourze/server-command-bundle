<?php

namespace ServerCommandBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Exception\DockerRegistryException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(DockerRegistryException::class)]
final class DockerRegistryExceptionTest extends AbstractExceptionTestCase
{
    public function testConfigurationCreateFailed(): void
    {
        $exception = DockerRegistryException::configurationCreateFailed();

        $this->assertInstanceOf(DockerRegistryException::class, $exception);
        $this->assertEquals('无法创建Docker registry配置文件', $exception->getMessage());
    }

    public function testConfigurationUpdateFailed(): void
    {
        $exception = DockerRegistryException::configurationUpdateFailed();

        $this->assertInstanceOf(DockerRegistryException::class, $exception);
        $this->assertEquals('无法更新Docker registry配置', $exception->getMessage());
    }

    public function testDirectoryCreationFailed(): void
    {
        $exception = DockerRegistryException::directoryCreationFailed();

        $this->assertInstanceOf(DockerRegistryException::class, $exception);
        $this->assertEquals('无法创建Docker registry配置目录', $exception->getMessage());
    }
}
