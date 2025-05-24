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
 * SSH密钥认证演示数据填充
 */
class SshKeyAuthFixtures extends Fixture implements DependentFixtureInterface, FixtureGroupInterface
{
    public const SSH_KEY_TEST_COMMAND = 'ssh-key-test-command';
    public const SSH_MIXED_AUTH_COMMAND = 'ssh-mixed-auth-command';

    public function load(ObjectManager $manager): void
    {
        /** @var Node $keyNode */
        $keyNode = $this->getReference(NodeFixtures::REFERENCE_NODE_1, Node::class);

        // 创建SSH密钥认证测试命令
        $keyTestCommand = new RemoteCommand();
        $keyTestCommand->setNode($keyNode)
            ->setName('SSH密钥认证测试')
            ->setCommand('ssh-keygen -l -f ~/.ssh/authorized_keys')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("2048 SHA256:nThbg6kXUpJWGl7E1IGOCspRomTxdCARLviKw6E5SY8 root@example.com (RSA)\n1024 SHA256:abcd1234567890efghij root@backup-server (DSA)")
            ->setExecutedAt(new \DateTime('2023-08-16 16:45:30'))
            ->setExecutionTime(0.03)
            ->setTimeout(30)
            ->setTags(['ssh-key', 'test']);

        $manager->persist($keyTestCommand);
        $this->addReference(self::SSH_KEY_TEST_COMMAND, $keyTestCommand);

        // 创建混合认证测试命令
        $mixedAuthCommand = new RemoteCommand();
        $mixedAuthCommand->setNode($keyNode)
            ->setName('混合认证回退测试')
            ->setCommand('whoami && echo "当前认证用户: $(whoami)"')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("root\n当前认证用户: root")
            ->setExecutedAt(new \DateTime('2023-08-16 16:46:15'))
            ->setExecutionTime(0.01)
            ->setTimeout(30)
            ->setTags(['ssh-key', 'mixed-auth', 'test']);

        $manager->persist($mixedAuthCommand);
        $this->addReference(self::SSH_MIXED_AUTH_COMMAND, $mixedAuthCommand);

        // 创建验证SSH密钥格式的命令
        $keyFormatCommand = new RemoteCommand();
        $keyFormatCommand->setNode($keyNode)
            ->setName('验证SSH密钥格式')
            ->setCommand('file ~/.ssh/id_rsa')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("/root/.ssh/id_rsa: PEM RSA private key")
            ->setExecutedAt(new \DateTime('2023-08-16 16:47:00'))
            ->setExecutionTime(0.02)
            ->setTimeout(30)
            ->setTags(['ssh-key', 'validation']);

        $manager->persist($keyFormatCommand);

        // 创建测试私钥权限的命令
        $keyPermissionCommand = new RemoteCommand();
        $keyPermissionCommand->setNode($keyNode)
            ->setName('检查SSH密钥权限')
            ->setCommand('ls -la ~/.ssh/id_rsa ~/.ssh/id_rsa.pub')
            ->setWorkingDirectory('/root')
            ->setUseSudo(false)
            ->setEnabled(true)
            ->setStatus(CommandStatus::COMPLETED)
            ->setResult("-rw------- 1 root root 1679 Aug 16 16:30 /root/.ssh/id_rsa\n-rw-r--r-- 1 root root  394 Aug 16 16:30 /root/.ssh/id_rsa.pub")
            ->setExecutedAt(new \DateTime('2023-08-16 16:48:00'))
            ->setExecutionTime(0.02)
            ->setTimeout(30)
            ->setTags(['ssh-key', 'security']);

        $manager->persist($keyPermissionCommand);

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
        return ['server-command', 'ssh-key-auth'];
    }
} 