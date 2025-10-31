<?php

declare(strict_types=1);

namespace ServerCommandBundle\Service;

use Monolog\Attribute\WithMonologChannel;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SSH2;
use Psr\Log\LoggerInterface;
use ServerCommandBundle\Exception\SshConnectionException;
use ServerNodeBundle\Entity\Node;

#[WithMonologChannel(channel: 'server_command')]
class SshConnectionService
{
    /**
     * SSH连接超时时间（秒）
     */
    private const SSH_READ_TIMEOUT = 0.2; // 200ms

    /**
     * 最大等待时间（秒）
     */
    private const MAX_WAIT_TIME = 5;

    /**
     * 短暂等待时间（微秒）
     */
    private const POLLING_INTERVAL = 100000; // 100ms

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 创建SSH连接
     */
    public function createConnection(Node $node, bool $useSudo = false): SSH2
    {
        $ssh = $this->initializeConnection($node);

        // 如果需要sudo且用户不是root，需要切换到root
        if ($useSudo && 'root' !== $node->getSshUser()) {
            $this->switchToRootUser($ssh, $node);
        }

        return $ssh;
    }

    /**
     * 初始化SSH连接
     */
    private function initializeConnection(Node $node): SSH2
    {
        $connectionInfo = $this->extractConnectionInfo($node);
        $this->validateConnectionInfo($connectionInfo);

        // 尝试私钥认证
        if ($this->hasPrivateKey($connectionInfo)) {
            $ssh = $this->tryPrivateKeyAuth($connectionInfo);
            if (null !== $ssh) {
                return $ssh;
            }
        }

        // 尝试密码认证
        if ($this->hasPassword($connectionInfo)) {
            $ssh = $this->tryPasswordAuth($connectionInfo);
            if (null !== $ssh) {
                return $ssh;
            }
        }

        // 所有认证方式都失败
        $this->handleAllAuthenticationsFailed($connectionInfo);

        $host = $connectionInfo['host'] ?? 'unknown';
        throw SshConnectionException::connectionFailed($host, $connectionInfo['port']);
    }

    /**
     * 提取连接信息
     *
     * @return array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null}
     */
    private function extractConnectionInfo(Node $node): array
    {
        return [
            'host' => $node->getSshHost(),
            'port' => $node->getSshPort(),
            'user' => $node->getSshUser(),
            'privateKey' => $node->getSshPrivateKey(),
            'password' => $node->getSshPassword(),
        ];
    }

    /**
     * 验证连接信息
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function validateConnectionInfo(array $connectionInfo): void
    {
        if (null === $connectionInfo['host'] || null === $connectionInfo['user']) {
            $host = $connectionInfo['host'] ?? 'unknown';
            throw SshConnectionException::connectionFailed($host, $connectionInfo['port']);
        }
    }

    /**
     * 是否有私钥
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function hasPrivateKey(array $connectionInfo): bool
    {
        return null !== $connectionInfo['privateKey'] && '' !== $connectionInfo['privateKey'];
    }

    /**
     * 是否有密码
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function hasPassword(array $connectionInfo): bool
    {
        return null !== $connectionInfo['password'] && '' !== $connectionInfo['password'];
    }

    /**
     * 尝试私钥认证
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function tryPrivateKeyAuth(array $connectionInfo): ?SSH2
    {
        try {
            $host = $connectionInfo['host'];
            $user = $connectionInfo['user'];
            $privateKey = $connectionInfo['privateKey'];

            if (null === $host || null === $user || null === $privateKey) {
                return null;
            }

            return $this->connectWithPrivateKey(
                $host,
                $connectionInfo['port'],
                $user,
                $privateKey
            );
        } catch (\Throwable $e) {
            $this->logger->warning('SSH私钥认证失败，尝试密码认证', [
                'host' => $connectionInfo['host'],
                'port' => $connectionInfo['port'],
                'user' => $connectionInfo['user'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 尝试密码认证
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function tryPasswordAuth(array $connectionInfo): ?SSH2
    {
        try {
            $host = $connectionInfo['host'];
            $user = $connectionInfo['user'];
            $password = $connectionInfo['password'];

            if (null === $host || null === $user || null === $password) {
                return null;
            }

            return $this->connectWithPassword(
                $host,
                $connectionInfo['port'],
                $user,
                $password
            );
        } catch (\Throwable $e) {
            $this->logger->error('SSH密码认证也失败', [
                'host' => $connectionInfo['host'],
                'port' => $connectionInfo['port'],
                'user' => $connectionInfo['user'],
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 处理所有认证失败的情况
     *
     * @param array{host: string|null, port: int, user: string|null, privateKey: string|null, password: string|null} $connectionInfo
     */
    private function handleAllAuthenticationsFailed(array $connectionInfo): void
    {
        $error = 'SSH连接失败: 私钥和密码认证均失败';
        $this->logger->error($error, [
            'host' => $connectionInfo['host'],
            'port' => $connectionInfo['port'],
            'user' => $connectionInfo['user'],
            'hasPrivateKey' => null !== $connectionInfo['privateKey'] && '' !== $connectionInfo['privateKey'],
            'hasPassword' => null !== $connectionInfo['password'] && '' !== $connectionInfo['password'],
        ]);
        $host = $connectionInfo['host'] ?? 'unknown';
        throw SshConnectionException::connectionFailed($host, $connectionInfo['port']);
    }

