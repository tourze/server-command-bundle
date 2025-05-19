<?php

namespace ServerSshCommandBundle\Message;

class RemoteCommandExecuteMessage
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
