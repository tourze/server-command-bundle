<?php

namespace ServerCommandBundle\MessageHandler;

use Psr\Log\LoggerInterface;
use ServerCommandBundle\Message\RemoteCommandExecuteMessage;
use ServerCommandBundle\Service\RemoteCommandService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class RemoteCommandExecuteHandler
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemoteCommandExecuteMessage $message): void
    {
        $commandId = $message->getCommandId();
        $this->logger->info('处理远程命令执行消息', ['commandId' => $commandId]);

        $command = $this->remoteCommandService->findById($commandId);
        if (null === $command) {
            $this->logger->warning('未找到指定的远程命令', ['commandId' => $commandId]);
            return;
        }

        $this->remoteCommandService->executeCommand($command);
    }
}
