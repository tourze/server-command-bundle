<?php

namespace ServerCommandBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\DependencyInjection\ServerCommandExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ServerCommandExtensionTest extends TestCase
{
    private ServerCommandExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new ServerCommandExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoad(): void
    {
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