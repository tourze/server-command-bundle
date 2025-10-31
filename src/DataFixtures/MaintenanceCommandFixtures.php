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
 * 维护相关的远程命令数据填充
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class MaintenanceCommandFixtures extends Fixture implements FixtureGroupInterface
{
    // 使用常量定义引用名称
    public const CLEANUP_LOGS_COMMAND = 'cleanup-logs-command';
    public const MYSQL_BACKUP_COMMAND = 'mysql-backup-command';
    public const SERVICE_CHECK_COMMAND = 'service-check-command';

    public function load(ObjectManager $manager): void
    {
        // 创建维护服务器测试节点
        $backupNode = new Node();
        $backupNode->setName('维护服务器');
        $backupNode->setHostname('maintenance-server');
        $backupNode->setSshHost('192.168.1.102');
        $backupNode->setSshPort(22);
        $backupNode->setSshUser('root');
        $backupNode->setValid(true);
        $backupNode->setTags(['maintenance', 'backup']);

        $manager->persist($backupNode);

        // 创建日志清理命令
        $cleanupCommand = new RemoteCommand();
        $cleanupCommand->setNode($backupNode);
        $cleanupCommand->setName('清理日志文件');
        $cleanupCommand->setCommand('find /var/log -type f -name "*.log" -size +100M -delete');
        $cleanupCommand->setWorkingDirectory('/var/log');
        $cleanupCommand->setUseSudo(true);
        $cleanupCommand->setEnabled(true);
        $cleanupCommand->setStatus(CommandStatus::COMPLETED);
        $cleanupCommand->setResult('已删除大于100MB的日志文件');
        $cleanupCommand->setExecutedAt(new \DateTimeImmutable('2023-08-15 02:00:00'));
        $cleanupCommand->setExecutionTime(5.21);
        $cleanupCommand->setTimeout(300);
        $cleanupCommand->setTags(['maintenance', 'cleanup']);

        $manager->persist($cleanupCommand);
        $this->addReference(self::CLEANUP_LOGS_COMMAND, $cleanupCommand);

        // 创建MySQL备份命令
        $backupCommand = new RemoteCommand();
        $backupCommand->setNode($backupNode);
        $backupCommand->setName('MySQL数据库备份');
        $backupCommand->setCommand(
            'mysqldump -u root -p"password" --all-databases > ' .
            '/backup/mysql_backup_$(date +%Y%m%d).sql'
        );
        $backupCommand->setWorkingDirectory('/backup');
        $backupCommand->setUseSudo(false);
        $backupCommand->setEnabled(true);
        $backupCommand->setStatus(CommandStatus::FAILED);
        $backupCommand->setResult(
            "mysqldump: Got error: 1045: Access denied for user 'root'@'localhost' " .
            '(using password: YES) when trying to connect'
        );
        $backupCommand->setExecutedAt(new \DateTimeImmutable('2023-08-15 03:00:00'));
        $backupCommand->setExecutionTime(0.45);
        $backupCommand->setTimeout(1800);
        $backupCommand->setTags(['backup', 'database', 'mysql']);

        $manager->persist($backupCommand);
        $this->addReference(self::MYSQL_BACKUP_COMMAND, $backupCommand);

        // 创建服务检查命令
        $serviceCommand = new RemoteCommand();
        $serviceCommand->setNode($backupNode);
        $serviceCommand->setName('检查关键服务状态');
        $serviceCommand->setCommand('systemctl status nginx mysql redis');
        $serviceCommand->setWorkingDirectory('/root');
        $serviceCommand->setUseSudo(true);
        $serviceCommand->setEnabled(true);
        $serviceCommand->setStatus(CommandStatus::RUNNING);
        $serviceCommand->setTimeout(120);
        $serviceCommand->setTags(['monitoring', 'service']);

        $manager->persist($serviceCommand);
        $this->addReference(self::SERVICE_CHECK_COMMAND, $serviceCommand);

        $manager->flush();
    }

    /**
     * 返回此 Fixture 所属的组名称
     */
    public static function getGroups(): array
    {
        return ['server-command', 'maintenance-commands'];
    }
}
