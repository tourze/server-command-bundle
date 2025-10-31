<?php

namespace ServerCommandBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Timer\Timer;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Exception\RemoteFileArgumentException;
use ServerCommandBundle\Exception\RemoteFileException;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;

#[WithMonologChannel(channel: 'server_command')]
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
        private readonly SshCommandExecutor $sshCommandExecutor,
    ) {
    }

    /**
     * 创建新的文件传输任务
     *
     * @param string[]|null $tags
     */
    public function createTransfer(
        Node $node,
        string $name,
        string $localPath,
        string $remotePath,
        ?bool $useSudo = false,
        ?int $timeout = 300,
        ?array $tags = null,
    ): RemoteFileTransfer {
        // 检查本地文件是否存在
        if (!file_exists($localPath)) {
            throw RemoteFileArgumentException::invalidArgument("本地文件不存在: {$localPath}");
        }

        $fileSize = filesize($localPath);
        if (false === $fileSize) {
            throw RemoteFileArgumentException::invalidArgument("无法获取文件大小: {$localPath}");
        }
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
        if (false === $transfer->isEnabled()) {
            $this->logger->warning('尝试执行已禁用的文件传输', ['transfer' => $transfer]);

            return $transfer;
        }

        $this->initializeTransfer($transfer);
        $timer = new Timer();
        $timer->start();

        try {
            $this->validateLocalFile($transfer);
            $ssh = $this->establishSshConnection($transfer);
            $sftp = $this->establishSftpConnection($transfer);
            $this->uploadFileToTemp($transfer, $sftp);
            $this->moveFileToTarget($transfer, $ssh);
            $this->completeTransfer($transfer, $timer);
        } catch (\Throwable $e) {
            $this->handleTransferError($transfer, $timer, $e);
        } finally {
            $this->entityManager->flush();
        }

        return $transfer;
    }

    private function initializeTransfer(RemoteFileTransfer $transfer): void
    {
        $transfer->setStatus(FileTransferStatus::UPLOADING);
        $transfer->setStartedAt(new \DateTimeImmutable());
        $this->entityManager->flush();
    }

    private function validateLocalFile(RemoteFileTransfer $transfer): void
    {
        if (!file_exists($transfer->getLocalPath())) {
            throw RemoteFileException::fileNotExists($transfer->getLocalPath());
        }
    }

    private function establishSshConnection(RemoteFileTransfer $transfer): SSH2
    {
        $host = $transfer->getNode()->getSshHost();
        $user = $transfer->getNode()->getSshUser();
        $password = $transfer->getNode()->getSshPassword();

        if (null === $host || null === $user || null === $password) {
            throw RemoteFileException::connectionFailed();
        }

        return $this->sshConnectionService->connectWithPassword(
            $host,
            $transfer->getNode()->getSshPort(),
            $user,
            $password
        );
    }

    private function establishSftpConnection(RemoteFileTransfer $transfer): SFTP
    {
        $sftp = new SFTP($transfer->getNode()->getSshHost(), $transfer->getNode()->getSshPort());

        if (null !== $transfer->getNode()->getSshPrivateKey() && '' !== $transfer->getNode()->getSshPrivateKey()) {
            $key = PublicKeyLoader::load($transfer->getNode()->getSshPrivateKey());
            $user = $transfer->getNode()->getSshUser();
            if (null === $user || !$sftp->login($user, $key)) {
                throw RemoteFileException::connectionFailed();
            }
        } else {
            $user = $transfer->getNode()->getSshUser();
            $password = $transfer->getNode()->getSshPassword();
            if (null === $user || null === $password || !$sftp->login($user, $password)) {
                throw RemoteFileException::connectionFailed();
            }
        }

        return $sftp;
    }

    private function uploadFileToTemp(RemoteFileTransfer $transfer, SFTP $sftp): void
    {
        $this->logger->info('开始上传文件到临时目录', [
            'localPath' => $transfer->getLocalPath(),
            'tempPath' => $transfer->getTempPath(),
        ]);

        // 首先尝试使用 SFTP 上传
        $tempPath = $transfer->getTempPath();
        $localPath = $transfer->getLocalPath();

        if (null === $tempPath || '' === $tempPath || '' === $localPath) {
            throw RemoteFileException::transferExecutionFailed('临时路径或本地路径为空');
        }

        if ($sftp->put($tempPath, $localPath, SFTP::SOURCE_LOCAL_FILE)) {
            if ($sftp->file_exists($tempPath)) {
                $this->logger->info('文件已成功上传到临时目录 (SFTP)', ['tempPath' => $transfer->getTempPath()]);

                return;
            }
        }

        // SFTP 上传失败，尝试使用 sshpass + scp 作为备选方案
        $this->logger->warning('SFTP 上传失败，尝试使用 sshpass + scp 备选方案');

        if ($this->uploadFileWithSshpass($transfer)) {
            $this->logger->info('文件已成功上传到临时目录 (sshpass + scp)', ['tempPath' => $transfer->getTempPath()]);

            return;
        }

        throw RemoteFileException::transferExecutionFailed('文件上传到临时目录失败：SFTP 和 sshpass + scp 方案均失败');
    }

    private function moveFileToTarget(RemoteFileTransfer $transfer, SSH2 $ssh): void
    {
        $transfer->setStatus(FileTransferStatus::MOVING);
        $this->entityManager->flush();

        $this->logger->info('开始移动文件到目标位置', [
            'tempPath' => $transfer->getTempPath(),
            'remotePath' => $transfer->getRemotePath(),
            'useSudo' => $transfer->isUseSudo(),
        ]);

        $this->ensureTargetDirectory($transfer, $ssh);
        $this->moveFile($transfer, $ssh);
        $this->verifyTargetFile($transfer, $ssh);
    }

    private function ensureTargetDirectory(RemoteFileTransfer $transfer, SSH2 $ssh): void
    {
        $targetDir = dirname($transfer->getRemotePath());
        $useSudo = $transfer->isUseSudo();
        if (null === $useSudo) {
            $useSudo = false;
        }

        $this->sshCommandExecutor->execute(
            $ssh,
            "mkdir -p {$targetDir}",
            null,
            $useSudo,
            $transfer->getNode()
        );
    }

    private function moveFile(RemoteFileTransfer $transfer, SSH2 $ssh): void
    {
        $useSudo = $transfer->isUseSudo();
        if (null === $useSudo) {
            $useSudo = false;
        }

        $this->sshCommandExecutor->execute(
            $ssh,
            "mv {$transfer->getTempPath()} {$transfer->getRemotePath()}",
            null,
            $useSudo,
            $transfer->getNode()
        );
    }

    private function verifyTargetFile(RemoteFileTransfer $transfer, SSH2 $ssh): void
    {
        $useSudo = $transfer->isUseSudo();
        if (null === $useSudo) {
            $useSudo = false;
        }

        $checkResult = $this->sshCommandExecutor->execute(
            $ssh,
            "test -f {$transfer->getRemotePath()} && echo 'exists' || echo 'not exists'",
            null,
            $useSudo,
            $transfer->getNode()
        );

        if (!str_contains(trim($checkResult), 'exists')) {
            throw RemoteFileException::transferStatusUpdateFailed();
        }
    }

    private function completeTransfer(RemoteFileTransfer $transfer, Timer $timer): void
    {
        $duration = $timer->stop();
        $transfer->setTransferTime($duration->asSeconds());
        $transfer->setStatus(FileTransferStatus::COMPLETED);
        $transfer->setCompletedAt(new \DateTimeImmutable());
        $transfer->setResult('文件传输成功');

        $this->logger->info('文件传输完成', [
            'transfer' => $transfer,
            'duration' => $duration->asString(),
        ]);
    }

    private function handleTransferError(RemoteFileTransfer $transfer, Timer $timer, \Throwable $e): void
    {
        $duration = $timer->stop();
        $transfer->setTransferTime($duration->asSeconds());
        $transfer->setStatus(FileTransferStatus::FAILED);
        $transfer->setCompletedAt(new \DateTimeImmutable());
        $transfer->setResult('传输失败: ' . $e->getMessage());

        $this->logger->error('文件传输失败', [
            'transfer' => $transfer,
            'error' => $e->getMessage(),
            'duration' => $duration->asString(),
        ]);
    }

    /**
     * 取消文件传输
     */
    public function cancelTransfer(RemoteFileTransfer $transfer): RemoteFileTransfer
    {
        if (FileTransferStatus::PENDING === $transfer->getStatus()) {
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
     *
     * @return RemoteFileTransfer[]
     */
    public function findPendingTransfersByNode(Node $node): array
    {
        return $this->remoteFileTransferRepository->findPendingTransfersByNode($node);
    }

    /**
     * 查找所有待传输的文件
     *
     * @return RemoteFileTransfer[]
     */
    public function findAllPendingTransfers(): array
    {
        return $this->remoteFileTransferRepository->findAllPendingTransfers();
    }

    /**
     * 按标签查找传输记录
     *
     * @param string[] $tags
     *
     * @return RemoteFileTransfer[]
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
     * 使用 sshpass + scp 上传文件到临时目录
     */
    private function uploadFileWithSshpass(RemoteFileTransfer $transfer): bool
    {
        // 检查是否支持 sshpass（仅支持密码认证）
        if (!$this->isSshpassSupported($transfer)) {
            $this->logger->debug('不支持 sshpass：节点使用私钥认证或密码为空');

            return false;
        }

        try {
            $this->logger->info('开始使用 sshpass + scp 上传文件');

            $command = $this->buildSshpassScpCommand($transfer);
            $this->logger->debug('执行 sshpass + scp 命令', ['command_template' => $this->sanitizeCommandForLog($command)]);

            $output = [];
            $returnCode = 0;
            exec($command, $output, $returnCode);

            if (0 !== $returnCode) {
                $this->logger->error('sshpass + scp 命令执行失败', [
                    'returnCode' => $returnCode,
                    'output' => implode("\n", $output),
                ]);

                return false;
            }

            // 验证文件是否上传成功
            return $this->verifyUploadedFileWithSsh($transfer);
        } catch (\Throwable $e) {
            $this->logger->error('sshpass + scp 上传过程中发生异常', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return false;
        }
    }

    /**
     * 检查是否支持 sshpass（仅支持密码认证的节点）
     */
    private function isSshpassSupported(RemoteFileTransfer $transfer): bool
    {
        $node = $transfer->getNode();

        // 必须使用密码认证（不使用私钥）
        $hasPassword = null !== $node->getSshPassword() && '' !== $node->getSshPassword();
        $hasNoPrivateKey = null === $node->getSshPrivateKey() || '' === $node->getSshPrivateKey();

        return $hasPassword && $hasNoPrivateKey;
    }

    /**
     * 构建 sshpass + scp 命令
     */
    private function buildSshpassScpCommand(RemoteFileTransfer $transfer): string
    {
        $node = $transfer->getNode();

        $nodePassword = $node->getSshPassword();
        $nodeHost = $node->getSshHost();
        $nodeUser = $node->getSshUser();
        $transferLocalPath = $transfer->getLocalPath();

        if (
            null === $nodePassword
            || null === $nodeHost
            || null === $nodeUser
            || '' === $nodePassword
            || '' === $nodeHost
            || '' === $nodeUser
            || '' === $transferLocalPath
        ) {
            throw RemoteFileException::transferExecutionFailed('SSH连接信息不完整');
        }

        $password = escapeshellarg($nodePassword);
        $localPath = escapeshellarg($transferLocalPath);
        $host = escapeshellarg($nodeHost);
        $user = escapeshellarg($nodeUser);
        $port = $node->getSshPort();
        $transferTempPath = $transfer->getTempPath();

        if (null === $transferTempPath || '' === $transferTempPath) {
            throw RemoteFileException::transferExecutionFailed('临时路径为空');
        }

        $tempPath = escapeshellarg($transferTempPath);

        return sprintf(
            'sshpass -p %s scp -o StrictHostKeyChecking=no -P %d %s %s@%s:%s',
            $password,
            $port,
            $localPath,
            $user,
            $host,
            $tempPath
        );
    }

    /**
     * 为日志记录清理命令（移除敏感信息）
     */
    private function sanitizeCommandForLog(string $command): string
    {
        $result = preg_replace('/sshpass -p [^\s]+/', 'sshpass -p [HIDDEN]', $command);

        return $result ?? $command;
    }

    /**
     * 通过 SSH 验证文件是否上传成功
     */
    private function verifyUploadedFileWithSsh(RemoteFileTransfer $transfer): bool
    {
        try {
            $ssh = $this->establishSshConnection($transfer);
            $checkResult = $this->sshCommandExecutor->execute(
                $ssh,
                "test -f {$transfer->getTempPath()} && echo 'exists' || echo 'not exists'",
                null,
                false,
                $transfer->getNode()
            );

            return str_contains(trim($checkResult), 'exists');
        } catch (\Throwable $e) {
            $this->logger->error('验证上传文件时发生异常', [
                'error' => $e->getMessage(),
                'tempPath' => $transfer->getTempPath(),
            ]);

            return false;
        }
    }

    /**
     * 上传并直接移动文件（一步到位）
     */
    public function uploadFile(
        Node $node,
        string $localPath,
        string $remotePath,
        ?bool $useSudo = false,
        ?string $name = null,
    ): RemoteFileTransfer {
        $name ??= sprintf('上传 %s', basename($localPath));

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
