<?php

namespace ServerCommandBundle\Tests\Service;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Service\RemoteFileService;
use ServerNodeBundle\Entity\Node;

class RemoteFileServiceTest extends TestCase
{
    private RemoteFileTransferRepository|MockObject $repository;
    private EntityManagerInterface|MockObject $entityManager;
    private LoggerInterface|MockObject $logger;
    private RemoteCommandService|MockObject $remoteCommandService;
    private RemoteFileService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(RemoteFileTransferRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->remoteCommandService = $this->createMock(RemoteCommandService::class);

        $this->service = new RemoteFileService(
            $this->repository,
            $this->entityManager,
            $this->logger,
            $this->remoteCommandService
        );
    }

    public function testCreateTransfer(): void
    {
        // 创建临时测试文件
        $tempFile = tempnam(sys_get_temp_dir(), 'test_upload_');
        file_put_contents($tempFile, 'test content');

        try {
            // 准备测试数据
            $node = $this->createMock(Node::class);
            $name = '测试文件传输';
            $localPath = $tempFile;
            $remotePath = '/var/www/test.txt';
            $useSudo = true;
            $timeout = 60;
            $tags = ['upload', 'test'];

            // 设置实体管理器的期望行为
            $this->entityManager->expects($this->once())
                ->method('persist')
                ->with($this->isInstanceOf(RemoteFileTransfer::class));

            $this->entityManager->expects($this->once())
                ->method('flush');

            // 设置日志记录器的期望行为
            $this->logger->expects($this->once())
                ->method('info')
                ->with(
                    $this->stringContains('文件传输任务已创建'),
                    $this->arrayHasKey('transfer')
                );

            // 调用被测试方法
            $result = $this->service->createTransfer(
                $node,
                $name,
                $localPath,
                $remotePath,
                $useSudo,
                $timeout,
                $tags
            );

            // 验证结果
            $this->assertInstanceOf(RemoteFileTransfer::class, $result);
            $this->assertSame($node, $result->getNode());
            $this->assertSame($name, $result->getName());
            $this->assertSame($localPath, $result->getLocalPath());
            $this->assertSame($remotePath, $result->getRemotePath());
            $this->assertSame($useSudo, $result->isUseSudo());
            $this->assertSame($timeout, $result->getTimeout());
            $this->assertSame($tags, $result->getTags());
            $this->assertSame(FileTransferStatus::PENDING, $result->getStatus());
            $this->assertNotNull($result->getTempPath());
            $this->assertNotNull($result->getFileSize());
        } finally {
            // 清理临时文件
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransferWithNonExistentFile(): void
    {
        // 准备测试数据
        $node = $this->createMock(Node::class);
        $name = '测试文件传输';
        $localPath = '/non/existent/file.txt';
        $remotePath = '/var/www/test.txt';

        // 期望抛出异常
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('本地文件不存在');

        // 调用被测试方法
        $this->service->createTransfer(
            $node,
            $name,
            $localPath,
            $remotePath
        );
    }

    public function testFindById(): void
    {
        // 准备测试数据
        $transferId = '123';
        $transfer = $this->createMock(RemoteFileTransfer::class);

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('find')
            ->with($transferId)
            ->willReturn($transfer);

        // 调用被测试方法
        $result = $this->service->findById($transferId);

        // 验证结果
        $this->assertSame($transfer, $result);
    }

    public function testFindPendingTransfersByNode(): void
    {
        // 准备测试数据
        $node = $this->createMock(Node::class);
        $transfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findPendingTransfersByNode')
            ->with($node)
            ->willReturn($transfers);

        // 调用被测试方法
        $result = $this->service->findPendingTransfersByNode($node);

        // 验证结果
        $this->assertSame($transfers, $result);
    }

    public function testFindAllPendingTransfers(): void
    {
        // 准备测试数据
        $transfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findAllPendingTransfers')
            ->willReturn($transfers);

        // 调用被测试方法
        $result = $this->service->findAllPendingTransfers();

        // 验证结果
        $this->assertSame($transfers, $result);
    }

    public function testFindByTags(): void
    {
        // 准备测试数据
        $tags = ['upload', 'test'];
        $transfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];

        // 设置仓库的期望行为
        $this->repository->expects($this->once())
            ->method('findByTags')
            ->with($tags)
            ->willReturn($transfers);

        // 调用被测试方法
        $result = $this->service->findByTags($tags);

        // 验证结果
        $this->assertSame($transfers, $result);
    }

    public function testCancelTransfer(): void
    {
        // 准备测试数据
        $transfer = $this->createMock(RemoteFileTransfer::class);

        // 设置传输的期望行为
        $transfer->expects($this->once())
            ->method('getStatus')
            ->willReturn(FileTransferStatus::PENDING);

        $transfer->expects($this->once())
            ->method('setStatus')
            ->with(FileTransferStatus::CANCELED);

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->once())
            ->method('flush');

        // 设置日志记录器的期望行为
        $this->logger->expects($this->once())
            ->method('info')
            ->with(
                $this->stringContains('文件传输已取消'),
                $this->arrayHasKey('transfer')
            );

        // 调用被测试方法
        $result = $this->service->cancelTransfer($transfer);

        // 验证结果
        $this->assertSame($transfer, $result);
    }

    public function testCancelTransferWithNonPendingTransfer(): void
    {
        // 准备测试数据
        $transfer = $this->createMock(RemoteFileTransfer::class);

        // 设置传输的期望行为
        $transfer->expects($this->once())
            ->method('getStatus')
            ->willReturn(FileTransferStatus::COMPLETED);

        $transfer->expects($this->never())
            ->method('setStatus');

        // 设置实体管理器的期望行为
        $this->entityManager->expects($this->never())
            ->method('flush');

        // 设置日志记录器的期望行为
        $this->logger->expects($this->never())
            ->method('info');

        // 调用被测试方法
        $result = $this->service->cancelTransfer($transfer);

        // 验证结果
        $this->assertSame($transfer, $result);
    }

    public function testGetRepository(): void
    {
        // 调用被测试方法
        $result = $this->service->getRepository();

        // 验证结果
        $this->assertSame($this->repository, $result);
    }
} 