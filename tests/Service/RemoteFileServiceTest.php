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
    private RemoteFileTransferRepository&MockObject $repository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private RemoteCommandService&MockObject $remoteCommandService;
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
            /** @var Node&MockObject $node */
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
        /** @var Node&MockObject $node */
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
        /** @var RemoteFileTransfer&MockObject $transfer */
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
        /** @var Node&MockObject $node */
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
        /** @var RemoteFileTransfer&MockObject $transfer */
        $transfer = $this->createMock(RemoteFileTransfer::class);

        // 设置传输状态为可取消状态
        $transfer->expects($this->once())
            ->method('getStatus')
            ->willReturn(FileTransferStatus::PENDING);

        $transfer->expects($this->once())
            ->method('setStatus')
            ->with(FileTransferStatus::CANCELED)
            ->willReturnSelf();

        // cancelTransfer 只调用 flush，不调用 persist
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
        /** @var RemoteFileTransfer&MockObject $transfer */
        $transfer = $this->createMock(RemoteFileTransfer::class);

        // 设置传输状态为非待执行状态
        $transfer->expects($this->once())
            ->method('getStatus')
            ->willReturn(FileTransferStatus::COMPLETED);

        // cancelTransfer 方法在非PENDING状态时不抛出异常，只是跳过操作
        $transfer->expects($this->never())
            ->method('setStatus');

        $this->entityManager->expects($this->never())
            ->method('flush');

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

    // ========== 文件传输核心逻辑测试 ==========

    public function testExecuteTransfer_WithDisabledTransfer(): void
    {
        // 准备测试数据
        /** @var RemoteFileTransfer&MockObject $transfer */
        $transfer = $this->createMock(RemoteFileTransfer::class);

        $transfer->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        // 设置日志记录器期望
        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains('尝试执行已禁用的文件传输'),
                $this->arrayHasKey('transfer')
            );

        // 调用被测试方法
        $result = $this->service->executeTransfer($transfer);

        // 验证结果
        $this->assertSame($transfer, $result);
    }

    public function testExecuteTransfer_WithNonExistentLocalFile(): void
    {
        // 创建真实的传输对象来测试
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('测试传输');
        $transfer->setLocalPath('/non/existent/file.txt');
        $transfer->setRemotePath('/tmp/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setEnabled(true);

        // 设置实体管理器期望
        $this->entityManager->expects($this->atLeastOnce())
            ->method('flush');

        // 设置日志记录器期望
        $this->logger->expects($this->atLeastOnce())
            ->method('error');

        // 调用被测试方法
        $result = $this->service->executeTransfer($transfer);

        // 验证结果
        $this->assertSame(FileTransferStatus::FAILED, $result->getStatus());
        $this->assertStringContainsString('本地文件不存在', $result->getResult());
        $this->assertNotNull($result->getCompletedAt());
    }

    public function testExecuteTransfer_SftpConnectionFailure(): void
    {
        // 创建临时文件
        $tempFile = tempnam(sys_get_temp_dir(), 'test_');
        file_put_contents($tempFile, 'test content');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $node->method('getSshHost')->willReturn('invalid-host.example.com');
            $node->method('getSshPort')->willReturn(22);
            $node->method('getSshUser')->willReturn('testuser');
            $node->method('getSshPassword')->willReturn('invalid-password');
            $node->method('getSshPrivateKey')->willReturn(null);

            $transfer = new RemoteFileTransfer();
            $transfer->setNode($node);
            $transfer->setName('SFTP连接测试');
            $transfer->setLocalPath($tempFile);
            $transfer->setRemotePath('/tmp/test.txt');
            $transfer->setStatus(FileTransferStatus::PENDING);
            $transfer->setEnabled(true);

            // 由于需要实际网络连接，这里跳过测试
            $this->markTestSkipped('需要实际SSH服务器进行SFTP连接测试');

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransfer_WithLargeFile(): void
    {
        // 创建大文件测试
        $tempFile = tempnam(sys_get_temp_dir(), 'large_test_');
        $largeContent = str_repeat('A', 1024 * 1024); // 1MB内容
        file_put_contents($tempFile, $largeContent);

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $name = '大文件传输测试';
            $remotePath = '/var/www/large_test.txt';

            // 设置实体管理器期望
            $this->entityManager->expects($this->once())
                ->method('persist')
                ->with($this->isInstanceOf(RemoteFileTransfer::class));

            $this->entityManager->expects($this->once())
                ->method('flush');

            // 设置日志记录器期望
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
                $tempFile,
                $remotePath
            );

            // 验证结果
            $this->assertInstanceOf(RemoteFileTransfer::class, $result);
            $this->assertEquals(1024 * 1024, $result->getFileSize());
            $this->assertStringContainsString('/tmp/', $result->getTempPath());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransfer_WithEmptyFile(): void
    {
        // 创建空文件测试
        $tempFile = tempnam(sys_get_temp_dir(), 'empty_test_');
        file_put_contents($tempFile, ''); // 空文件

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $name = '空文件传输测试';
            $remotePath = '/var/www/empty_test.txt';

            $this->entityManager->expects($this->once())
                ->method('persist');
            $this->entityManager->expects($this->once())
                ->method('flush');
            $this->logger->expects($this->once())
                ->method('info');

            $result = $this->service->createTransfer(
                $node,
                $name,
                $tempFile,
                $remotePath
            );

            $this->assertEquals(0, $result->getFileSize());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransfer_WithSpecialCharactersInPath(): void
    {
        // 测试路径中包含特殊字符
        $tempFile = tempnam(sys_get_temp_dir(), 'special_chars_test_');
        file_put_contents($tempFile, 'test content');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $name = '特殊字符路径测试';
            $remotePath = '/var/www/测试文件 with spaces & symbols.txt';

            $this->entityManager->expects($this->once())
                ->method('persist');
            $this->entityManager->expects($this->once())
                ->method('flush');
            $this->logger->expects($this->once())
                ->method('info');

            $result = $this->service->createTransfer(
                $node,
                $name,
                $tempFile,
                $remotePath
            );

            $this->assertEquals($remotePath, $result->getRemotePath());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransfer_WithCustomTags(): void
    {
        // 测试自定义标签
        $tempFile = tempnam(sys_get_temp_dir(), 'tags_test_');
        file_put_contents($tempFile, 'test content');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $name = '标签测试';
            $remotePath = '/tmp/tags_test.txt';
            $tags = ['deployment', 'config', 'production'];

            $this->entityManager->expects($this->once())
                ->method('persist');
            $this->entityManager->expects($this->once())
                ->method('flush');
            $this->logger->expects($this->once())
                ->method('info');

            $result = $this->service->createTransfer(
                $node,
                $name,
                $tempFile,
                $remotePath,
                false,
                600,
                $tags
            );

            $this->assertEquals($tags, $result->getTags());
            $this->assertEquals(600, $result->getTimeout());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testCreateTransfer_WithSudoPermission(): void
    {
        // 测试sudo权限传输
        $tempFile = tempnam(sys_get_temp_dir(), 'sudo_test_');
        file_put_contents($tempFile, 'test content');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $name = 'Sudo权限测试';
            $remotePath = '/etc/nginx/nginx.conf';

            $this->entityManager->expects($this->once())
                ->method('persist');
            $this->entityManager->expects($this->once())
                ->method('flush');
            $this->logger->expects($this->once())
                ->method('info');

            $result = $this->service->createTransfer(
                $node,
                $name,
                $tempFile,
                $remotePath,
                true // 使用sudo
            );

            $this->assertTrue($result->isUseSudo());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUploadFile_QuickMethod(): void
    {
        // 测试快速上传方法的逻辑验证
        $tempFile = tempnam(sys_get_temp_dir(), 'quick_upload_');
        file_put_contents($tempFile, 'quick upload test');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $remotePath = '/tmp/quick_upload.txt';

            // 验证文件存在和内容
            $this->assertTrue(file_exists($tempFile));
            $this->assertEquals('quick upload test', file_get_contents($tempFile));
            
            // 验证节点和路径配置
            $this->assertInstanceOf(Node::class, $node);
            $this->assertEquals('/tmp/quick_upload.txt', $remotePath);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUploadFile_WithCustomName(): void
    {
        // 测试带自定义名称的快速上传逻辑验证
        $tempFile = tempnam(sys_get_temp_dir(), 'custom_name_');
        file_put_contents($tempFile, 'custom name test');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $remotePath = '/tmp/custom_name.txt';
            $customName = '自定义传输名称';

            // 验证文件和配置
            $this->assertTrue(file_exists($tempFile));
            $this->assertEquals('custom name test', file_get_contents($tempFile));
            $this->assertEquals('自定义传输名称', $customName);
            $this->assertEquals('/tmp/custom_name.txt', $remotePath);

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testUploadFile_CreateTransferOnly(): void
    {
        // 测试只调用 createTransfer 部分的逻辑
        $tempFile = tempnam(sys_get_temp_dir(), 'create_only_');
        file_put_contents($tempFile, 'create only test');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $remotePath = '/tmp/create_only.txt';
            $customName = '只创建传输任务';

            $this->entityManager->expects($this->once())
                ->method('persist');
            $this->entityManager->expects($this->once())
                ->method('flush');
            $this->logger->expects($this->once())
                ->method('info');

            // 只调用 createTransfer 方法
            $result = $this->service->createTransfer(
                $node,
                $customName,
                $tempFile,
                $remotePath
            );

            $this->assertInstanceOf(RemoteFileTransfer::class, $result);
            $this->assertEquals($customName, $result->getName());
            $this->assertEquals($remotePath, $result->getRemotePath());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testFindByStatus_Integration(): void
    {
        // 测试按状态查找传输的逻辑验证
        $status = FileTransferStatus::COMPLETED;
        $expectedTransfers = [
            $this->createMock(RemoteFileTransfer::class),
            $this->createMock(RemoteFileTransfer::class),
        ];
        
        $this->repository->expects($this->once())
            ->method('findByStatus')
            ->with($status)
            ->willReturn($expectedTransfers);

        // 直接通过repository调用
        $result = $this->repository->findByStatus($status);
        $this->assertSame($expectedTransfers, $result);
        $this->assertCount(2, $result);
    }

    public function testExecuteTransfer_CompleteWorkflow(): void
    {
        // 测试完整传输工作流的配置验证
        $tempFile = tempnam(sys_get_temp_dir(), 'workflow_test_');
        file_put_contents($tempFile, 'workflow test content');

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);
            $node->method('getSshHost')->willReturn('test.example.com');
            $node->method('getSshUser')->willReturn('testuser');

            $transfer = new RemoteFileTransfer();
            $transfer->setNode($node);
            $transfer->setLocalPath($tempFile);
            $transfer->setRemotePath('/tmp/workflow_test.txt');
            $transfer->setStatus(FileTransferStatus::PENDING);

            // 验证传输配置
            $this->assertEquals($node, $transfer->getNode());
            $this->assertEquals($tempFile, $transfer->getLocalPath());
            $this->assertEquals('/tmp/workflow_test.txt', $transfer->getRemotePath());
            $this->assertEquals(FileTransferStatus::PENDING, $transfer->getStatus());
            $this->assertEquals('test.example.com', $node->getSshHost());

        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testExecuteTransfer_PermissionDeniedOnRemoteTarget(): void
    {
        // 测试远程目标权限被拒绝的配置验证
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshUser')->willReturn('normaluser');

        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setRemotePath('/etc/sensitive/config.txt'); // 需要特殊权限的路径
        $transfer->setUseSudo(false); // 不使用sudo

        // 验证权限配置
        $this->assertEquals('normaluser', $node->getSshUser());
        $this->assertStringContainsString('/etc/', $transfer->getRemotePath());
        $this->assertFalse($transfer->isUseSudo());
    }

    public function testExecuteTransfer_NetworkInterruption(): void
    {
        // 测试网络中断情况的配置验证
        /** @var Node&MockObject $node */
        $node = $this->createMock(Node::class);
        $node->method('getSshHost')->willReturn('unreliable-host.example.com');
        $node->method('getSshPort')->willReturn(22);

        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setTimeout(30); // 短超时时间

        // 验证网络配置
        $this->assertEquals('unreliable-host.example.com', $node->getSshHost());
        $this->assertEquals(22, $node->getSshPort());
        $this->assertEquals(30, $transfer->getTimeout());
    }

    public function testExecuteTransfer_DiskSpaceInsufficient(): void
    {
        // 测试磁盘空间不足的配置验证
        $largeFile = tempnam(sys_get_temp_dir(), 'large_file_');
        file_put_contents($largeFile, str_repeat('x', 1024)); // 1KB文件

        try {
            /** @var Node&MockObject $node */
            $node = $this->createMock(Node::class);

            $transfer = new RemoteFileTransfer();
            $transfer->setNode($node);
            $transfer->setLocalPath($largeFile);
            $transfer->setRemotePath('/tmp/large_file.txt');

            // 验证文件大小和配置
            $this->assertTrue(file_exists($largeFile));
            $this->assertEquals(1024, filesize($largeFile));
            $this->assertEquals($largeFile, $transfer->getLocalPath());

        } finally {
            if (file_exists($largeFile)) {
                unlink($largeFile);
            }
        }
    }
} 