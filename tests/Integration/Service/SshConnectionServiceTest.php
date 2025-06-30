<?php

namespace ServerCommandBundle\Tests\Integration\Service;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Service\SshConnectionService;
use ServerNodeBundle\Entity\Node;

class SshConnectionServiceTest extends TestCase
{
    private SshConnectionService $service;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->service = new SshConnectionService($this->logger);
    }

    public function testServiceCreation(): void
    {
        $this->assertInstanceOf(SshConnectionService::class, $this->service);
    }

    public function testCreateConnectionWithMockNode(): void
    {
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('127.0.0.1');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('test');
        $node->method('getSshPassword')->willReturn('password');

        // 测试方法存在性，预期会抛出异常因为没有真实连接
        $this->expectException(\Exception::class);
        $this->service->createConnection($node);
    }

    public function testCreateConnectionWithSudo(): void
    {
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('127.0.0.1');
        $node->method('getSshPort')->willReturn(22);
        $node->method('getSshUser')->willReturn('test');
        $node->method('getSshPassword')->willReturn('password');

        // 测试sudo模式
        $this->expectException(\Exception::class);
        $this->service->createConnection($node, true);
    }
}