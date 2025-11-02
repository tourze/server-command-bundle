<?php

namespace ServerCommandBundle\Service\Quick;

use ServerCommandBundle\Contracts\ProgressModel;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Exception\DnsConfigurationException;
use ServerCommandBundle\Service\CommandOutputInspector;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * DNS配置服务
 */
#[Autoconfigure(public: true)]
class DnsConfigurationService
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly CommandOutputInspector $commandOutputInspector,
    ) {
    }

    /**
     * 检测并修复DNS污染问题
     */
    public function checkAndFixDns(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('检测DNS污染情况...');

        // 检测Docker Hub的DNS解析是否正常
        $dnsTestResult = $this->performDnsTest($deployTask, $node);

        // 使用8.8.8.8进行对比测试
        $googleDnsResult = $this->performGoogleDnsTest($deployTask, $node);

        // 检测是否存在DNS污染
        $isDnsPolluted = $this->analyzeDnsTestResults($deployTask, $dnsTestResult, $googleDnsResult);

        // 测试解析出的IP是否能正常连接Docker Hub
        if (!$isDnsPolluted && '' !== $dnsTestResult && 'DNS_FAILED' !== $dnsTestResult) {
            $isDnsPolluted = $this->testDockerHubConnectivity($deployTask, $node, $dnsTestResult);
        }

        // 测试Docker Hub API连接
        if (!$isDnsPolluted) {
            $isDnsPolluted = $this->testDockerHubConnection($deployTask, $node);
        }

        // 如果检测到DNS问题，修改DNS配置
        if ($isDnsPolluted) {
            $deployTask->appendLog('检测到DNS污染或连接问题，修改DNS配置...');
            $this->fixDnsConfiguration($deployTask, $node);

            // 修复后再次验证
            $this->verifyDnsAfterFix($deployTask, $node);
        } else {
            $deployTask->appendLog('DNS解析正常，无需修改');
        }
    }

    /**
     * 执行DNS测试
     */
    private function performDnsTest(ProgressModel $deployTask, Node $node): string
    {
        $dnsTestCommand = $this->remoteCommandService->createCommand(
            $node,
            '检测DNS解析',
            'nslookup registry-1.docker.io 2>/dev/null | grep "Address:" | tail -1 | ' .
            'cut -d" " -f2 2>/dev/null || echo "DNS_FAILED"',
            null,
            false,
            10,
            ['dns_test']
        );

        $this->remoteCommandService->executeCommand($dnsTestCommand);
        $dnsResult = trim($dnsTestCommand->getResult() ?? '');
        $deployTask->appendLog('DNS解析结果: ' . $dnsResult);

        return $dnsResult;
    }

    /**
     * 使用Google DNS测试
     */
    private function performGoogleDnsTest(ProgressModel $deployTask, Node $node): string
    {
        $googleDnsTestCommand = $this->remoteCommandService->createCommand(
            $node,
            '使用Google DNS测试',
            'nslookup registry-1.docker.io 8.8.8.8 2>/dev/null | grep "Address:" | ' .
            'tail -1 | cut -d" " -f2 2>/dev/null || echo "DNS_FAILED"',
            null,
            false,
            10,
            ['google_dns_test']
        );

        $this->remoteCommandService->executeCommand($googleDnsTestCommand);
        $googleDnsResult = trim($googleDnsTestCommand->getResult() ?? '');
        $deployTask->appendLog('Google DNS解析结果: ' . $googleDnsResult);

        return $googleDnsResult;
    }

    /**
     * 分析DNS测试结果
     */
    private function analyzeDnsTestResults(ProgressModel $deployTask, string $dnsResult, string $googleDnsResult): bool
    {
        if ('DNS_FAILED' === $dnsResult || '' === $dnsResult) {
            $deployTask->appendLog('检测到DNS解析失败');

            return true;
        }

        if ('DNS_FAILED' !== $googleDnsResult && '' !== $googleDnsResult && $dnsResult !== $googleDnsResult) {
            $deployTask->appendLog('检测到DNS解析结果不一致，可能存在DNS污染');

            return true;
        }

        return false;
    }

    /**
     * 测试解析出的IP是否能正常连接Docker Hub
     */
    private function testDockerHubConnectivity(ProgressModel $deployTask, Node $node, string $resolvedIp): bool
    {
        $deployTask->appendLog("测试解析IP {$resolvedIp} 的连通性...");

        $connectTestCommand = $this->remoteCommandService->createCommand(
            $node,
            '测试解析IP连通性',
            "curl -s --max-time 10 --connect-timeout 5 -I \"https://{$resolvedIp}/v2/\" " .
            '-H "Host: registry-1.docker.io" 2>/dev/null | head -1 | ' .
            'grep -q "200\|401" && echo "IP_CONNECT_OK" || echo "IP_CONNECT_FAILED"',
            null,
            false,
            15,
            ['test_ip_connectivity']
        );

        $this->remoteCommandService->executeCommand($connectTestCommand);
        $connectResult = trim($connectTestCommand->getResult() ?? '');
        $deployTask->appendLog("IP连通性测试结果: {$connectResult}");

        if ('IP_CONNECT_FAILED' === $connectResult) {
            $deployTask->appendLog("解析的IP {$resolvedIp} 无法正常连接Docker Hub");

            return true;
        }

        return false;
    }

    /**
     * 测试Docker Hub连接
     */
    private function testDockerHubConnection(ProgressModel $deployTask, Node $node): bool
    {
        $connectTestCommand = $this->remoteCommandService->createCommand(
            $node,
            '测试Docker Hub连接',
            'curl -s --max-time 5 -I "https://registry-1.docker.io/v2/" 2>/dev/null | ' .
            'head -1 | grep -q "200\|401" && echo "CONNECT_OK" || echo "CONNECT_FAILED"',
            null,
            false,
            10,
            ['docker_hub_test']
        );

        $this->remoteCommandService->executeCommand($connectTestCommand);
        $connectResult = trim($connectTestCommand->getResult() ?? '');
        $deployTask->appendLog('Docker Hub连接测试: ' . $connectResult);

        if ('CONNECT_FAILED' === $connectResult) {
            $deployTask->appendLog('检测到Docker Hub连接失败');

            return true;
        }

        return false;
    }

    /**
     * 修复DNS配置
     */
    private function fixDnsConfiguration(ProgressModel $deployTask, Node $node): void
    {
        // 备份原有DNS配置
        $this->backupDnsConfiguration($deployTask, $node);

        // 检查系统DNS管理方式
        $systemdStatus = $this->checkSystemdResolvedStatus($deployTask, $node);

        $dnsConfigured = false;

        if ('active' === $systemdStatus) {
            // 尝试使用systemd-resolved配置DNS
            try {
                $this->configureSystemdResolvedDns($deployTask, $node);
                $dnsConfigured = true;
            } catch (\Throwable $e) {
                $deployTask->appendLog('systemd-resolved配置失败: ' . $e->getMessage());
                $deployTask->appendLog('切换到传统DNS配置方式...');
            }
        }

        if (!$dnsConfigured) {
            // 使用传统方式配置DNS
            $this->configureTraditionalDns($deployTask, $node);
        }

        // 验证新DNS配置
        $this->verifyDnsConfiguration($deployTask, $node);

        $deployTask->appendLog('DNS配置修复完成');
    }

    /**
     * 备份DNS配置
     */
    private function backupDnsConfiguration(ProgressModel $deployTask, Node $node): void
    {
        $backupDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '备份DNS配置',
            'cp /etc/resolv.conf /etc/resolv.conf.backup 2>/dev/null || true',
            null,
            true,
            10,
            ['backup_dns']
        );

        $this->remoteCommandService->executeCommand($backupDnsCommand);
        $deployTask->appendLog('已备份原DNS配置');
    }

    /**
     * 检查systemd-resolved状态
     */
    private function checkSystemdResolvedStatus(ProgressModel $deployTask, Node $node): string
    {
        $checkSystemdCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查systemd-resolved状态',
            'systemctl is-active systemd-resolved 2>/dev/null || echo "inactive"',
            null,
            false,
            5,
            ['check_systemd_resolved']
        );

        $this->remoteCommandService->executeCommand($checkSystemdCommand);
        $systemdStatus = trim($checkSystemdCommand->getResult() ?? '');
        $deployTask->appendLog('systemd-resolved状态: ' . $systemdStatus);

        return $systemdStatus;
    }

    /**
     * 使用systemd-resolved配置DNS
     */
    private function configureSystemdResolvedDns(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('使用systemd-resolved配置DNS...');

        // 首先检查当前DNS状态
        $this->checkCurrentDnsStatus($deployTask, $node);

        // 方法1：使用resolvectl直接配置（推荐）
        if ($this->configureUsingResolvectl($deployTask, $node)) {
            $deployTask->appendLog('通过resolvectl配置DNS成功');

            return;
        }

        // 方法2：创建配置文件（备选）
        $this->createSystemdResolvedConfigFile($deployTask, $node);

        // 重启systemd-resolved服务
        $this->restartSystemdResolved($deployTask, $node);

        // 验证配置并刷新缓存
        $this->verifyAndFlushSystemdResolved($deployTask, $node);

        $deployTask->appendLog('systemd-resolved配置完成');
    }

    /**
     * 检查当前DNS状态
     */
    private function checkCurrentDnsStatus(ProgressModel $deployTask, Node $node): void
    {
        $statusCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查当前DNS状态',
            'resolvectl status',
            null,
            false,
            10,
            ['check_dns_status']
        );

        $this->remoteCommandService->executeCommand($statusCommand);
        $statusResult = $statusCommand->getResult() ?? '';
        $deployTask->appendLog('当前DNS状态: ' . substr(trim($statusResult), 0, 500));
    }

    /**
     * 使用resolvectl直接配置DNS
     */
    private function configureUsingResolvectl(ProgressModel $deployTask, Node $node): bool
    {
        $deployTask->appendLog('尝试使用resolvectl直接配置DNS...');

        // 获取默认网络接口
        $interfaceCommand = $this->remoteCommandService->createCommand(
            $node,
            '获取默认网络接口',
            'ip route show default | head -1 | cut -d\' \' -f5',
            null,
            false,
            5,
            ['get_interface']
        );

        $this->remoteCommandService->executeCommand($interfaceCommand);
        $interface = trim($interfaceCommand->getResult() ?? '');

        if ('' === $interface) {
            $deployTask->appendLog('无法获取网络接口，使用备选方法');

            return false;
        }

        $deployTask->appendLog("检测到网络接口: {$interface}");

        // 配置DNS服务器到指定接口
        $dnsConfigCommand = $this->remoteCommandService->createCommand(
            $node,
            '配置接口DNS',
            "resolvectl dns {$interface} 8.8.8.8 8.8.4.4 1.1.1.1",
            null,
            true,
            10,
            ['config_interface_dns']
        );

        $this->remoteCommandService->executeCommand($dnsConfigCommand);

        if (
            CommandStatus::COMPLETED === $dnsConfigCommand->getStatus()
            && !$this->commandOutputInspector->hasError($dnsConfigCommand->getResult() ?? '')
        ) {
            // 设置全局DNS域
            $domainCommand = $this->remoteCommandService->createCommand(
                $node,
                '配置DNS域',
                "resolvectl domain {$interface} '~.'",
                null,
                true,
                10,
                ['config_dns_domain']
            );

            $this->remoteCommandService->executeCommand($domainCommand);

            // 刷新DNS缓存
            $flushCommand = $this->remoteCommandService->createCommand(
                $node,
                '刷新DNS缓存',
                'resolvectl flush-caches',
                null,
                true,
                10,
                ['flush_dns_cache']
            );

            $this->remoteCommandService->executeCommand($flushCommand);

            $deployTask->appendLog('DNS配置和缓存刷新完成');

            return true;
        }

        return false;
    }

    /**
     * 创建systemd-resolved配置文件
     */
    private function createSystemdResolvedConfigFile(ProgressModel $deployTask, Node $node): void
    {
        // 创建systemd-resolved配置目录
        $this->createSystemdResolvedDirectory($deployTask, $node);

        // 创建配置文件
        $this->createSystemdResolvedConfig($deployTask, $node);
    }

    /**
     * 创建systemd-resolved配置目录
     */
    private function createSystemdResolvedDirectory(ProgressModel $deployTask, Node $node): void
    {
        $createDirCommand = $this->remoteCommandService->createCommand(
            $node,
            '创建systemd-resolved配置目录',
            'mkdir -p /etc/systemd/resolved.conf.d',
            null,
            true,
            5,
            ['create_resolved_dir']
        );

        $this->remoteCommandService->executeCommand($createDirCommand);

        // 检查目录创建是否成功
        $checkDirCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查配置目录',
            'test -d /etc/systemd/resolved.conf.d && echo "DIR_EXISTS" || echo "DIR_FAILED"',
            null,
            false,
            5,
            ['check_resolved_dir']
        );

        $this->remoteCommandService->executeCommand($checkDirCommand);
        $dirResult = trim($checkDirCommand->getResult() ?? '');

        if ('DIR_EXISTS' !== $dirResult) {
            throw DnsConfigurationException::directoryCreationFailed();
        }
    }

    /**
     * 创建systemd-resolved配置文件
     */
    private function createSystemdResolvedConfig(ProgressModel $deployTask, Node $node): void
    {
        $configResolvedCommand = $this->remoteCommandService->createCommand(
            $node,
            '配置systemd-resolved DNS',
            'cat > /etc/systemd/resolved.conf.d/dns_servers.conf << EOF
[Resolve]
DNS=8.8.8.8 8.8.4.4 1.1.1.1 114.114.114.114
FallbackDNS=223.5.5.5 119.29.29.29
Domains=~.
DNSSEC=no
DNSOverTLS=no
Cache=yes
DNSStubListener=yes
ReadEtcHosts=yes
EOF',
            null,
            true,
            10,
            ['config_systemd_resolved']
        );

        $this->remoteCommandService->executeCommand($configResolvedCommand);

        // 检查配置文件是否创建成功
        if (
            CommandStatus::COMPLETED !== $configResolvedCommand->getStatus()
            || $this->commandOutputInspector->hasError($configResolvedCommand->getResult() ?? '')
        ) {
            throw DnsConfigurationException::configurationCreateFailed();
        }

        $deployTask->appendLog('systemd-resolved配置文件创建成功');
    }

    /**
     * 重启systemd-resolved服务
     */
    private function restartSystemdResolved(ProgressModel $deployTask, Node $node): void
    {
        $restartResolvedCommand = $this->remoteCommandService->createCommand(
            $node,
            '重启systemd-resolved',
            'systemctl restart systemd-resolved && sleep 3',
            null,
            true,
            15,
            ['restart_systemd_resolved']
        );

        $this->remoteCommandService->executeCommand($restartResolvedCommand);

        if (CommandStatus::COMPLETED !== $restartResolvedCommand->getStatus()) {
            throw DnsConfigurationException::configurationUpdateFailed();
        }
    }

    /**
     * 验证配置并刷新systemd-resolved
     */
    private function verifyAndFlushSystemdResolved(ProgressModel $deployTask, Node $node): void
    {
        // 刷新DNS缓存
        $flushCommand = $this->remoteCommandService->createCommand(
            $node,
            '刷新systemd-resolved缓存',
            'resolvectl flush-caches',
            null,
            true,
            10,
            ['flush_resolved_cache']
        );

        $this->remoteCommandService->executeCommand($flushCommand);
        $deployTask->appendLog('已刷新systemd-resolved缓存');

        // 重新检查DNS状态
        $this->checkCurrentDnsStatus($deployTask, $node);

        // 测试新DNS配置
        $testDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '测试新DNS配置',
            'resolvectl query registry-1.docker.io',
            null,
            false,
            10,
            ['test_new_dns']
        );

        $this->remoteCommandService->executeCommand($testDnsCommand);
        $testResult = $testDnsCommand->getResult() ?? '';
        $deployTask->appendLog('DNS测试结果: ' . trim($testResult));
    }

    /**
     * 传统方式配置DNS
     */
    private function configureTraditionalDns(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('使用传统方式配置DNS...');

        // 移除immutable属性和符号链接
        $this->prepareResolveConf($deployTask, $node);

        // 尝试多种方法创建DNS配置
        $dnsConfigured = $this->tryCreateResolveConf($deployTask, $node)
                        || $this->tryCreateResolveConfWithTemp($deployTask, $node)
                        || $this->configureDockerDns($deployTask, $node);

        if (!$dnsConfigured) {
            $deployTask->appendLog('所有DNS配置方法都失败了，但将继续部署');
        }

        // 刷新DNS缓存
        $this->flushDnsCache($deployTask, $node);
    }

    /**
     * 准备resolv.conf文件
     */
    private function prepareResolveConf(ProgressModel $deployTask, Node $node): void
    {
        // 移除immutable属性（如果存在）
        $removeImmutableCommand = $this->remoteCommandService->createCommand(
            $node,
            '移除resolv.conf保护属性',
            'chattr -i /etc/resolv.conf 2>/dev/null || true',
            null,
            true,
            5,
            ['remove_immutable']
        );

        $this->remoteCommandService->executeCommand($removeImmutableCommand);
        $deployTask->appendLog('已移除文件保护属性');

        // 如果是符号链接，先删除
        $removeSymlinkCommand = $this->remoteCommandService->createCommand(
            $node,
            '处理resolv.conf符号链接',
            'if [ -L /etc/resolv.conf ]; then rm /etc/resolv.conf; fi',
            null,
            true,
            5,
            ['remove_symlink']
        );

        $this->remoteCommandService->executeCommand($removeSymlinkCommand);
        $deployTask->appendLog('已处理符号链接');
    }

    /**
     * 尝试直接创建resolv.conf
     */
    private function tryCreateResolveConf(ProgressModel $deployTask, Node $node): bool
    {
        $deployTask->appendLog('尝试直接创建DNS配置...');

        $newDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '创建DNS配置文件',
            'cat > /etc/resolv.conf << EOF
nameserver 8.8.8.8
nameserver 8.8.4.4
nameserver 1.1.1.1
options timeout:2 attempts:3 rotate single-request-reopen
EOF',
            null,
            true,
            10,
            ['create_resolv_conf']
        );

        $this->remoteCommandService->executeCommand($newDnsCommand);

        if (
            CommandStatus::COMPLETED === $newDnsCommand->getStatus()
            && !$this->commandOutputInspector->hasError($newDnsCommand->getResult() ?? '')
        ) {
            $deployTask->appendLog('DNS配置文件创建成功');

            // 设置immutable属性防止被覆盖
            $this->protectResolveConf($deployTask, $node);

            return true;
        }

        return false;
    }

    /**
     * 保护resolv.conf文件
     */
    private function protectResolveConf(ProgressModel $deployTask, Node $node): void
    {
        $setImmutableCommand = $this->remoteCommandService->createCommand(
            $node,
            '保护DNS配置文件',
            'chattr +i /etc/resolv.conf 2>/dev/null || true',
            null,
            true,
            5,
            ['set_immutable']
        );

        $this->remoteCommandService->executeCommand($setImmutableCommand);
        $deployTask->appendLog('已保护DNS配置文件');
    }

    /**
     * 使用临时文件创建resolv.conf
     */
    private function tryCreateResolveConfWithTemp(ProgressModel $deployTask, Node $node): bool
    {
        $deployTask->appendLog('使用临时文件创建DNS配置...');

        $tempDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '使用临时文件创建DNS配置',
            'cat > /tmp/resolv.conf.new << EOF
nameserver 8.8.8.8
nameserver 8.8.4.4
nameserver 1.1.1.1
options timeout:2 attempts:3 rotate single-request-reopen
EOF
mv /tmp/resolv.conf.new /etc/resolv.conf',
            null,
            true,
            10,
            ['create_resolv_conf_temp']
        );

        $this->remoteCommandService->executeCommand($tempDnsCommand);

        if (
            CommandStatus::COMPLETED === $tempDnsCommand->getStatus()
            && !$this->commandOutputInspector->hasError($tempDnsCommand->getResult() ?? '')
        ) {
            $deployTask->appendLog('使用临时文件成功创建DNS配置');

            return true;
        }

        return false;
    }

    /**
     * 配置Docker daemon DNS
     */
    private function configureDockerDns(ProgressModel $deployTask, Node $node): bool
    {
        $deployTask->appendLog('配置Docker daemon DNS设置...');

        try {
            // 确保Docker配置目录存在
            $this->ensureDockerConfigDirectory($deployTask, $node);

            // 更新Docker daemon配置
            $this->updateDockerDaemonConfig($deployTask, $node);

            // 重启Docker应用配置
            $this->restartDockerForDns($deployTask, $node);

            return true;
        } catch (\Throwable $e) {
            $deployTask->appendLog('Docker DNS配置失败: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * 确保Docker配置目录存在
     */
    private function ensureDockerConfigDirectory(ProgressModel $deployTask, Node $node): void
    {
        $createDirCommand = $this->remoteCommandService->createCommand(
            $node,
            '创建Docker配置目录',
            'mkdir -p /etc/docker',
            null,
            true,
            5,
            ['create_docker_dir']
        );

        $this->remoteCommandService->executeCommand($createDirCommand);
    }

    /**
     * 更新Docker daemon配置
     */
    private function updateDockerDaemonConfig(ProgressModel $deployTask, Node $node): void
    {
        // 检查现有Docker配置
        $checkDockerConfigCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查Docker配置',
            'test -f /etc/docker/daemon.json && cat /etc/docker/daemon.json || echo "{}"',
            null,
            false,
            5,
            ['check_docker_config']
        );

        $this->remoteCommandService->executeCommand($checkDockerConfigCommand);
        $currentConfig = trim($checkDockerConfigCommand->getResult() ?? '{}');

        // 解析现有配置并添加DNS设置
        $decoded = json_decode($currentConfig, true);
        $config = is_array($decoded) ? $decoded : [];
        $config['dns'] = ['8.8.8.8', '8.8.4.4', '1.1.1.1'];

        $newConfig = json_encode($config, JSON_PRETTY_PRINT);

        $updateDockerConfigCommand = $this->remoteCommandService->createCommand(
            $node,
            '更新Docker DNS配置',
            "cat > /etc/docker/daemon.json << 'EOF'\n{$newConfig}\nEOF",
            null,
            true,
            10,
            ['update_docker_dns_config']
        );

        $this->remoteCommandService->executeCommand($updateDockerConfigCommand);

        if (CommandStatus::COMPLETED === $updateDockerConfigCommand->getStatus()) {
            $deployTask->appendLog('Docker DNS配置更新成功');
        } else {
            throw DnsConfigurationException::dnsmasqConfigCreateFailed();
        }
    }

    /**
     * 重启Docker应用DNS配置
     */
    private function restartDockerForDns(ProgressModel $deployTask, Node $node): void
    {
        $restartDockerCommand = $this->remoteCommandService->createCommand(
            $node,
            '重启Docker应用DNS配置',
            'systemctl restart docker && sleep 3',
            null,
            true,
            30,
            ['restart_docker_dns']
        );

        $this->remoteCommandService->executeCommand($restartDockerCommand);
        $deployTask->appendLog('Docker已重启并应用DNS配置');
    }

    /**
     * 刷新DNS缓存
     */
    private function flushDnsCache(ProgressModel $deployTask, Node $node): void
    {
        $flushDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '刷新DNS缓存',
            'service nscd restart 2>/dev/null || /etc/init.d/nscd restart 2>/dev/null || true',
            null,
            true,
            10,
            ['flush_dns_cache']
        );

        $this->remoteCommandService->executeCommand($flushDnsCommand);
        $deployTask->appendLog('已刷新DNS缓存');
    }

    /**
     * 验证DNS配置
     */
    private function verifyDnsConfiguration(ProgressModel $deployTask, Node $node): void
    {
        $verifyDnsCommand = $this->remoteCommandService->createCommand(
            $node,
            '验证DNS配置',
            'nslookup registry-1.docker.io 2>/dev/null | grep "Address:" | tail -1 || echo "验证DNS配置完成"',
            null,
            false,
            10,
            ['verify_dns']
        );

        $this->remoteCommandService->executeCommand($verifyDnsCommand);
        $verifyResult = trim($verifyDnsCommand->getResult() ?? '');
        $deployTask->appendLog('DNS配置验证: ' . $verifyResult);
    }


    /**
     * 修复后验证DNS配置
     */
    private function verifyDnsAfterFix(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('验证DNS修复效果...');

        // 等待DNS配置生效
        sleep(3);

        // 重新测试DNS解析
        $newDnsResult = $this->performDnsTest($deployTask, $node);
        $deployTask->appendLog("修复后DNS解析结果: {$newDnsResult}");

        // 测试Docker Hub连接
        $connectResult = $this->testDockerHubConnection($deployTask, $node);

        if ($connectResult) {
            $deployTask->appendLog('⚠️ DNS修复后仍无法正常连接Docker Hub，可能存在网络防火墙限制');
        } else {
            $deployTask->appendLog('✅ DNS修复成功，可以正常连接Docker Hub');
        }
    }
}
