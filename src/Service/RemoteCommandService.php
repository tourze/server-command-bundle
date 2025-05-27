<?php

namespace ServerCommandBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Timer\Timer;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoteCommandService
{
    /**
     * SSH连接超时时间（秒）
     */
    private const SSH_READ_TIMEOUT = 0.2; // 200ms

    /**
     * 最大等待时间（秒）
     */
    private const MAX_WAIT_TIME = 5;

    /**
     * 短暂等待时间（微秒）
     */
    private const POLLING_INTERVAL = 100000; // 100ms

    public function __construct(
        private readonly RemoteCommandRepository $remoteCommandRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * 创建新的远程命令
     */
    public function createCommand(
        Node $node,
        string $name,
        string $command,
        ?string $workingDirectory = null,
        ?bool $useSudo = false,
        ?int $timeout = 300,
        ?array $tags = null
    ): RemoteCommand {
        $remoteCommand = new RemoteCommand();
        $remoteCommand->setNode($node);
        $remoteCommand->setName($name);
        $remoteCommand->setCommand($command);
        $remoteCommand->setWorkingDirectory($workingDirectory);
        $remoteCommand->setUseSudo($useSudo);
        $remoteCommand->setTimeout($timeout);
        $remoteCommand->setTags($tags);
        $remoteCommand->setStatus(CommandStatus::PENDING);

        $this->entityManager->persist($remoteCommand);
        $this->entityManager->flush();

        return $remoteCommand;
    }

    /**
     * 创建SSH连接
     */
    private function createSshConnection(Node $node, bool $useSudo = false): SSH2
    {
        $ssh = $this->initializeSshConnection($node);

        // 如果需要sudo且用户不是root，需要切换到root
        if ($useSudo && 'root' !== $node->getSshUser()) {
            $this->switchToRootUser($ssh, $node);
        }

        return $ssh;
    }

    /**
     * 使用密码凭证创建SSH连接
     */
    public function connectWithPassword(string $host, int $port, string $user, string $password): SSH2
    {
        $ssh = $this->createBasicSshConnection($host, $port);

        if (!$ssh->login($user, $password)) {
            $error = 'SSH连接失败: 密码认证失败';
            $this->logger->error($error, [
                'host' => $host,
                'port' => $port,
                'user' => $user,
            ]);
            throw new \RuntimeException($error);
        }

        $this->logger->info('SSH密码认证成功', [
            'host' => $host,
            'port' => $port,
            'user' => $user,
        ]);

        $this->configureSshConnection($ssh, $host, $port, $user, '密码认证');

        return $ssh;
    }

    /**
     * 使用私钥凭证创建SSH连接
     */
    public function connectWithPrivateKey(string $host, int $port, string $user, string $privateKey): SSH2
    {
        $ssh = $this->createBasicSshConnection($host, $port);

        try {
            $key = PublicKeyLoader::load($privateKey);
            if (!$ssh->login($user, $key)) {
                $error = 'SSH连接失败: 私钥认证失败';
                $this->logger->error($error, [
                    'host' => $host,
                    'port' => $port,
                    'user' => $user,
                ]);
                throw new \RuntimeException($error);
            }

            $this->logger->info('SSH私钥认证成功', [
                'host' => $host,
                'port' => $port,
                'user' => $user,
            ]);

            $this->configureSshConnection($ssh, $host, $port, $user, '私钥认证');

            return $ssh;
        } catch (\Exception $e) {
            $error = 'SSH连接失败: 私钥加载或认证失败';
            $this->logger->error($error, [
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'error' => $e->getMessage(),
            ]);
            throw new \RuntimeException($error, 0, $e);
        }
    }

    /**
     * 初始化SSH连接
     */
    private function initializeSshConnection(Node $node): SSH2
    {
        $host = $node->getSshHost();
        $port = $node->getSshPort();
        $user = $node->getSshUser();
        $privateKey = $node->getSshPrivateKey();
        $password = $node->getSshPassword();

        // 优先尝试私钥认证
        if ($privateKey) {
            try {
                return $this->connectWithPrivateKey($host, $port, $user, $privateKey);
            } catch (\Exception $e) {
                $this->logger->warning('SSH私钥认证失败，尝试密码认证', [
                    'host' => $host,
                    'port' => $port,
                    'user' => $user,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 如果私钥认证失败或没有私钥，尝试密码认证
        if ($password) {
            try {
                return $this->connectWithPassword($host, $port, $user, $password);
            } catch (\Exception $e) {
                // 密码认证也失败了，记录错误并抛出异常
                $this->logger->error('SSH密码认证也失败', [
                    'host' => $host,
                    'port' => $port,
                    'user' => $user,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // 如果所有认证方式都失败
        $error = 'SSH连接失败: 私钥和密码认证均失败';
        $this->logger->error($error, [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'hasPrivateKey' => !empty($privateKey),
            'hasPassword' => !empty($password),
        ]);
        throw new \RuntimeException($error);
    }

    /**
     * 创建基础SSH连接对象
     */
    private function createBasicSshConnection(string $host, int $port): SSH2
    {
        return new SSH2($host, $port);
    }

    /**
     * 配置SSH连接
     */
    private function configureSshConnection(SSH2 $ssh, string $host, int $port, string $user, string $authMethod): void
    {
        // 设置超时为0（无超时）
        $ssh->setTimeout(0);

        $this->logger->debug('SSH连接建立成功', [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'authMethod' => $authMethod,
        ]);
    }

    /**
     * 切换到root用户
     */
    private function switchToRootUser(SSH2 $ssh, Node $node): void
    {
        try {
            // 执行切换到root账号的命令
            $ssh->write("sudo su -\n");

            // 使用较短的超时时间来检查响应
            $ssh->setTimeout(self::SSH_READ_TIMEOUT);

            // 使用轮询方式读取输出，避免长时间等待
            $startTime = time();
            $output = '';

            // 最多等待设定的最大时间
            while ((time() - $startTime) < self::MAX_WAIT_TIME) {
                // 尝试读取小块数据，不使用正则匹配
                $temp = $ssh->read();
                if (!empty($temp)) {
                    $output .= $temp;
                    // 检查是否已经收到密码提示或root提示
                    if (preg_match('/[Pp]assword|密码|口令|认证/i', $output)) {
                        $ssh->write("{$node->getSshPassword()}\n"); // 输入sudo密码
                        $this->waitForRootPrompt($ssh);
                        break;
                    } else if (preg_match('/root@|#\s*$/', $output)) {
                        // 已经是root用户或直接切换成功
                        break;
                    }
                }
                // 短暂等待减少CPU使用
                usleep(self::POLLING_INTERVAL);
            }

            $this->logger->debug('SSH响应输出', ['output' => $output]);

            // 恢复无超时设置
            $ssh->setTimeout(0);
        } catch (\Exception $e) {
            $this->logger->warning('切换到root用户时出错: ' . $e->getMessage(), ['node' => $node->getId()]);
            // 失败后尝试继续，使用当前用户执行命令
        }
    }

    /**
     * 等待root提示符
     */
    private function waitForRootPrompt(SSH2 $ssh): void
    {
        // 继续读取直到出现root提示符
        $rootOutput = '';
        $startRootTime = time();
        $foundRoot = false;

        while ((time() - $startRootTime) < self::MAX_WAIT_TIME && !$foundRoot) {
            $temp = $ssh->read();
            if (!empty($temp)) {
                $rootOutput .= $temp;
                if (preg_match('/root@|#\s*$/', $rootOutput)) {
                    $foundRoot = true;
                }
            }
            // 短暂等待减少CPU使用
            usleep(self::POLLING_INTERVAL);
        }
    }

    /**
     * 执行SSH命令
     */
    private function execSshCommand(SSH2 $ssh, string $command, ?string $workingDirectory = null, bool $useSudo = false, ?Node $node = null): string
    {
        // 构建完整的命令，包括工作目录切换
        $fullCommand = $command;

        // 如果有工作目录，将cd命令和实际命令组合
        if ($workingDirectory !== null) {
            $fullCommand = "cd {$workingDirectory} && {$command}";
        }

        // 根据是否需要sudo执行不同的命令
        if ($useSudo) {
            if ($node && 'root' !== $node->getSshUser()) {
                if ($node->getSshPassword()) {
                    return $ssh->exec("echo '{$node->getSshPassword()}' | sudo -S {$fullCommand}");
                } else {
                    return $ssh->exec("sudo -S {$fullCommand}");
                }
            }
        }

        // 直接执行命令
        return $ssh->exec($fullCommand);
    }

    /**
     * 执行指定的远程命令
     */
    public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
    {
        if (!$command->isEnabled()) {
            $this->logger->warning('尝试执行已禁用的命令', ['command' => $command]);
            return $command;
        }

        $command->setStatus(CommandStatus::RUNNING);
        $command->setExecutedAt(new DateTime());
        $this->entityManager->flush();

        $node = $command->getNode();

        if (null === $ssh) {
            try {
                $ssh = $this->createSshConnection($node, $command->isUseSudo());
            } catch (\Exception $e) {
                $command->setStatus(CommandStatus::FAILED);
                $command->setResult('SSH连接失败: ' . $e->getMessage());
                $this->entityManager->flush();

                $this->logger->error('执行命令时SSH连接失败', [
                    'command' => $command,
                    'error' => $e->getMessage(),
                ]);

                return $command;
            }
        }

        $timer = new Timer();
        $timer->start();

        try {
            $result = $this->execSshCommand(
                $ssh,
                $command->getCommand(),
                $command->getWorkingDirectory(),
                $command->isUseSudo(),
                $node
            );

            $duration = $timer->stop();
            $command->setExecutionTime($duration->asSeconds());
            $command->setResult($result);
            $command->setStatus(CommandStatus::COMPLETED);

            $this->logger->info('命令执行成功', [
                'command' => $command,
                'duration' => $duration->asString(),
            ]);
        } catch (\Exception $e) {
            $duration = $timer->stop();
            $command->setExecutionTime($duration->asSeconds());
            $command->setResult('执行失败: ' . $e->getMessage());
            $command->setStatus(CommandStatus::FAILED);

            $this->logger->error('命令执行失败', [
                'command' => $command,
                'error' => $e->getMessage(),
                'duration' => $duration->asString(),
            ]);
        } finally {
            $this->entityManager->flush();
        }

        return $command;
    }

    /**
     * 发送异步执行命令消息
     */
    public function scheduleCommand(RemoteCommand $command): void
    {
        $message = new RemoteCommandExecuteMessage($command->getId());
        $this->messageBus->dispatch($message);

        $this->logger->info('命令已加入执行队列', ['command' => $command]);
    }

    /**
     * 根据ID查找命令
     */
    public function findById(string $id): ?RemoteCommand
    {
        return $this->remoteCommandRepository->find($id);
    }

    /**
     * 查找节点上待执行的命令
     */
    public function findPendingCommandsByNode(Node $node): array
    {
        return $this->remoteCommandRepository->findPendingCommandsByNode($node);
    }

    /**
     * 查找所有待执行的命令
     */
    public function findAllPendingCommands(): array
    {
        return $this->remoteCommandRepository->findAllPendingCommands();
    }

    /**
     * 按标签查找命令
     */
    public function findByTags(array $tags): array
    {
        return $this->remoteCommandRepository->findByTags($tags);
    }

    /**
     * 取消命令执行
     */
    public function cancelCommand(RemoteCommand $command): RemoteCommand
    {
        if ($command->getStatus() === CommandStatus::PENDING) {
            $command->setStatus(CommandStatus::CANCELED);
            $this->entityManager->flush();

            $this->logger->info('命令已取消', ['command' => $command]);
        }

        return $command;
    }

    /**
     * 获取Repository实例
     */
    public function getRepository(): RemoteCommandRepository
    {
        return $this->remoteCommandRepository;
    }
}
