<?php

namespace ServerCommandBundle\Service\Quick;

use ServerCommandBundle\Contracts\ProgressModel;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Service\RemoteCommandService;
use ServerNodeBundle\Entity\Node;

/**
 * Docker环境服务
 */
class DockerEnvironmentService
{
    public function __construct(
        private readonly RemoteCommandService $remoteCommandService,
    ) {
    }

    /**
     * 检查Docker环境
     */
    public function checkDockerEnvironment(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->setProgress(5);
        $deployTask->appendLog('检查Docker环境');

        // 检查Docker是否已安装（不使用sudo）
        $dockerCheckCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查Docker版本',
            'docker --version 2>/dev/null || echo "Docker未安装"',
            null,
            false, // 不使用sudo
            30,
            ['ssr_deploy', 'docker_check']
        );

        $this->remoteCommandService->executeCommand($dockerCheckCommand);
        $result = $dockerCheckCommand->getResult() ?? '';
        $deployTask->appendLog('Docker检查结果: ' . trim($result));
        
        // 如果Docker未安装，自动安装
        if (stripos($result, 'Docker未安装') !== false || 
            stripos($result, 'command not found') !== false ||
            stripos($result, 'not found') !== false) {
            $deployTask->appendLog('Docker未安装，开始自动安装...');
            $this->installDockerOnLinux($deployTask, $node);
        } else {
            $deployTask->appendLog('Docker已安装，版本: ' . trim($result));
            
            // 检查Docker服务是否正常运行
            $dockerInfoCommand = $this->remoteCommandService->createCommand(
                $node,
                '检查Docker服务状态',
                'docker info >/dev/null 2>&1 && echo "Docker正常" || echo "Docker服务异常"',
                null,
                false,
                30,
                ['ssr_deploy', 'docker_info_check']
            );

            $this->remoteCommandService->executeCommand($dockerInfoCommand);
            $infoResult = $dockerInfoCommand->getResult() ?? '';
            $deployTask->appendLog('Docker服务检查: ' . trim($infoResult));
            
            if (stripos($infoResult, 'Docker正常') !== false) {
                $deployTask->appendLog('Docker环境检查通过');
            } else {
                $deployTask->appendLog('Docker已安装但服务异常，尝试启动服务...');
                $this->startDockerService($deployTask, $node);
                
                // 重新验证
                $this->verifyDockerAfterStart($deployTask, $node);
            }
        }
    }

    /**
     * 在Linux系统上安装Docker
     */
    private function installDockerOnLinux(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->setProgress(7);
        $deployTask->appendLog('开始在Linux系统安装Docker...');

        // 检查系统类型
        $systemCheckCommand = $this->remoteCommandService->createCommand(
            $node,
            '检查系统信息',
            'cat /etc/os-release 2>/dev/null || echo "无法检测系统版本"',
            null,
            false,
            30,
            ['ssr_deploy', 'check_os']
        );
        
        $this->remoteCommandService->executeCommand($systemCheckCommand);
        $osInfo = $systemCheckCommand->getResult() ?? '';
        $deployTask->appendLog('系统信息: ' . substr(trim($osInfo), 0, 200));

        // 安装curl（如果没有的话）
        $deployTask->appendLog('准备安装依赖工具...');
        $installCurlCommand = $this->remoteCommandService->createCommand(
            $node,
            '安装curl',
            'which curl >/dev/null 2>&1 && echo "curl已存在" || (apt install -y curl 2>/dev/null || yum install -y curl 2>/dev/null || dnf install -y curl 2>/dev/null || echo "curl安装失败")',
            null,
            true,
            120,
            ['ssr_deploy', 'install_curl']
        );

        $this->remoteCommandService->executeCommand($installCurlCommand);
        $curlResult = $installCurlCommand->getResult() ?? '';
        $deployTask->appendLog('curl安装结果: ' . trim($curlResult));

        // 下载并执行Docker安装脚本
        $deployTask->appendLog('下载Docker官方安装脚本...');
        $downloadDockerScript = $this->remoteCommandService->createCommand(
            $node,
            '下载Docker安装脚本',
            'curl -fsSL https://get.docker.com -o /tmp/get-docker.sh && echo "脚本下载成功" || echo "脚本下载失败"',
            null,
            true,
            120,
            ['ssr_deploy', 'download_docker_script']
        );

        $this->remoteCommandService->executeCommand($downloadDockerScript);
        $downloadResult = $downloadDockerScript->getResult() ?? '';
        $deployTask->appendLog('下载结果: ' . trim($downloadResult));
        
        if (stripos($downloadResult, '脚本下载失败') !== false) {
            throw new \RuntimeException('无法下载Docker安装脚本，请检查网络连接');
        }

        // 执行Docker安装脚本
        $deployTask->appendLog('执行Docker安装脚本（这可能需要几分钟）...');
        $installDockerCommand = $this->remoteCommandService->createCommand(
            $node,
            '安装Docker',
            'sh /tmp/get-docker.sh && echo "Docker安装完成" || echo "Docker安装失败"',
            null,
            true,
            600,
            ['ssr_deploy', 'install_docker']
        );

        $this->remoteCommandService->executeCommand($installDockerCommand);
        $installResult = $installDockerCommand->getResult() ?? '';
        $deployTask->appendLog('安装结果: ' . substr(trim($installResult), -500)); // 只显示最后500字符
        
        if (stripos($installResult, 'Docker安装失败') !== false) {
            throw new \RuntimeException('Docker安装失败: ' . $installResult);
        }

        // 将当前用户添加到docker组（避免sudo）
        $deployTask->appendLog('配置Docker用户权限...');
        $addUserCommand = $this->remoteCommandService->createCommand(
            $node,
            '添加用户到docker组',
            'usermod -aG docker $USER && echo "用户权限配置完成" || echo "用户权限配置失败"',
            null,
            true,
            30,
            ['ssr_deploy', 'add_user_docker']
        );

        $this->remoteCommandService->executeCommand($addUserCommand);
        $userResult = $addUserCommand->getResult() ?? '';
        $deployTask->appendLog('用户权限配置: ' . trim($userResult));

        // 启动Docker服务
        $this->startDockerService($deployTask, $node);

        // 等待Docker服务完全启动
        $deployTask->appendLog('等待Docker服务完全启动...');
        sleep(5);

        // 验证Docker安装 - 使用sudo确保能访问
        $verifyDockerCommand = $this->remoteCommandService->createCommand(
            $node,
            '验证Docker安装',
            'docker --version && docker info >/dev/null 2>&1 && echo "Docker验证成功" || echo "Docker验证失败"',
            null,
            true, // 使用sudo验证
            60,
            ['ssr_deploy', 'verify_docker']
        );

        $this->remoteCommandService->executeCommand($verifyDockerCommand);
        $verifyResult = $verifyDockerCommand->getResult() ?? '';
        $deployTask->appendLog('Docker验证: ' . trim($verifyResult));
        
        if (stripos($verifyResult, 'Docker验证失败') !== false) {
            throw new \RuntimeException('Docker安装完成但验证失败，可能需要重新登录或重启系统');
        }

        $deployTask->appendLog('Docker安装并验证完成！');
    }

    /**
     * 启动Docker服务
     */
    private function startDockerService(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('启动Docker服务...');

        // 尝试systemctl启动
        $systemctlCommand = $this->remoteCommandService->createCommand(
            $node,
            '使用systemctl启动Docker',
            'systemctl enable docker && systemctl start docker',
            null,
            true,
            60,
            ['ssr_deploy', 'systemctl_docker']
        );

        $this->remoteCommandService->executeCommand($systemctlCommand);
        
        // 检查systemctl是否成功
        if ($systemctlCommand->getStatus() === CommandStatus::COMPLETED && 
            !$this->checkCommandError($systemctlCommand->getResult() ?? '')) {
            $deployTask->appendLog('Docker服务已通过systemctl启动');
            return;
        }

        // systemctl失败，尝试service命令
        $serviceCommand = $this->remoteCommandService->createCommand(
            $node,
            '使用service启动Docker',
            'service docker start',
            null,
            true,
            60,
            ['ssr_deploy', 'service_docker']
        );

        $this->remoteCommandService->executeCommand($serviceCommand);
        
        if ($serviceCommand->getStatus() === CommandStatus::COMPLETED && 
            !$this->checkCommandError($serviceCommand->getResult() ?? '')) {
            $deployTask->appendLog('Docker服务已通过service启动');
            return;
        }

        // 两种方式都失败，尝试直接启动dockerd
        $dockerdCommand = $this->remoteCommandService->createCommand(
            $node,
            '直接启动dockerd',
            'nohup dockerd > /var/log/docker.log 2>&1 & sleep 3',
            null,
            true,
            30,
            ['ssr_deploy', 'dockerd_direct']
        );

        $this->remoteCommandService->executeCommand($dockerdCommand);
        $deployTask->appendLog('尝试直接启动dockerd守护进程');

        // 最后验证Docker是否运行
        $testDockerCommand = $this->remoteCommandService->createCommand(
            $node,
            '测试Docker状态',
            'docker info',
            null,
            true,
            30,
            ['ssr_deploy', 'test_docker']
        );

        $this->remoteCommandService->executeCommand($testDockerCommand);
        $this->handleCommandResult($testDockerCommand, $deployTask, '验证Docker服务状态');
    }

    /**
     * 启动服务后验证Docker
     */
    private function verifyDockerAfterStart(ProgressModel $deployTask, Node $node): void
    {
        $deployTask->appendLog('重新验证Docker服务...');
        
        $verifyCommand = $this->remoteCommandService->createCommand(
            $node,
            '验证Docker服务',
            'docker info >/dev/null 2>&1 && echo "验证成功" || echo "验证失败"',
            null,
            false,
            30,
            ['ssr_deploy', 'verify_after_start']
        );

        $this->remoteCommandService->executeCommand($verifyCommand);
        $result = $verifyCommand->getResult() ?? '';
        $deployTask->appendLog('验证结果: ' . trim($result));
        
        if (stripos($result, '验证成功') === false) {
            throw new \RuntimeException('Docker服务启动后仍无法正常工作，请检查系统配置');
        }
        
        $deployTask->appendLog('Docker环境验证通过');
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
        
        if ($status === CommandStatus::COMPLETED && !$hasError) {
            $deployTask->appendLog("{$stepName}执行成功");
            if ($result) {
                $deployTask->appendLog("执行结果: " . trim($result));
            }
        } else {
            $errorMsg = $hasError ? 
                "{$stepName}执行失败: " . trim($result) : 
                "{$stepName}执行失败: 命令状态 " . $status->value;
                
            $deployTask->appendLog($errorMsg);
            throw new \RuntimeException($errorMsg);
        }
    }
    
    /**
     * 检查命令输出中是否包含错误信息
     */
    private function checkCommandError(string $output): bool
    {
        // 先检查是否只是sudo密码提示
        if (preg_match('/^\[sudo\] password for .+:/', trim($output))) {
            return false;
        }

        $errorPatterns = [
            'command not found',
            'Permission denied',
            'No such file or directory',
            'cannot create directory',
            'Operation not permitted',
            'Access denied',
            'bash: line',
            'Error:',
            'ERROR:',
            'Failed to',
            'failed to',
            'Interactive authentication required',
            'sudo: no tty present',
            'sudo: unable to resolve host',
            'service: command not found',
            'systemctl: command not found',
        ];

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
