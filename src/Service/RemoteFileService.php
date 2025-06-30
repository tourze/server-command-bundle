<?php

namespace ServerCommandBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SFTP;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Timer\Timer;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Exception\RemoteFileArgumentException;
use ServerCommandBundle\Exception\RemoteFileException;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;

class RemoteFileService
{
    /**
     * 默认临时目录
     */
    private const DEFAULT_TEMP_DIR = '/tmp';

    public function __construct(
        private readonly RemoteFileTransferRepository $remoteFileTransferRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly SshConnectionService $sshConnectionService,
        private readonly RemoteCommandService $remoteCommandService,
    ) {
    }

    /**
     * 创建新的文件传输任务
     */
    public function createTransfer(
        Node $node,
        string $name,
        string $localPath,
        string $remotePath,
        ?bool $useSudo = false,
        ?int $timeout = 300,
        ?array $tags = null
    ): RemoteFileTransfer {
        // 检查本地文件是否存在
        if (!file_exists($localPath)) {
            throw RemoteFileArgumentException::invalidArgument("本地文件不存在: {$localPath}");
        }

        $fileSize = filesize($localPath);
        $tempPath = self::DEFAULT_TEMP_DIR . '/' . basename($localPath) . '_' . uniqid();

        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName($name);
        $transfer->setLocalPath($localPath);
        $transfer->setRemotePath($remotePath);
        $transfer->setTempPath($tempPath);
        $transfer->setFileSize($fileSize);
        $transfer->setUseSudo($useSudo);
        $transfer->setTimeout($timeout);
        $transfer->setTags($tags);
        $transfer->setStatus(FileTransferStatus::PENDING);

        $this->entityManager->persist($transfer);
        $this->entityManager->flush();

        $this->logger->info('文件传输任务已创建', [
            'transfer' => $transfer,
            'localPath' => $localPath,
            'remotePath' => $remotePath,
            'fileSize' => $fileSize,
        ]);

        return $transfer;
    }

