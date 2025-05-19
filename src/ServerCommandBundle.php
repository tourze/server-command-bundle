<?php

namespace ServerCommandBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;

class ServerCommandBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            \ServerNodeBundle\ServerNodeBundle::class => ['all' => true],
        ];
    }
}
