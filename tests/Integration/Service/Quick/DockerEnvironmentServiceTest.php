<?php

namespace ServerCommandBundle\Tests\Integration\Service\Quick;

use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Service\Quick\DockerEnvironmentService;
use ServerCommandBundle\Service\RemoteCommandService;

class DockerEnvironmentServiceTest extends TestCase
{
    private DockerEnvironmentService $service;
    private RemoteCommandService $remoteCommandService;

    protected function setUp(): void
    {
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);
        $this->service = new DockerEnvironmentService($this->remoteCommandService);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(DockerEnvironmentService::class, $this->service);
    }
}