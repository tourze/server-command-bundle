<?php

namespace ServerCommandBundle\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Service\SshCommandExecutor;

class SshCommandExecutorTest extends TestCase
{
    private SshCommandExecutor $executor;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->executor = new SshCommandExecutor($this->logger);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(SshCommandExecutor::class, $this->executor);
    }
}