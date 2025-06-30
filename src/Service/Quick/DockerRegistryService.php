<?php

namespace ServerCommandBundle\Service\Quick;

use Psr\Log\LoggerInterface;
use ServerCommandBundle\Contracts\ProgressModel;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Exception\DockerRegistryException;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;

/**
 * Docker镜像仓库服务
 * 负责检测服务器位置并配置适当的Docker镜像注册表加速器
 */
class DockerRegistryService
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
        private readonly DnsConfigurationService $dnsConfigurationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * 检测IP位置并配置Docker镜像加速器
     */
    public function configureDockerRegistry(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('检测服务器位置并配置镜像源...');

        // 先检测DNS污染情况
        $this->dnsConfigurationService->checkAndFixDns($deployTask, $node);

        // 检测公网IP位置
        $locationInfo = $this->detectServerLocation($deployTask, $node);

        // 如果是中国大陆，配置镜像加速器
        if ($this->isChinaMainland($locationInfo)) {
            $deployTask->appendLog('检测到中国大陆IP，配置Docker镜像加速器...');
            $this->setupDockerMirror($deployTask, $node);
        } else {
            $deployTask->appendLog('非中国大陆IP，使用默认镜像源');
        }
    }

    /**
     * 检查镜像加速器配置是否生效
     */
    public function verifyMirrorConfiguration(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('验证镜像加速器配置...');

        $verifyCommand = $this->remoteCommandService->createCommand(
            $node,
            '验证镜像加速器配置',
            'docker info | grep -A 10 "Registry Mirrors" || echo "无镜像加速器配置"',
            null,
            true,
            30,
            ['verify_mirror_config']
        );

        $this->remoteCommandService->executeCommand($verifyCommand);
        $result = $verifyCommand->getResult() ?? '';
        $deployTask->appendLog('镜像加速器验证结果: ' . trim($result));
    }

    /**
     * 测试镜像拉取性能
     */
    public function testImagePullPerformance(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('测试镜像拉取性能...');

        // 测试拉取一个小镜像
        $testCommand = $this->remoteCommandService->createCommand(
            $node,
            '测试镜像拉取',
            'time docker pull hello-world:latest 2>&1 | grep -E "(real|已完成|Complete)" || echo "拉取测试完成"',
            null,
            true,
            120,
            ['test_image_pull']
        );

        $this->remoteCommandService->executeCommand($testCommand);
        $result = $testCommand->getResult() ?? '';
        $deployTask->appendLog('镜像拉取测试结果: ' . trim($result));

        // 清理测试镜像
        $cleanupCommand = $this->remoteCommandService->createCommand(
            $node,
            '清理测试镜像',
            'docker rmi hello-world:latest 2>/dev/null || true',
            null,
            true,
            30,
            ['cleanup_test_image']
        );

        $this->remoteCommandService->executeCommand($cleanupCommand);
        $deployTask->appendLog('测试镜像清理完成');
    }

    /**
     * 检测服务器位置
     */
    private function detectServerLocation(ProgressModel $deployTask, Node $node): array
    {
        $ipCheckCommand = $this->remoteCommandService->createCommand(
            $node,
            '检测服务器IP位置',
            'curl -s --max-time 10 "http://ip-api.com/json/?fields=country,countryCode" 2>/dev/null || echo "{\"country\":\"Unknown\"}"',
            null,
            false,
            15,
            ['check_ip_location']
        );

        $this->remoteCommandService->executeCommand($ipCheckCommand);
        $ipResult = $ipCheckCommand->getResult() ?? '{"country":"Unknown"}';
        $deployTask->appendLog('IP检测结果: ' . trim($ipResult));

        // 解析结果
        $locationData = json_decode(trim($ipResult), true);
        
        return [
            'country' => $locationData['country'] ?? 'Unknown',
            'countryCode' => $locationData['countryCode'] ?? 'Unknown'
        ];
    }

    /**
     * 判断是否为中国大陆
     */
    private function isChinaMainland(array $locationInfo): bool
    {
        $country = $locationInfo['country'];
        $countryCode = $locationInfo['countryCode'];
        
        return in_array($countryCode, ['CN', 'China']) || stripos($country, 'China') !== false;
    }

    /**
     * 配置Docker镜像加速器
     */
    private function setupDockerMirror(ProgressModel $deployTask, Node $node): void
    {
        // 创建Docker daemon配置目录
        $this->createDockerConfigDirectory($deployTask, $node);

        // 配置镜像加速器
        $this->configureDockerMirrorConfig($deployTask, $node);

        // 重启Docker服务以应用配置
        $this->restartDockerService($deployTask, $node);

        $deployTask->appendLog('Docker镜像加速器配置完成');
    }

    /**
     * 创建Docker配置目录
     */
    private function createDockerConfigDirectory(ProgressModel $deployTask, Node $node): void
    {
        $createDirCommand = $this->remoteCommandService->createCommand(
            $node,
            '创建Docker配置目录',
            'mkdir -p /etc/docker',
            null,
            true,
            30,
            ['create_docker_config_dir']
        );

        $this->remoteCommandService->executeCommand($createDirCommand);
        $this->handleCommandResult($createDirCommand, $deployTask, '创建Docker配置目录');
    }

    /**
     * 配置Docker镜像加速器配置文件
     */
    private function configureDockerMirrorConfig(ProgressModel $deployTask, Node $node): void
    {
        $mirrorConfig = json_encode([
            'registry-mirrors' => [
                'https://docker.1panel.live',
                'https://dockerhub.azk8s.cn',
                'https://docker.mirrors.ustc.edu.cn'
            ],
            'insecure-registries' => [],
            'debug' => false,
            'experimental' => false
        ], JSON_PRETTY_PRINT);

        $configCommand = $this->remoteCommandService->createCommand(
            $node,
            '配置Docker镜像加速器',
            "cat > /etc/docker/daemon.json << 'EOF'\n{$mirrorConfig}\nEOF",
            null,
            true,
            30,
            ['config_docker_mirror']
        );

        $this->remoteCommandService->executeCommand($configCommand);
        $this->handleCommandResult($configCommand, $deployTask, '配置Docker镜像加速器');
    }

    /**
     * 重启Docker服务
     */
    private function restartDockerService(ProgressModel $deployTask, Node $node): void
    {
        $restartCommand = $this->remoteCommandService->createCommand(
            $node,
            '重启Docker服务',
            'systemctl restart docker && sleep 3',
            null,
            true,
            60,
            ['restart_docker']
        );

        $this->remoteCommandService->executeCommand($restartCommand);
        $this->handleCommandResult($restartCommand, $deployTask, '重启Docker服务');
    }

    /**
     * 处理命令执行结果
     */
    private function handleCommandResult(RemoteCommand $command, ProgressModel $deployTask, string $stepName): void
    {
        $result = $command->getResult() ?? '';
        $status = $command->getStatus();
        
        // 检查命令是否真正成功执行
        $hasError = $this->checkCommandError($result);
        
        if ($status === \ServerCommandBundle\Enum\CommandStatus::COMPLETED && !$hasError) {
            $deployTask->appendLog("{$stepName}执行成功");
            if ('' !== $result) {
                $deployTask->appendLog("执行结果: " . trim($result));
            }
            $this->logger->info('Docker注册表步骤执行成功', [
                'step' => $stepName,
                'command' => $command->getCommand(),
                'result' => $result,
            ]);
        } else {
            $errorMsg = $hasError ? 
                "{$stepName}执行失败: " . trim($result) : 
                "{$stepName}执行失败: 命令状态 " . $status->value;
                
            $deployTask->appendLog($errorMsg);
            
            $this->logger->error('Docker注册表步骤执行失败', [
                'step' => $stepName,
                'command' => $command->getCommand(),
                'result' => $result,
                'status' => $status->value,
                'hasError' => $hasError,
            ]);
            
            throw DockerRegistryException::directoryCreationFailed();
        }
    }
    
    /**
     * 检查命令输出中是否包含错误信息
     */
    private function checkCommandError(string $output): bool
    {
        $errorPatterns = [
            'ERROR:',
            'Error:',
            'FAILED',
            'failed to',
            'Cannot connect to the Docker daemon',
            'docker: Error response from daemon',
            'No such file or directory',
            'Permission denied',
            'command not found',
            'Operation not permitted',
            'Access denied',
        ];

        // 先检查是否有sudo密码提示，如果只是密码提示不算错误
        if (preg_match('/^\[sudo\] password for .+:/', trim($output))) {
            return false;
        }

        foreach ($errorPatterns as $pattern) {
            if (stripos($output, $pattern) !== false) {
                // 进一步检查是否是在sudo提示之后的真正错误
                $lines = explode("\n", $output);
                foreach ($lines as $line) {
                    $cleanLine = trim($line);
                    if (stripos($cleanLine, $pattern) !== false && 
                        !preg_match('/^\[sudo\] password for .+:/', $cleanLine)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
