<?php

namespace ServerCommandBundle\Message;

use Tourze\AsyncContracts\AsyncMessageInterface;

class RemoteCommandExecuteMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $commandId,
    ) {
    }

    public function getCommandId(): string
    {
        return $this->commandId;
    }
}
