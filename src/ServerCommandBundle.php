<?php

namespace ServerCommandBundle;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
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
            DoctrineBundle::class => ['all' => true],
            RoutingAutoLoaderBundle::class => ['all' => true],
            ScheduleEntityCleanBundle::class => ['all' => true],
            ServerNodeBundle::class => ['all' => true],
        ];
    }
}
