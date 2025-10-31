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
 * 远程命令数据填充
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class RemoteCommandFixtures extends Fixture implements FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const SYSTEM_UPDATE_COMMAND = 'system-update-command';
    public const SYSTEM_RESTART_COMMAND = 'system-restart-command';
    public const NGINX_RESTART_COMMAND = 'nginx-restart-command';
    public const DISK_SPACE_COMMAND = 'disk-space-command';

    public function load(ObjectManager $manager): void
    {
        // 创建测试节点
        $linuxNode = new Node();
        $linuxNode->setName('测试服务器');
        $linuxNode->setSshHost('127.0.0.1');
        $linuxNode->setSshUser('root');
        $linuxNode->setSshPort(22);
        $linuxNode->setValid(true);
        $manager->persist($linuxNode);

        // 创建系统更新命令
        $updateCommand = new RemoteCommand();
        $updateCommand->setNode($linuxNode);
        $updateCommand->setName('系统更新');
        $updateCommand->setCommand('apt update && apt upgrade -y');
        $updateCommand->setWorkingDirectory('/root');
        $updateCommand->setUseSudo(true);
        $updateCommand->setEnabled(true);
        $updateCommand->setStatus(CommandStatus::PENDING);
        $updateCommand->setTimeout(600);
        $updateCommand->setTags(['system', 'maintenance']);

        $manager->persist($updateCommand);
        $this->addReference(self::SYSTEM_UPDATE_COMMAND, $updateCommand);

        // 创建系统重启命令
        $restartCommand = new RemoteCommand();
        $restartCommand->setNode($linuxNode);
        $restartCommand->setName('系统重启');
        $restartCommand->setCommand('reboot');
        $restartCommand->setWorkingDirectory('/root');
        $restartCommand->setUseSudo(true);
        $restartCommand->setEnabled(true);
        $restartCommand->setStatus(CommandStatus::PENDING);
        $restartCommand->setTimeout(60);
        $restartCommand->setTags(['system', 'critical']);

        $manager->persist($restartCommand);
        $this->addReference(self::SYSTEM_RESTART_COMMAND, $restartCommand);

        // 创建Nginx重启命令
        $nginxCommand = new RemoteCommand();
        $nginxCommand->setNode($linuxNode);
        $nginxCommand->setName('重启Nginx');
        $nginxCommand->setCommand('systemctl restart nginx');
        $nginxCommand->setWorkingDirectory('/root');
        $nginxCommand->setUseSudo(true);
        $nginxCommand->setEnabled(true);
        $nginxCommand->setStatus(CommandStatus::COMPLETED);
        $nginxCommand->setResult(
            "● nginx.service - A high performance web server and a reverse proxy server\n" .
            "   Loaded: loaded (/lib/systemd/system/nginx.service; enabled; vendor preset: enabled)\n" .
            '   Active: active (running) since Wed 2023-08-16 14:22:33 UTC; 2s ago'
        );
        $nginxCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 14:22:30'));
        $nginxCommand->setExecutionTime(2.53);
        $nginxCommand->setTimeout(120);
        $nginxCommand->setTags(['service', 'web']);

        $manager->persist($nginxCommand);
        $this->addReference(self::NGINX_RESTART_COMMAND, $nginxCommand);

        // 创建磁盘空间查询命令
        $diskCommand = new RemoteCommand();
        $diskCommand->setNode($linuxNode);
        $diskCommand->setName('查询磁盘空间');
        $diskCommand->setCommand('df -h');
        $diskCommand->setWorkingDirectory('/root');
        $diskCommand->setUseSudo(false);
        $diskCommand->setEnabled(true);
        $diskCommand->setStatus(CommandStatus::COMPLETED);
        $diskCommand->setResult(
            "Filesystem      Size  Used Avail Use% Mounted on\n" .
            "udev            7.9G     0  7.9G   0% /dev\n" .
            "tmpfs           1.6G  2.3M  1.6G   1% /run\n" .
            "/dev/sda1        80G   25G   51G  34% /\n" .
            "tmpfs           7.9G     0  7.9G   0% /dev/shm\n" .
            'tmpfs           5.0M     0  5.0M   0% /run/lock'
        );
        $diskCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 15:10:12'));
        $diskCommand->setExecutionTime(0.32);
        $diskCommand->setTimeout(60);
        $diskCommand->setTags(['system', 'monitoring']);

        $manager->persist($diskCommand);
        $this->addReference(self::DISK_SPACE_COMMAND, $diskCommand);

        $manager->flush();
    }

    /**
     * 返回此 Fixture 所属的组名称
     */
    public static function getGroups(): array
    {
        return ['server-command', 'system-commands'];
    }
}
