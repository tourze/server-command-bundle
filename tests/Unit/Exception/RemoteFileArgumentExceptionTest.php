<?php

namespace ServerCommandBundle\Tests\Unit\Exception;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Exception\RemoteFileArgumentException;

class RemoteFileArgumentExceptionTest extends TestCase
{
    public function testInvalidArgument(): void
    {
        $message = 'Invalid argument provided';
        $exception = RemoteFileArgumentException::invalidArgument($message);
        
        $this->assertInstanceOf(RemoteFileArgumentException::class, $exception);
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertEquals('Invalid argument provided', $exception->getMessage());
    }
}