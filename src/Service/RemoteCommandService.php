<?php

namespace ServerSshCommandBundle\Service;

use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Timer\Timer;
use ServerNodeBundle\Entity\Node;
use ServerSshCommandBundle\Entity\RemoteCommand;
use ServerSshCommandBundle\Enum\CommandStatus;
use ServerSshCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerSshCommandBundle\Repository\RemoteCommandRepository;
use Symfony\Component\Messenger\MessageBusInterface;

class RemoteCommandService
{
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
    private function createSshConnection(Node $node): SSH2
    {
        $ssh = new SSH2($node->getSshHost(), $node->getSshPort());
        if (!$ssh->login($node->getSshUser(), $node->getSshPassword())) {
            throw new \RuntimeException('SSH连接失败: 无法登录');
        }

        // 设置超时为0（无超时）
        $ssh->setTimeout(0);

        // 如果用户不是root，需要切换到root
        if ('root' !== $node->getSshUser()) {
            // 执行切换到root账号的命令
            $ssh->write("sudo su -\n"); // 使用sudo切换到root账号
            $ssh->read('password for'); // 读取sudo密码提示
            $ssh->write("{$node->getSshPassword()}\n"); // 输入sudo密码
            $ssh->read('root@'); // 读取root账号提示符
        }

        return $ssh;
    }

    /**
     * 执行SSH命令
     */
    private function execSshCommand(SSH2 $ssh, string $command, ?string $workingDirectory = null, bool $useSudo = false, Node $node = null): string
    {
        // 如果有工作目录，先切换到该目录
        if ($workingDirectory !== null) {
            $ssh->exec("cd {$workingDirectory}");
        }

        // 根据是否需要sudo执行不同的命令
        if ($useSudo) {
            if ($node && 'root' !== $node->getSshUser()) {
                if ($node->getSshPassword()) {
                    return $ssh->exec("echo '{$node->getSshPassword()}' | sudo -S {$command}");
                } else {
                    return $ssh->exec("sudo -S {$command}");
                }
            }
        }

        // 直接执行命令
        return $ssh->exec($command);
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
        $sshConnOwned = false;

        if (null === $ssh) {
            try {
                $ssh = $this->createSshConnection($node);
                $sshConnOwned = true;
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
}
