<?php

declare(strict_types=1);

namespace ServerCommandBundle\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\ServerCommandBundle;
use Tourze\PHPUnitSymfonyKernelTest\AbstractBundleTestCase;

/**
 * @internal
 */
#[CoversClass(ServerCommandBundle::class)]
#[RunTestsInSeparateProcesses]
final class ServerCommandBundleTest extends AbstractBundleTestCase
{
}
