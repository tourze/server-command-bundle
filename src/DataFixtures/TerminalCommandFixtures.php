<?php

namespace ServerCommandBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerNodeBundle\DataFixtures\NodeFixtures;
use ServerNodeBundle\Entity\Node;

/**
 * 终端命令演示数据填充
 */
class TerminalCommandFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const TERMINAL_LS_COMMAND = 'terminal-ls-command';
    public const TERMINAL_PS_COMMAND = 'terminal-ps-command';
    public const TERMINAL_DF_COMMAND = 'terminal-df-command';

    public function load(ObjectManager $manager): void
    {
        /** @var Node $linuxNode */
        $linuxNode = $this->getReference(NodeFixtures::REFERENCE_NODE_1, Node::class);

        // 创建终端命令：ls -la
        $lsCommand = new RemoteCommand();
        $lsCommand->setNode($linuxNode)
            ->setName('终端命令: ls -la')
            ->setCommand('ls -la')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("total 24\ndrwx------  5 root root 4096 Aug 16 10:30 .\ndrwxr-xr-x 19 root root 4096 Aug 15 14:22 ..\n-rw-------  1 root root 1234 Aug 16 09:15 .bash_history\n-rw-r--r--  1 root root  570 Jan 31  2010 .bashrc\ndrwxr-xr-x  3 root root 4096 Aug 15 14:25 .local\n-rw-r--r--  1 root root  148 Aug 17  2015 .profile")
            ->setExecutedAt(new \DateTime('2023-08-16 16:30:15'))
            ->setExecutionTime(0.12)
            ->setTimeout(30)
            ->setTags(['terminal']);

        $manager->persist($lsCommand);
        $this->addReference(self::TERMINAL_LS_COMMAND, $lsCommand);

        // 创建终端命令：ps aux
        $psCommand = new RemoteCommand();
        $psCommand->setNode($linuxNode)
            ->setName('终端命令: ps aux')
            ->setCommand('ps aux | head -10')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND\nroot         1  0.0  0.1 225448  9428 ?        Ss   Aug15   0:01 /sbin/init\nroot         2  0.0  0.0      0     0 ?        S    Aug15   0:00 [kthreadd]\nroot         3  0.0  0.0      0     0 ?        I<   Aug15   0:00 [rcu_gp]\nroot         4  0.0  0.0      0     0 ?        I<   Aug15   0:00 [rcu_par_gp]\nroot         6  0.0  0.0      0     0 ?        I<   Aug15   0:00 [kworker/0:0H-events_highpri]\nroot         9  0.0  0.0      0     0 ?        I<   Aug15   0:00 [mm_percpu_wq]\nroot        10  0.0  0.0      0     0 ?        S    Aug15   0:00 [rcu_tasks_rude_]\nroot        11  0.0  0.0      0     0 ?        S    Aug15   0:00 [rcu_tasks_trace]\nroot        12  0.0  0.0      0     0 ?        S    Aug15   0:00 [ksoftirqd/0]")
            ->setExecutedAt(new \DateTime('2023-08-16 16:31:22'))
            ->setExecutionTime(0.08)
            ->setTimeout(30)
            ->setTags(['terminal']);

        $manager->persist($psCommand);
        $this->addReference(self::TERMINAL_PS_COMMAND, $psCommand);

        // 创建终端命令：df -h
        $dfCommand = new RemoteCommand();
        $dfCommand->setNode($linuxNode)
            ->setName('终端命令: df -h')
            ->setCommand('df -h')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("Filesystem      Size  Used Avail Use% Mounted on\n/dev/sda1        40G   12G   26G  32% /\ntmpfs           2.0G     0  2.0G   0% /dev/shm\ntmpfs           798M  1.1M  797M   1% /run\ntmpfs           5.0M     0  5.0M   0% /run/lock\n/dev/sda15      105M  6.1M   99M   6% /boot/efi\ntmpfs           400M  4.0K  400M   1% /run/user/1000")
            ->setExecutedAt(new \DateTime('2023-08-16 16:32:10'))
            ->setExecutionTime(0.05)
            ->setTimeout(30)
            ->setTags(['terminal']);

        $manager->persist($dfCommand);
        $this->addReference(self::TERMINAL_DF_COMMAND, $dfCommand);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            NodeFixtures::class,
        ];
    }

    /**
     * 返回此 Fixture 所属的组名称
     */
    public static function getGroups(): array
    {
        return ['server-command', 'terminal-commands'];
    }
} 