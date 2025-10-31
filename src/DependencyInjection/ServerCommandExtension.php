<?php

namespace ServerCommandBundle\DependencyInjection;

use Tourze\SymfonyDependencyServiceLoader\AutoExtension;

class ServerCommandExtension extends AutoExtension
{
    protected function getConfigDir(): string
    {
        return __DIR__ . '/../Resources/config';
    }

    public function getAlias(): string
    {
        return 'server_command';
    }
}
