<?php

namespace ServerCommandBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use ServerNodeBundle\Entity\Node;

#[WithMonologChannel(channel: 'server_command')]
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
        ?Node $node = null,
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
        Node $node,
    ): string {
        $this->logger->debug('执行sudo命令', [
            'command' => $command,
            'workingDirectory' => $workingDirectory,
            'node' => $node->getId(),
        ]);

        $sudoCommand = $this->buildSudoCommand($command, $workingDirectory, $node);
        $result = $ssh->exec($sudoCommand);

        return is_string($result) ? $result : '';
    }

    /**
     * 构建sudo命令字符串
     */
    private function buildSudoCommand(string $command, ?string $workingDirectory, Node $node): string
    {
        $finalCommand = $this->prepareFinalCommand($command, $workingDirectory);

        return $this->addSudoPrefix($finalCommand, $node);
    }

    /**
     * 准备最终执行的命令
     */
    private function prepareFinalCommand(string $command, ?string $workingDirectory): string
    {
        if (null !== $workingDirectory && '' !== $workingDirectory) {
            return 'bash -c ' . escapeshellarg("cd {$workingDirectory} && {$command}");
        }

        return $command;
    }

    /**
     * 添加sudo前缀和密码处理
     */
    private function addSudoPrefix(string $command, Node $node): string
    {
        $password = $node->getSshPassword();

        if (null !== $password && '' !== $password) {
            return "printf '%s\\n\\n' '{$password}' | sudo -S {$command}";
        }

        return "sudo -S {$command}";
    }

    /**
     * 正常执行命令（不使用sudo）
     */
    private function executeNormal(
        SSH2 $ssh,
        string $command,
        ?string $workingDirectory,
    ): string {
        $fullCommand = $command;

        // 如果有工作目录，将cd命令和实际命令组合
        if (null !== $workingDirectory) {
            $fullCommand = "cd {$workingDirectory} && {$command}";
        }

        // 直接执行命令
        $result = $ssh->exec($fullCommand);

        return is_string($result) ? $result : '';
    }
}
