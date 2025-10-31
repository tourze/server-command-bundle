<?php

namespace ServerCommandBundle\Command;

use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;
use ServerNodeBundle\Repository\NodeRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: self::NAME,
    description: '执行远程命令',
)]
class RemoteCommandExecuteCommand extends Command
{
    public const NAME = 'server-node:remote-command:execute';

    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly NodeRepository $nodeRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('command-id', InputArgument::OPTIONAL, '命令ID')
            ->addOption('node-id', null, InputOption::VALUE_REQUIRED, '节点ID')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, '命令名称')
            ->addOption('command', null, InputOption::VALUE_REQUIRED, '命令内容')
            ->addOption('working-dir', null, InputOption::VALUE_OPTIONAL, '工作目录')
            ->addOption('sudo', null, InputOption::VALUE_NONE, '是否使用sudo执行')
            ->addOption('timeout', null, InputOption::VALUE_OPTIONAL, '超时时间(秒)', 300)
            ->addOption('execute-all-pending', null, InputOption::VALUE_NONE, '执行所有待执行的命令')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // 执行所有待执行的命令
        if (true === $input->getOption('execute-all-pending')) {
            return $this->executeAllPendingCommands($io);
        }

        // 执行指定ID的命令
        $commandId = $input->getArgument('command-id');
        if (null !== $commandId && '' !== $commandId) {
            assert(is_string($commandId));

            return $this->executeCommandById($commandId, $io);
        }

        // 创建并执行新命令
        $nodeId = $input->getOption('node-id');
        $name = $input->getOption('name');
        $command = $input->getOption('command');

        if (
            null === $nodeId || '' === $nodeId
            || null === $name || '' === $name
            || null === $command || '' === $command
        ) {
            $io->error('必须指定节点ID、命令名称和命令内容，或者提供已存在的命令ID');

            return Command::INVALID;
        }

        return $this->createAndExecuteCommand($input, $io);
    }

    private function executeCommandById(string $commandId, SymfonyStyle $io): int
    {
        $command = $this->remoteCommandService->findById($commandId);
        if (null === $command) {
            $io->error(sprintf('未找到ID为 %s 的命令', $commandId));

            return Command::FAILURE;
        }

        $io->section(sprintf('执行命令: %s', $command->getName()));

        $this->remoteCommandService->executeCommand($command);

        $this->outputCommandResult($command, $io);

        return CommandStatus::COMPLETED === $command->getStatus()
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function createAndExecuteCommand(InputInterface $input, SymfonyStyle $io): int
    {
        $nodeId = $input->getOption('node-id');
        assert(is_string($nodeId) || is_int($nodeId));
        $node = $this->nodeRepository->find($nodeId);

        if (null === $node) {
            $io->error(sprintf('未找到ID为 %s 的节点', (string) $nodeId));

            return Command::FAILURE;
        }

        $name = $input->getOption('name');
        $commandStr = $input->getOption('command');
        $workingDir = $input->getOption('working-dir');
        $sudo = $input->getOption('sudo');
        $timeout = $input->getOption('timeout');

        assert(is_string($name));
        assert(is_string($commandStr));
        assert(is_string($workingDir) || null === $workingDir);
        assert(is_bool($sudo) || null === $sudo);
        assert(is_string($timeout) || is_int($timeout) || null === $timeout);

        $io->section(sprintf('在节点 %s 上创建并执行命令: %s', $node->getName(), $name));

        $command = $this->remoteCommandService->createCommand(
            $node,
            $name,
            $commandStr,
            $workingDir,
            $sudo,
            (int) $timeout
        );

        $this->remoteCommandService->executeCommand($command);

        $this->outputCommandResult($command, $io);

        return CommandStatus::COMPLETED === $command->getStatus()
            ? Command::SUCCESS
            : Command::FAILURE;
    }

    private function executeAllPendingCommands(SymfonyStyle $io): int
    {
        $commands = $this->remoteCommandService->findAllPendingCommands();

        if (0 === count($commands)) {
            $io->success('没有待执行的命令');

            return Command::SUCCESS;
        }

        $io->section(sprintf('执行 %d 个待执行命令', count($commands)));

        $success = 0;
        $failed = 0;

        foreach ($commands as $command) {
            $io->writeln(sprintf('执行命令: %s (ID: %s)', $command->getName(), $command->getId()));

            $this->remoteCommandService->executeCommand($command);

            if (CommandStatus::COMPLETED === $command->getStatus()) {
                ++$success;
            } else {
                ++$failed;
            }

            $this->outputCommandResult($command, $io);
            $io->newLine();
        }

        $io->success(sprintf('命令执行完成: %d 个成功, %d 个失败', $success, $failed));

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function outputCommandResult(RemoteCommand $command, SymfonyStyle $io): void
    {
        $status = $command->getStatus();
        $statusText = match ($status) {
            CommandStatus::COMPLETED => '<info>成功</info>',
            CommandStatus::FAILED => '<error>失败</error>',
            CommandStatus::TIMEOUT => '<error>超时</error>',
            CommandStatus::CANCELED => '<comment>已取消</comment>',
            null => '<comment>未知状态</comment>',
            default => $status->value,
        };

        $io->writeln(sprintf('状态: %s', $statusText));
        $io->writeln(sprintf('执行时间: %s', $command->getExecutedAt()?->format('Y-m-d H:i:s') ?? '未执行'));

        if (null !== $command->getExecutionTime()) {
            $io->writeln(sprintf('执行耗时: %.2f 秒', $command->getExecutionTime()));
        }

        if (null !== $command->getResult() && '' !== $command->getResult()) {
            $io->writeln('执行结果:');
            $io->writeln($command->getResult());
        }
    }
}