    /**
     * 使用私钥凭证创建SSH连接
     */
    public function connectWithPrivateKey(string $host, int $port, string $user, string $privateKey): SSH2
    {
        $ssh = $this->createBasicConnection($host, $port);

        try {
            $key = PublicKeyLoader::loadPrivateKey($privateKey);
            if (!$ssh->login($user, $key)) {
                $error = 'SSH连接失败: 私钥认证失败';
                $this->logger->error($error, [
                    'host' => $host,
                    'port' => $port,
                    'user' => $user,
                ]);
                throw SshConnectionException::authenticationFailed();
            }

            $this->logger->info('SSH私钥认证成功', [
                'host' => $host,
                'port' => $port,
                'user' => $user,
            ]);

            $this->configureConnection($ssh, $host, $port, $user, '私钥认证');

            return $ssh;
        } catch (\Throwable $e) {
            $error = 'SSH连接失败: 私钥加载或认证失败';
            $this->logger->error($error, [
                'host' => $host,
                'port' => $port,
                'user' => $user,
                'error' => $e->getMessage(),
            ]);
            throw SshConnectionException::sudoSwitchFailed();
        }
    }

    /**
     * 创建基础SSH连接对象
     */
    private function createBasicConnection(string $host, int $port): SSH2
    {
        return new SSH2($host, $port);
    }

    /**
     * 配置SSH连接
     */
    private function configureConnection(SSH2 $ssh, string $host, int $port, string $user, string $authMethod): void
    {
        // 设置超时为0（无超时）
        $ssh->setTimeout(0);

        $this->logger->debug('SSH连接建立成功', [
            'host' => $host,
            'port' => $port,
            'user' => $user,
            'authMethod' => $authMethod,
        ]);
    }

    /**
     * 使用密码凭证创建SSH连接
     */
    public function connectWithPassword(string $host, int $port, string $user, string $password): SSH2
    {
        $ssh = $this->createBasicConnection($host, $port);

        if (!$ssh->login($user, $password)) {
            $error = 'SSH连接失败: 密码认证失败';
            $this->logger->error($error, [
                'host' => $host,
                'port' => $port,
                'user' => $user,
            ]);
            throw SshConnectionException::executionFailed('sudo su');
        }

        $this->logger->info('SSH密码认证成功', [
            'host' => $host,
            'port' => $port,
            'user' => $user,
        ]);

        $this->configureConnection($ssh, $host, $port, $user, '密码认证');

        return $ssh;
    }

    /**
     * 切换到root用户
     */
    private function switchToRootUser(SSH2 $ssh, Node $node): void
    {
        try {
            // 执行切换到root账号的命令
            $ssh->write("sudo su -\n");

            // 使用较短的超时时间来检查响应
            $ssh->setTimeout(self::SSH_READ_TIMEOUT);

            // 使用轮询方式读取输出，避免长时间等待
            $startTime = time();
            $output = '';

            // 最多等待设定的最大时间
            while ((time() - $startTime) < self::MAX_WAIT_TIME) {
                // 尝试读取小块数据，不使用正则匹配
                $temp = $ssh->read();
                if (null !== $temp && '' !== $temp) {
                    $output .= $temp;
                    // 检查是否已经收到密码提示或root提示
                    if (1 === preg_match('/[Pp]assword|密码|口令|认证/i', $output)) {
                        $ssh->write("{$node->getSshPassword()}\n"); // 输入sudo密码
                        $this->waitForRootPrompt($ssh);
                        break;
                    }
                    if (1 === preg_match('/root@|#\s*$/', $output)) {
                        // 已经是root用户或直接切换成功
                        break;
                    }
                }
                // 短暂等待减少CPU使用
                usleep(self::POLLING_INTERVAL);
            }

            $this->logger->debug('SSH响应输出', ['output' => $output]);

            // 恢复无超时设置
            $ssh->setTimeout(0);
        } catch (\Throwable $e) {
            $this->logger->warning('切换到root用户时出错: ' . $e->getMessage(), ['node' => $node->getId()]);
            // 失败后尝试继续，使用当前用户执行命令
        }
    }

    /**
     * 等待root提示符
     */
    private function waitForRootPrompt(SSH2 $ssh): void
    {
        // 继续读取直到出现root提示符
        $rootOutput = '';
        $startRootTime = time();
        $foundRoot = false;

        while ((time() - $startRootTime) < self::MAX_WAIT_TIME && !$foundRoot) {
            $temp = $ssh->read();
            if (null !== $temp && '' !== $temp) {
                $rootOutput .= $temp;
                if (1 === preg_match('/root@|#\s*$/', $rootOutput)) {
                    $foundRoot = true;
                }
            }
            // 短暂等待减少CPU使用
            usleep(self::POLLING_INTERVAL);
        }
    }
}
