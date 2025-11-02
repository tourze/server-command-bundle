<?php

declare(strict_types=1);

namespace ServerCommandBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * 命令输出错误检查工具
 *
 * 统一处理远程命令执行结果的错误识别逻辑，
 * 避免在多个服务类中重复相同的错误模式匹配代码。
 */
#[Autoconfigure(public: true)]
class CommandOutputInspector
{
    /**
     * 常见的命令执行错误模式
     */
    private const ERROR_PATTERNS = [
        'command not found',
        'Permission denied',
        'No such file or directory',
        'cannot create directory',
        'Operation not permitted',
        'Access denied',
        'bash: line',
        'Error:',
        'ERROR:',
        'FAILED',
        'cannot access',
        'not found',
        'failed',
    ];

    /**
     * sudo 密码提示模式
     */
    private const SUDO_PASSWORD_PATTERNS = [
        '[sudo] password for',
        'Sorry, try again',
    ];

    /**
     * 检查命令输出中是否包含错误信息
     */
    public function hasError(string $output): bool
    {
        foreach (self::ERROR_PATTERNS as $pattern) {
            if ($this->hasPatternInOutput($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查是否为 sudo 密码提示
     */
    private function isSudoPasswordPrompt(string $output): bool
    {
        foreach (self::SUDO_PASSWORD_PATTERNS as $pattern) {
            if (false !== stripos($output, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * 检查输出中是否包含指定模式
     * 逐行检查，忽略 sudo 密码提示行
     */
    private function hasPatternInOutput(string $output, string $pattern): bool
    {
        if (false === stripos($output, $pattern)) {
            return false;
        }

        $lines = explode("\n", $output);
        foreach ($lines as $line) {
            $cleanLine = trim($line);
            if (false !== stripos($cleanLine, $pattern) && !$this->isSudoPasswordPrompt($cleanLine)) {
                return true;
            }
        }

        return false;
    }
}