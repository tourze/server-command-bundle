<?php

namespace ServerCommandBundle\Service;

use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use ServerNodeBundle\Entity\Node;

class SshCommandExecutor
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 在SSH连接上执行命令
     */
    public function execute(
        SSH2 $ssh,
        string $command,
        ?string $workingDirectory = null,
        bool $useSudo = false,
        ?Node $node = null
    ): string {
        // 根据是否需要sudo执行不同的命令
        if ($useSudo && null !== $node && 'root' !== $node->getSshUser()) {
            return $this->executeWithSudo($ssh, $command, $workingDirectory, $node);
        }

        return $this->executeNormal($ssh, $command, $workingDirectory);
    }

    /**
     * 使用sudo执行命令
     */
    private function executeWithSudo(
        SSH2 $ssh,
        string $command,
        ?string $workingDirectory,
        Node $node
    ): string {
        $this->logger->debug('执行sudo命令', [
            'command' => $command,
            'workingDirectory' => $workingDirectory,
            'node' => $node->getId(),
        ]);

        // 使用sudo执行命令，通过shell -c来处理工作目录
        if (null !== $workingDirectory && '' !== $workingDirectory) {
            // 使用bash -c来组合cd和命令，然后用sudo执行
            $shellCommand = "cd {$workingDirectory} && {$command}";
            if (null !== $node->getSshPassword() && '' !== $node->getSshPassword()) {
                return $ssh->exec("printf '%s\\n\\n' '{$node->getSshPassword()}' | sudo -S bash -c " . escapeshellarg($shellCommand));
            } else {
                return $ssh->exec("sudo -S bash -c " . escapeshellarg($shellCommand));
            }
        } else {
            // 没有工作目录时直接sudo执行命令
            if (null !== $node->getSshPassword() && '' !== $node->getSshPassword()) {
                return $ssh->exec("printf '%s\\n\\n' '{$node->getSshPassword()}' | sudo -S {$command}");
            } else {
                return $ssh->exec("sudo -S {$command}");
            }
        }
    }

    /**
     * 正常执行命令（不使用sudo）
     */
    private function executeNormal(
        SSH2 $ssh,
        string $command,
        ?string $workingDirectory
    ): string {
        $fullCommand = $command;

        // 如果有工作目录，将cd命令和实际命令组合
        if ($workingDirectory !== null) {
            $fullCommand = "cd {$workingDirectory} && {$command}";
        }

        // 直接执行命令
        return $ssh->exec($fullCommand);
    }
}