    /**
     * 执行文件传输
     */
    public function executeTransfer(RemoteFileTransfer $transfer): RemoteFileTransfer
    {
        if (!$transfer->isEnabled()) {
            $this->logger->warning('尝试执行已禁用的文件传输', ['transfer' => $transfer]);
            return $transfer;
        }

        $transfer->setStatus(FileTransferStatus::UPLOADING);
        $transfer->setStartedAt(new DateTime());
        $this->entityManager->flush();

        $timer = new Timer();
        $timer->start();

        try {
            // 检查本地文件是否存在
            if (!file_exists($transfer->getLocalPath())) {
                throw RemoteFileException::fileNotExists($transfer->getLocalPath());
            }

            // 创建SSH连接
            $ssh = $this->sshConnectionService->connectWithPassword(
                $transfer->getNode()->getSshHost(),
                $transfer->getNode()->getSshPort(),
                $transfer->getNode()->getSshUser(),
                $transfer->getNode()->getSshPassword()
            );

            // 创建SFTP连接
            $sftp = new SFTP($transfer->getNode()->getSshHost(), $transfer->getNode()->getSshPort());

            // 登录SFTP
            if (null !== $transfer->getNode()->getSshPrivateKey() && '' !== $transfer->getNode()->getSshPrivateKey()) {
                $key = \phpseclib3\Crypt\PublicKeyLoader::load($transfer->getNode()->getSshPrivateKey());
                if (!$sftp->login($transfer->getNode()->getSshUser(), $key)) {
                    throw RemoteFileException::connectionFailed();
                }
            } else {
                if (!$sftp->login($transfer->getNode()->getSshUser(), $transfer->getNode()->getSshPassword())) {
                    throw RemoteFileException::connectionFailed();
                }
            }

            // 第一步：上传文件到临时目录
            $this->logger->info('开始上传文件到临时目录', [
                'localPath' => $transfer->getLocalPath(),
                'tempPath' => $transfer->getTempPath(),
            ]);

            if (!$sftp->put($transfer->getTempPath(), $transfer->getLocalPath(), SFTP::SOURCE_LOCAL_FILE)) {
                throw RemoteFileException::transferExecutionFailed('文件上传到临时目录失败');
            }

            // 验证临时文件是否上传成功
            if (!$sftp->file_exists($transfer->getTempPath())) {
                throw RemoteFileException::transferStatusUpdateFailed();
            }

            $this->logger->info('文件已成功上传到临时目录', [
                'tempPath' => $transfer->getTempPath(),
            ]);

            // 第二步：移动文件到目标位置
            $transfer->setStatus(FileTransferStatus::MOVING);
            $this->entityManager->flush();

            $this->logger->info('开始移动文件到目标位置', [
                'tempPath' => $transfer->getTempPath(),
                'remotePath' => $transfer->getRemotePath(),
                'useSudo' => $transfer->isUseSudo(),
            ]);

            // 确保目标目录存在
            $targetDir = dirname($transfer->getRemotePath());
            $mkdirCommand = $transfer->isUseSudo() ? "sudo mkdir -p {$targetDir}" : "mkdir -p {$targetDir}";

            $mkdirResult = $this->remoteCommandService->execSshCommand(
                $ssh,
                "mkdir -p {$targetDir}",
                null,
                $transfer->isUseSudo(),
                $transfer->getNode()
            );

            // 移动文件
            $mvResult = $this->remoteCommandService->execSshCommand(
                $ssh,
                "mv {$transfer->getTempPath()} {$transfer->getRemotePath()}",
                null,
                $transfer->isUseSudo(),
                $transfer->getNode()
            );

            // 验证目标文件是否存在
            $checkResult = $this->remoteCommandService->execSshCommand(
                $ssh,
                "test -f {$transfer->getRemotePath()} && echo 'exists' || echo 'not exists'",
                null,
                $transfer->isUseSudo(),
                $transfer->getNode()
            );

            if (!str_contains(trim($checkResult), 'exists')) {
                throw RemoteFileException::transferStatusUpdateFailed();
            }

            $duration = $timer->stop();
            $transfer->setTransferTime($duration->asSeconds());
            $transfer->setStatus(FileTransferStatus::COMPLETED);
            $transfer->setCompletedAt(new DateTime());
            $transfer->setResult('文件传输成功');

            $this->logger->info('文件传输完成', [
                'transfer' => $transfer,
                'duration' => $duration->asString(),
            ]);

        } catch (\Throwable $e) {
            $duration = $timer->stop();
            $transfer->setTransferTime($duration->asSeconds());
            $transfer->setStatus(FileTransferStatus::FAILED);
            $transfer->setCompletedAt(new DateTime());
            $transfer->setResult('传输失败: ' . $e->getMessage());

            $this->logger->error('文件传输失败', [
                'transfer' => $transfer,
                'error' => $e->getMessage(),
                'duration' => $duration->asString(),
            ]);
        } finally {
            $this->entityManager->flush();
        }

        return $transfer;
    }

    /**
     * 取消文件传输
     */
    public function cancelTransfer(RemoteFileTransfer $transfer): RemoteFileTransfer
    {
        if ($transfer->getStatus() === FileTransferStatus::PENDING) {
            $transfer->setStatus(FileTransferStatus::CANCELED);
            $this->entityManager->flush();

            $this->logger->info('文件传输已取消', ['transfer' => $transfer]);
        }

        return $transfer;
    }

    /**
     * 根据ID查找传输记录
     */
    public function findById(string $id): ?RemoteFileTransfer
    {
        return $this->remoteFileTransferRepository->find($id);
    }

    /**
     * 查找节点上待传输的文件
     */
    public function findPendingTransfersByNode(Node $node): array
    {
        return $this->remoteFileTransferRepository->findPendingTransfersByNode($node);
    }

    /**
     * 查找所有待传输的文件
     */
    public function findAllPendingTransfers(): array
    {
        return $this->remoteFileTransferRepository->findAllPendingTransfers();
    }

    /**
     * 按标签查找传输记录
     */
    public function findByTags(array $tags): array
    {
        return $this->remoteFileTransferRepository->findByTags($tags);
    }

    /**
     * 获取Repository实例
     */
    public function getRepository(): RemoteFileTransferRepository
    {
        return $this->remoteFileTransferRepository;
    }

    /**
     * 上传并直接移动文件（一步到位）
     */
    public function uploadFile(
        Node $node,
        string $localPath,
        string $remotePath,
        ?bool $useSudo = false,
        ?string $name = null
    ): RemoteFileTransfer {
        $name = $name ?? sprintf('上传 %s', basename($localPath));

        $transfer = $this->createTransfer(
            $node,
            $name,
            $localPath,
            $remotePath,
            $useSudo
        );

        return $this->executeTransfer($transfer);
    }
}
