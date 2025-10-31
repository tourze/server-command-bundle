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
 * SSH密钥认证演示数据填充
 */
#[When(env: 'test')]
#[When(env: 'dev')]
class SshKeyAuthFixtures extends Fixture implements FixtureGroupInterface
{
    public const SSH_KEY_TEST_COMMAND = 'ssh-key-test-command';
    public const SSH_MIXED_AUTH_COMMAND = 'ssh-mixed-auth-command';

    public function load(ObjectManager $manager): void
    {
        // 创建测试节点
        $keyNode = new Node();
        $keyNode->setName('SSH密钥认证测试节点');
        $keyNode->setHostname('ssh-key-test-node');
        $keyNode->setSshHost('192.168.1.101');
        $keyNode->setSshPort(22);
        $keyNode->setSshUser('root');
        $keyNode->setValid(true);
        $keyNode->setTags(['ssh-key', 'test']);

        $manager->persist($keyNode);

        // 创建SSH密钥认证测试命令
        $keyTestCommand = new RemoteCommand();
        $keyTestCommand->setNode($keyNode);
        $keyTestCommand->setName('SSH密钥认证测试');
        $keyTestCommand->setCommand('ssh-keygen -l -f ~/.ssh/authorized_keys');
        $keyTestCommand->setWorkingDirectory('/root');
        $keyTestCommand->setUseSudo(false);
        $keyTestCommand->setEnabled(true);
        $keyTestCommand->setStatus(CommandStatus::COMPLETED);
        $keyTestCommand->setResult(
            "2048 SHA256:nThbg6kXUpJWGl7E1IGOCspRomTxdCARLviKw6E5SY8 root@demo.tourze.com (RSA)\n" .
            '1024 SHA256:abcd1234567890efghij root@backup-server (DSA)'
        );
        $keyTestCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:45:30'));
        $keyTestCommand->setExecutionTime(0.03);
        $keyTestCommand->setTimeout(30);
        $keyTestCommand->setTags(['ssh-key', 'test']);

        $manager->persist($keyTestCommand);
        $this->addReference(self::SSH_KEY_TEST_COMMAND, $keyTestCommand);

        // 创建混合认证测试命令
        $mixedAuthCommand = new RemoteCommand();
        $mixedAuthCommand->setNode($keyNode);
        $mixedAuthCommand->setName('混合认证回退测试');
        $mixedAuthCommand->setCommand('whoami && echo "当前认证用户: $(whoami)"');
        $mixedAuthCommand->setWorkingDirectory('/root');
        $mixedAuthCommand->setUseSudo(false);
        $mixedAuthCommand->setEnabled(true);
        $mixedAuthCommand->setStatus(CommandStatus::COMPLETED);
        $mixedAuthCommand->setResult("root\n当前认证用户: root");
        $mixedAuthCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:46:15'));
        $mixedAuthCommand->setExecutionTime(0.01);
        $mixedAuthCommand->setTimeout(30);
        $mixedAuthCommand->setTags(['ssh-key', 'mixed-auth', 'test']);

        $manager->persist($mixedAuthCommand);
        $this->addReference(self::SSH_MIXED_AUTH_COMMAND, $mixedAuthCommand);

        // 创建验证SSH密钥格式的命令
        $keyFormatCommand = new RemoteCommand();
        $keyFormatCommand->setNode($keyNode);
        $keyFormatCommand->setName('验证SSH密钥格式');
        $keyFormatCommand->setCommand('file ~/.ssh/id_rsa');
        $keyFormatCommand->setWorkingDirectory('/root');
        $keyFormatCommand->setUseSudo(false);
        $keyFormatCommand->setEnabled(true);
        $keyFormatCommand->setStatus(CommandStatus::COMPLETED);
        $keyFormatCommand->setResult('/root/.ssh/id_rsa: PEM RSA private key');
        $keyFormatCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:47:00'));
        $keyFormatCommand->setExecutionTime(0.02);
        $keyFormatCommand->setTimeout(30);
        $keyFormatCommand->setTags(['ssh-key', 'validation']);

        $manager->persist($keyFormatCommand);

        // 创建测试私钥权限的命令
        $keyPermissionCommand = new RemoteCommand();
        $keyPermissionCommand->setNode($keyNode);
        $keyPermissionCommand->setName('检查SSH密钥权限');
        $keyPermissionCommand->setCommand('ls -la ~/.ssh/id_rsa ~/.ssh/id_rsa.pub');
        $keyPermissionCommand->setWorkingDirectory('/root');
        $keyPermissionCommand->setUseSudo(false);
        $keyPermissionCommand->setEnabled(true);
        $keyPermissionCommand->setStatus(CommandStatus::COMPLETED);
        $keyPermissionCommand->setResult(
            "-rw------- 1 root root 1679 Aug 16 16:30 /root/.ssh/id_rsa\n" .
            '-rw-r--r-- 1 root root  394 Aug 16 16:30 /root/.ssh/id_rsa.pub'
        );
        $keyPermissionCommand->setExecutedAt(new \DateTimeImmutable('2023-08-16 16:48:00'));
        $keyPermissionCommand->setExecutionTime(0.02);
        $keyPermissionCommand->setTimeout(30);
        $keyPermissionCommand->setTags(['ssh-key', 'security']);

        $manager->persist($keyPermissionCommand);

        $manager->flush();
    }

    /**
     * 返回此 Fixture 所属的组名称
     */
    public static function getGroups(): array
    {
        return ['server-command', 'ssh-key-auth'];
    }
}
