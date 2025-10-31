<?php

namespace ServerCommandBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Attribute\WithMonologChannel;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use SebastianBergmann\Timer\Timer;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Messenger\MessageBusInterface;

#[WithMonologChannel(channel: 'server_command')]
class RemoteCommandService
{
    public function __construct(
        private readonly RemoteCommandRepository $remoteCommandRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
        private readonly SshConnectionService $sshConnectionService,
        private readonly SshCommandExecutor $sshCommandExecutor,
    ) {
    }

    /**
     * 创建新的远程命令
     *
     * @param string[]|null $tags
     */
    public function createCommand(
        Node $node,
        string $name,
        string $command,
        ?string $workingDirectory = null,
        ?bool $useSudo = false,
        ?int $timeout = 300,
        ?array $tags = null,
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
     * 在SSH连接上执行命令
     *
     * @deprecated 使用 SshCommandExecutor::execute 代替
     */
    public function execSshCommand(
        SSH2 $ssh,
        string $command,
        ?string $workingDirectory = null,
        bool $useSudo = false,
        ?Node $node = null,
    ): string {
        return $this->sshCommandExecutor->execute($ssh, $command, $workingDirectory, $useSudo, $node);
    }

    /**
     * 执行指定的远程命令
     */
    public function executeCommand(RemoteCommand $command, ?SSH2 $ssh = null): RemoteCommand
    {
        if (false === $command->isEnabled()) {
            $this->logger->warning('尝试执行已禁用的命令', ['command' => $command]);

            return $command;
        }

        $command->setStatus(CommandStatus::RUNNING);
        $command->setExecutedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        $node = $command->getNode();

        if (null === $ssh) {
            try {
                $ssh = $this->sshConnectionService->createConnection($node, $command->isUseSudo() ?? false);
            } catch (\Throwable $e) {
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
            $result = $this->sshCommandExecutor->execute(
                $ssh,
                $command->getCommand(),
                $command->getWorkingDirectory(),
                $command->isUseSudo() ?? false,
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
        } catch (\Throwable $e) {
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
        $message = new RemoteCommandExecuteMessage((string) $command->getId());
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
     *
     * @return RemoteCommand[]
     */
    public function findPendingCommandsByNode(Node $node): array
    {
        return $this->remoteCommandRepository->findPendingCommandsByNode($node);
    }

    /**
     * 查找所有待执行的命令
     *
     * @return RemoteCommand[]
     */
    public function findAllPendingCommands(): array
    {
        return $this->remoteCommandRepository->findAllPendingCommands();
    }

    /**
     * 按标签查找命令
     *
     * @param string[] $tags
     *
     * @return RemoteCommand[]
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
        if (CommandStatus::PENDING === $command->getStatus()) {
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
