<?php

namespace ServerCommandBundle\Tests\Integration\Repository;

use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;

class RemoteFileTransferRepositoryTest extends TestCase
{
    private ManagerRegistry $registry;
    private RemoteFileTransferRepository $repository;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(ManagerRegistry::class);
        $this->repository = new RemoteFileTransferRepository($this->registry);
    }

    public function testRepositoryCreation(): void
    {
        $this->assertInstanceOf(RemoteFileTransferRepository::class, $this->repository);
    }

    public function testRepositoryCreationWithoutEntityManager(): void
    {
        // 测试Repository的基本功能，但不依赖Entity Manager
        $this->expectException(\LogicException::class);
        $this->repository->getClassName();
    }

    public function testRepositoryHasCorrectClass(): void
    {
        // 测试Repository是正确的类型
        $this->assertInstanceOf(RemoteFileTransferRepository::class, $this->repository);
    }
}