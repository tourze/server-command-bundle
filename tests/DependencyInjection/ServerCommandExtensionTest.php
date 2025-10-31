<?php

namespace ServerCommandBundle\Tests\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use ServerCommandBundle\DependencyInjection\ServerCommandExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\PHPUnitSymfonyUnitTest\AbstractDependencyInjectionExtensionTestCase;

/**
 * @internal
 */
#[CoversClass(ServerCommandExtension::class)]
final class ServerCommandExtensionTest extends AbstractDependencyInjectionExtensionTestCase
{
    private ServerCommandExtension $extension;

    private ContainerBuilder $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->extension = new ServerCommandExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
        $this->container->setParameter('kernel.environment', 'test');
        $this->extension->load([], $this->container);

        // 验证服务是否正确注册
        $this->assertTrue($this->container->hasDefinition('ServerCommandBundle\Service\RemoteCommandService'));
        $this->assertTrue($this->container->hasDefinition('ServerCommandBundle\Service\RemoteFileService'));
        $this->assertTrue($this->container->hasDefinition('ServerCommandBundle\Service\SshConnectionService'));
        $this->assertTrue($this->container->hasDefinition('ServerCommandBundle\Service\SshCommandExecutor'));
    }

    public function testGetAlias(): void
    {
        $this->assertEquals('server_command', $this->extension->getAlias());
    }
}
