<?php

namespace ServerCommandBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\DependencyInjection\Attribute\When;

/**
 * 终端命令演示数据填充
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class TerminalCommandFixtures extends Fixture implements FixtureGroupInterface
{
    public const TERMINAL_LS_COMMAND = 'terminal-ls-command';
    public const TERMINAL_PS_COMMAND = 'terminal-ps-command';
    public const TERMINAL_DF_COMMAND = 'terminal-df-command';

    public function load(ObjectManager $manager): void
    {
        // 创建终端命令测试节点
        $linuxNode = new Node();
        $linuxNode->setName('Linux终端测试节点');
        $linuxNode->setHostname('terminal-linux-server');
        $linuxNode->setSshHost('192.168.1.103');
        $linuxNode->setSshPort(22);
        $linuxNode->setSshUser('root');
        $linuxNode->setValid(true);
        $linuxNode->setTags(['terminal', 'linux']);

        $manager->persist($linuxNode);

        // 创建终端命令：ls -la
        $lsCommand = new RemoteCommand();
        $lsCommand->setNode($linuxNode);
        $lsCommand->setName('终端命令: ls -la');
        $lsCommand->setCommand('ls -la');
        $lsCommand->setWorkingDirectory('/root');
        $lsCommand->setUseSudo(false);
        $lsCommand->setEnabled(true);
        $lsCommand->setStatus(CommandStatus::COMPLETED);
        $lsCommand->setResult(
            "total 24\n" .
            "drwx------  5 root root 4096 Aug 16 10:30 .\n" .
            "drwxr-xr-x 19 root root 4096 Aug 15 14:22 ..\n" .
            "-rw-------  1 root root 1234 Aug 16 09:15 .bash_history\n" .
            "-rw-r--r--  1 root root  570 Jan 31  2010 .bashrc\n" .
            "drwxr-xr-x  3 root root 4096 Aug 15 14:25 .local\n" .
            '-rw-r--r--  1 root root  148 Aug 17  2015 .profile'
        );
        $lsCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:30:15'));
        $lsCommand->setExecutionTime(0.12);
        $lsCommand->setTimeout(30);
        $lsCommand->setTags(['terminal']);

        $manager->persist($lsCommand);
        $this->addReference(self::TERMINAL_LS_COMMAND, $lsCommand);

        // 创建终端命令：ps aux
        $psCommand = new RemoteCommand();
        $psCommand->setNode($linuxNode);
        $psCommand->setName('终端命令: ps aux');
        $psCommand->setCommand('ps aux | head -10');
        $psCommand->setWorkingDirectory('/root');
        $psCommand->setUseSudo(false);
        $psCommand->setEnabled(true);
        $psCommand->setStatus(CommandStatus::COMPLETED);
        $psCommand->setResult(
            "USER       PID %CPU %MEM    VSZ   RSS TTY      STAT START   TIME COMMAND\n" .
            "root         1  0.0  0.1 225448  9428 ?        Ss   Aug15   0:01 /sbin/init\n" .
            "root         2  0.0  0.0      0     0 ?        S    Aug15   0:00 [kthreadd]\n" .
            "root         3  0.0  0.0      0     0 ?        I<   Aug15   0:00 [rcu_gp]\n" .
            "root         4  0.0  0.0      0     0 ?        I<   Aug15   0:00 [rcu_par_gp]\n" .
            "root         6  0.0  0.0      0     0 ?        I<   Aug15   0:00 [kworker/0:0H-events_highpri]\n" .
            "root         9  0.0  0.0      0     0 ?        I<   Aug15   0:00 [mm_percpu_wq]\n" .
            "root        10  0.0  0.0      0     0 ?        S    Aug15   0:00 [rcu_tasks_rude_]\n" .
            "root        11  0.0  0.0      0     0 ?        S    Aug15   0:00 [rcu_tasks_trace]\n" .
            'root        12  0.0  0.0      0     0 ?        S    Aug15   0:00 [ksoftirqd/0]'
        );
        $psCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:31:22'));
        $psCommand->setExecutionTime(0.08);
        $psCommand->setTimeout(30);
        $psCommand->setTags(['terminal']);

        $manager->persist($psCommand);
        $this->addReference(self::TERMINAL_PS_COMMAND, $psCommand);

        // 创建终端命令：df -h
        $dfCommand = new RemoteCommand();
        $dfCommand->setNode($linuxNode);
        $dfCommand->setName('终端命令: df -h');
        $dfCommand->setCommand('df -h');
        $dfCommand->setWorkingDirectory('/root');
        $dfCommand->setUseSudo(false);
        $dfCommand->setEnabled(true);
        $dfCommand->setStatus(CommandStatus::COMPLETED);
        $dfCommand->setResult(
            "Filesystem      Size  Used Avail Use% Mounted on\n" .
            "/dev/sda1        40G   12G   26G  32% /\n" .
            "tmpfs           2.0G     0  2.0G   0% /dev/shm\n" .
            "tmpfs           798M  1.1M  797M   1% /run\n" .
            "tmpfs           5.0M     0  5.0M   0% /run/lock\n" .
            "/dev/sda15      105M  6.1M   99M   6% /boot/efi\n" .
            'tmpfs           400M  4.0K  400M   1% /run/user/1000'
        );
        $dfCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:32:10'));
        $dfCommand->setExecutionTime(0.05);
        $dfCommand->setTimeout(30);
        $dfCommand->setTags(['terminal']);

        $manager->persist($dfCommand);
        $this->addReference(self::TERMINAL_DF_COMMAND, $dfCommand);

        $manager->flush();
    }

    /**
     * 返回此 Fixture 所属的组名称
     */
    public static function getGroups(): array
    {
        return ['server-command', 'terminal-commands'];
    }
}
