<?php

namespace ServerCommandBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\DependencyInjection\ServerCommandExtension;
use ServerCommandBundle\ServerCommandBundle;

class ServerCommandBundleTest extends TestCase
{
    private ServerCommandBundle $bundle;

    protected function setUp(): void
    {
        $this->bundle = new ServerCommandBundle();
    }

    public function testGetContainerExtension(): void
    {
        $extension = $this->bundle->getContainerExtension();
        
        $this->assertInstanceOf(ServerCommandExtension::class, $extension);
    }

    public function testGetPath(): void
    {
        $path = $this->bundle->getPath();
        
        $this->assertStringEndsWith('src', $path);
        $this->assertTrue(is_dir($path));
    }
}