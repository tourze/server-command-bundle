<?php

namespace ServerCommandBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Exception\RemoteFileException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteFileException::class)]
final class RemoteFileExceptionTest extends AbstractExceptionTestCase
{
    public function testInvalidLocalPath(): void
    {
        $exception = RemoteFileException::invalidLocalPath();

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('本地文件路径不能为空', $exception->getMessage());
    }

    public function testFileNotExists(): void
    {
        $path = '/test/path';
        $exception = RemoteFileException::fileNotExists($path);

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('文件不存在: /test/path', $exception->getMessage());
    }

    public function testRemotePathEmpty(): void
    {
        $exception = RemoteFileException::remotePathEmpty();

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('远程路径不能为空', $exception->getMessage());
    }

    public function testTransferExecutionFailed(): void
    {
        $reason = 'Connection failed';
        $exception = RemoteFileException::transferExecutionFailed($reason);

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('文件传输执行失败: Connection failed', $exception->getMessage());
    }

    public function testTransferCancelFailed(): void
    {
        $exception = RemoteFileException::transferCancelFailed();

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('无法取消文件传输', $exception->getMessage());
    }

    public function testTransferStatusUpdateFailed(): void
    {
        $exception = RemoteFileException::transferStatusUpdateFailed();

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('传输状态更新失败', $exception->getMessage());
    }

    public function testConnectionFailed(): void
    {
        $exception = RemoteFileException::connectionFailed();

        $this->assertInstanceOf(RemoteFileException::class, $exception);
        $this->assertEquals('SFTP连接失败', $exception->getMessage());
    }
}
