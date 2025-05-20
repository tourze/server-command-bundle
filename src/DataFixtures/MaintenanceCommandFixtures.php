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
 * 维护相关的远程命令数据填充
 */
class MaintenanceCommandFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const CLEANUP_LOGS_COMMAND = 'cleanup-logs-command';
    public const MYSQL_BACKUP_COMMAND = 'mysql-backup-command';
    public const SERVICE_CHECK_COMMAND = 'service-check-command';

    public function load(ObjectManager $manager): void
    {
        // 使用来自 NodeFixtures 的引用
        /** @var Node $backupNode */
        $backupNode = $this->getReference(NodeFixtures::REFERENCE_NODE_2, Node::class);

        // 创建日志清理命令
        $cleanupCommand = new RemoteCommand();
        $cleanupCommand->setNode($backupNode)
            ->setName('清理日志文件')
            ->setCommand('find /var/log -type f -name "*.log" -size +100M -delete')
            ->setWorkingDirectory('/var/log')
            ->setUseSudo(true)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("已删除大于100MB的日志文件")
            ->setExecutedAt(new \DateTime('2023-08-15 02:00:00'))
            ->setExecutionTime(5.21)
            ->setTimeout(300)
            ->setTags(['maintenance', 'cleanup']);
        
        $manager->persist($cleanupCommand);
        $this->addReference(self::CLEANUP_LOGS_COMMAND, $cleanupCommand);
        
        // 创建MySQL备份命令
        $backupCommand = new RemoteCommand();
        $backupCommand->setNode($backupNode)
            ->setName('MySQL数据库备份')
            ->setCommand('mysqldump -u root -p"password" --all-databases > /backup/mysql_backup_$(date +%Y%m%d).sql')
            ->setWorkingDirectory('/backup')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::FAILED)
            ->setResult("mysqldump: Got error: 1045: Access denied for user 'root'@'localhost' (using password: YES) when trying to connect")
            ->setExecutedAt(new \DateTime('2023-08-15 03:00:00'))
            ->setExecutionTime(0.45)
            ->setTimeout(1800)
            ->setTags(['backup', 'database', 'mysql']);
        
        $manager->persist($backupCommand);
        $this->addReference(self::MYSQL_BACKUP_COMMAND, $backupCommand);
        
        // 创建服务检查命令
        $serviceCommand = new RemoteCommand();
        $serviceCommand->setNode($backupNode)
            ->setName('检查关键服务状态')
            ->setCommand('systemctl status nginx mysql redis')
            ->setWorkingDirectory('/root')
            ->setUseSudo(true)
            ->setEnabled(true)
            ->setStatus(CommandStatus::RUNNING)
            ->setTimeout(120)
            ->setTags(['monitoring', 'service']);
        
        $manager->persist($serviceCommand);
        $this->addReference(self::SERVICE_CHECK_COMMAND, $serviceCommand);
        
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
        return ['server-command', 'maintenance-commands'];
    }
}
