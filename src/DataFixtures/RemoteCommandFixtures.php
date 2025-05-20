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
 * 远程命令数据填充
 */
class RemoteCommandFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const SYSTEM_UPDATE_COMMAND = 'system-update-command';
    public const SYSTEM_RESTART_COMMAND = 'system-restart-command';
    public const NGINX_RESTART_COMMAND = 'nginx-restart-command';
    public const DISK_SPACE_COMMAND = 'disk-space-command';

    public function load(ObjectManager $manager): void
    {
        // 使用来自 NodeFixtures 的引用
        /** @var Node $linuxNode */
        $linuxNode = $this->getReference(NodeFixtures::REFERENCE_NODE_1, Node::class);
        
        // 创建系统更新命令
        $updateCommand = new RemoteCommand();
        $updateCommand->setNode($linuxNode)
            ->setName('系统更新')
            ->setCommand('apt update && apt upgrade -y')
            ->setWorkingDirectory('/root')
            ->setUseSudo(true)
            ->setEnabled(true)
            ->setStatus(CommandStatus::PENDING)
            ->setTimeout(600)
            ->setTags(['system', 'maintenance']);
        
        $manager->persist($updateCommand);
        $this->addReference(self::SYSTEM_UPDATE_COMMAND, $updateCommand);
        
        // 创建系统重启命令
        $restartCommand = new RemoteCommand();
        $restartCommand->setNode($linuxNode)
            ->setName('系统重启')
            ->setCommand('reboot')
            ->setWorkingDirectory('/root')
            ->setUseSudo(true)
            ->setEnabled(true)
            ->setStatus(CommandStatus::PENDING)
            ->setTimeout(60)
            ->setTags(['system', 'critical']);
        
        $manager->persist($restartCommand);
        $this->addReference(self::SYSTEM_RESTART_COMMAND, $restartCommand);
        
        // 创建Nginx重启命令
        $nginxCommand = new RemoteCommand();
        $nginxCommand->setNode($linuxNode)
            ->setName('重启Nginx')
            ->setCommand('systemctl restart nginx')
            ->setWorkingDirectory('/root')
            ->setUseSudo(true)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("● nginx.service - A high performance web server and a reverse proxy server\n   Loaded: loaded (/lib/systemd/system/nginx.service; enabled; vendor preset: enabled)\n   Active: active (running) since Wed 2023-08-16 14:22:33 UTC; 2s ago")
            ->setExecutedAt(new \DateTime('2023-08-16 14:22:30'))
            ->setExecutionTime(2.53)
            ->setTimeout(120)
            ->setTags(['service', 'web']);

        $manager->persist($nginxCommand);
        $this->addReference(self::NGINX_RESTART_COMMAND, $nginxCommand);

        // 创建磁盘空间查询命令
        $diskCommand = new RemoteCommand();
        $diskCommand->setNode($linuxNode)
            ->setName('查询磁盘空间')
            ->setCommand('df -h')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("Filesystem      Size  Used Avail Use% Mounted on\nudev            7.9G     0  7.9G   0% /dev\ntmpfs           1.6G  2.3M  1.6G   1% /run\n/dev/sda1        80G   25G   51G  34% /\ntmpfs           7.9G     0  7.9G   0% /dev/shm\ntmpfs           5.0M     0  5.0M   0% /run/lock")
            ->setExecutedAt(new \DateTime('2023-08-16 15:10:12'))
            ->setExecutionTime(0.32)
            ->setTimeout(60)
            ->setTags(['system', 'monitoring']);

        $manager->persist($diskCommand);
        $this->addReference(self::DISK_SPACE_COMMAND, $diskCommand);

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
        return ['server-command', 'system-commands'];
    }
}
