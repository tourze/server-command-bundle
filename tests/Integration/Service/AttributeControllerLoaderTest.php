<?php

namespace ServerCommandBundle\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Service\AttributeControllerLoader;
use Symfony\Component\Routing\RouteCollection;

class AttributeControllerLoaderTest extends TestCase
{
    private AttributeControllerLoader $loader;

    protected function setUp(): void
    {
        $this->loader = new AttributeControllerLoader();
    }

    public function testSupports(): void
    {
        $this->assertFalse($this->loader->supports('test', 'test'));
    }

    public function testLoad(): void
    {
        $result = $this->loader->load('test', 'test');
        
        $this->assertInstanceOf(RouteCollection::class, $result);
    }

    public function testAutoload(): void
    {
        $result = $this->loader->autoload();
        
        $this->assertInstanceOf(RouteCollection::class, $result);
    }
}