<?php

namespace ServerCommandBundle\Tests\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Service\AttributeControllerLoader;
use Symfony\Component\Routing\RouteCollection;
use Tourze\PHPUnitSymfonyKernelTest\AbstractIntegrationTestCase;

/**
 * @internal
 */
#[CoversClass(AttributeControllerLoader::class)]
#[RunTestsInSeparateProcesses]
final class AttributeControllerLoaderTest extends AbstractIntegrationTestCase
{
    private AttributeControllerLoader $loader;

    protected function onSetUp(): void
    {
        $this->loader = self::getService(AttributeControllerLoader::class);
    }

    public function testSupports(): void
    {
        $this->assertFalse($this->loader->supports('test', 'test'));
    }

    public function testLoad(): void
    {
        $result = $this->loader->load('test', 'test');

        // 验证load方法返回了RouteCollection
        $this->assertInstanceOf(RouteCollection::class, $result);
        $this->assertGreaterThanOrEqual(0, $result->count());
    }

    public function testAutoload(): void
    {
        $result = $this->loader->autoload();

        // 验证autoload方法返回了RouteCollection
        $this->assertInstanceOf(RouteCollection::class, $result);
        // 至少应该加载了TerminalController的路由
        $this->assertGreaterThan(0, $result->count());
    }
}
