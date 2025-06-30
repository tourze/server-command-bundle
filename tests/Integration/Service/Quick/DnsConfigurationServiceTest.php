<?php

namespace ServerCommandBundle\Tests\Integration\Service\Quick;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Service\Quick\DnsConfigurationService;
use ServerCommandBundle\Service\RemoteCommandService;

class DnsConfigurationServiceTest extends TestCase
{
    private DnsConfigurationService $service;
    private RemoteCommandService $remoteCommandService;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->service = new DnsConfigurationService($this->remoteCommandService);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(DnsConfigurationService::class, $this->service);
    }
}