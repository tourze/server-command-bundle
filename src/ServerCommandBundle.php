<?php

namespace ServerCommandBundle;

use ServerNodeBundle\ServerNodeBundle;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Tourze\BundleDependency\BundleDependencyInterface;
use Tourze\RoutingAutoLoaderBundle\RoutingAutoLoaderBundle;
use Tourze\ScheduleEntityCleanBundle\ScheduleEntityCleanBundle;

class ServerCommandBundle extends Bundle implements BundleDependencyInterface
{
    public static function getBundleDependencies(): array
    {
        return [
            RoutingAutoLoaderBundle::class => ['all' => true],
            ScheduleEntityCleanBundle::class => ['all' => true],
            ServerNodeBundle::class => ['all' => true],
        ];
    }
}
