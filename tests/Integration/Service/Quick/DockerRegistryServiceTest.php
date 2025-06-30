<?php

namespace ServerCommandBundle\Tests\Integration\Service\Quick;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Service\Quick\DnsConfigurationService;
use ServerCommandBundle\Service\Quick\DockerRegistryService;
use ServerCommandBundle\Service\RemoteCommandService;

class DockerRegistryServiceTest extends TestCase
{
    private DockerRegistryService $service;
    private RemoteCommandService $remoteCommandService;
    private DnsConfigurationService $dnsConfigurationService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->dnsConfigurationService = $this->createMock(DnsConfigurationService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new DockerRegistryService(
            $this->remoteCommandService,
            $this->dnsConfigurationService,
            $this->logger
        );
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(DockerRegistryService::class, $this->service);
    }
}