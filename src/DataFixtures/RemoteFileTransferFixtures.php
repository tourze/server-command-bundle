<?php

namespace ServerCommandBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Persistence\ObjectManager;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\DependencyInjection\Attribute\When;

#[When(env: 'test')]
#[When(env: 'dev')]
class RemoteFileTransferFixtures extends Fixture implements FixtureGroupInterface
{
    public const CONFIG_UPLOAD_TRANSFER = 'config-upload-transfer';
    public const LOG_BACKUP_TRANSFER = 'log-backup-transfer';
    public const UPDATE_PACKAGE_TRANSFER = 'update-package-transfer';
    public const CERTIFICATE_TRANSFER = 'certificate-transfer';

    public static function getGroups(): array
    {
        return ['server-command', 'file-transfers'];
    }

    public function load(ObjectManager $manager): void
    {
        // 创建测试节点
        $linuxNode = new Node();
        $linuxNode->setName('文件传输测试服务器');
        $linuxNode->setSshHost('127.0.0.1');
        $linuxNode->setSshUser('root');
        $linuxNode->setSshPort(22);
        $linuxNode->setValid(true);
        $manager->persist($linuxNode);

        $configTransfer = new RemoteFileTransfer();
        $configTransfer->setNode($linuxNode);
        $configTransfer->setName('配置文件上传');
        $configTransfer->setLocalPath('/tmp/config.ini');
        $configTransfer->setRemotePath('/etc/app/config.ini');
        $configTransfer->setTempPath('/tmp/upload_config.ini');
        $configTransfer->setFileSize(2048);
        $configTransfer->setUseSudo(true);
        $configTransfer->setEnabled(true);
        $configTransfer->setStatus(FileTransferStatus::PENDING);
        $configTransfer->setTimeout(300);
        $configTransfer->setTags(['config', 'deployment']);

        $manager->persist($configTransfer);
        $this->addReference(self::CONFIG_UPLOAD_TRANSFER, $configTransfer);

        $logTransfer = new RemoteFileTransfer();
        $logTransfer->setNode($linuxNode);
        $logTransfer->setName('日志备份下载');
        $logTransfer->setLocalPath('/var/backups/app.log');
        $logTransfer->setRemotePath('/var/log/application.log');
        $logTransfer->setFileSize(10485760);
        $logTransfer->setUseSudo(false);
        $logTransfer->setEnabled(true);
        $logTransfer->setStatus(FileTransferStatus::COMPLETED);
        $logTransfer->setResult('文件传输成功完成');
        $logTransfer->setStartedAt(new \DateTimeImmutable('2023-08-16 14:30:00'));
        $logTransfer->setCompletedAt(new \DateTimeImmutable('2023-08-16 14:32:15'));
        $logTransfer->setTransferTime(135.42);
        $logTransfer->setTimeout(600);
        $logTransfer->setTags(['backup', 'logs']);

        $manager->persist($logTransfer);
        $this->addReference(self::LOG_BACKUP_TRANSFER, $logTransfer);

        $packageTransfer = new RemoteFileTransfer();
        $packageTransfer->setNode($linuxNode);
        $packageTransfer->setName('更新包部署');
        $packageTransfer->setLocalPath('/release/app-v2.1.0.tar.gz');
        $packageTransfer->setRemotePath('/opt/app/releases/app-v2.1.0.tar.gz');
        $packageTransfer->setTempPath('/tmp/app-v2.1.0.tar.gz');
        $packageTransfer->setFileSize(52428800);
        $packageTransfer->setUseSudo(true);
        $packageTransfer->setEnabled(true);
        $packageTransfer->setStatus(FileTransferStatus::UPLOADING);
        $packageTransfer->setStartedAt(new \DateTimeImmutable('2023-08-16 15:00:00'));
        $packageTransfer->setTimeout(1200);
        $packageTransfer->setTags(['release', 'deployment']);

        $manager->persist($packageTransfer);
        $this->addReference(self::UPDATE_PACKAGE_TRANSFER, $packageTransfer);

        $certificateTransfer = new RemoteFileTransfer();
        $certificateTransfer->setNode($linuxNode);
        $certificateTransfer->setName('SSL证书更新');
        $certificateTransfer->setLocalPath('/certs/ssl.crt');
        $certificateTransfer->setRemotePath('/etc/ssl/certs/app.crt');
        $certificateTransfer->setFileSize(4096);
        $certificateTransfer->setUseSudo(true);
        $certificateTransfer->setEnabled(true);
        $certificateTransfer->setStatus(FileTransferStatus::FAILED);
        $certificateTransfer->setResult('权限不足，无法写入目标目录');
        $certificateTransfer->setStartedAt(new \DateTimeImmutable('2023-08-16 13:45:00'));
        $certificateTransfer->setCompletedAt(new \DateTimeImmutable('2023-08-16 13:45:05'));
        $certificateTransfer->setTransferTime(5.12);
        $certificateTransfer->setTimeout(300);
        $certificateTransfer->setTags(['security', 'ssl']);

        $manager->persist($certificateTransfer);
        $this->addReference(self::CERTIFICATE_TRANSFER, $certificateTransfer);

        $manager->flush();
    }
}
