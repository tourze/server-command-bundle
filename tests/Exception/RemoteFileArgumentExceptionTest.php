<?php

namespace ServerCommandBundle\Tests\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\Exception\RemoteFileArgumentException;
use Tourze\PHPUnitBase\AbstractExceptionTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteFileArgumentException::class)]
final class RemoteFileArgumentExceptionTest extends AbstractExceptionTestCase
{
    public function testInvalidArgument(): void
    {
        $message = 'Invalid argument provided';
        $exception = RemoteFileArgumentException::invalidArgument($message);

        $this->assertInstanceOf(RemoteFileArgumentException::class, $exception);
        $this->assertEquals('Invalid argument provided', $exception->getMessage());
    }
}
