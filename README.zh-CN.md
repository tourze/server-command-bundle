# ServerCommandBundle

[English](README.md) | [中文](README.zh-CN.md)

[![Latest Version](https://img.shields.io/packagist/v/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![Build Status](https://img.shields.io/github/actions/workflow/status/tourze/php-monorepo/ci.yml?style=flat-square)](https://github.com/tourze/php-monorepo/actions)
[![Quality Score](https://img.shields.io/scrutinizer/g/tourze/php-monorepo.svg?style=flat-square)](https://scrutinizer-ci.com/g/tourze/php-monorepo)
[![Total Downloads](https://img.shields.io/packagist/dt/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![Coverage Status](https://img.shields.io/codecov/c/github/tourze/php-monorepo.svg?style=flat-square)](https://codecov.io/gh/tourze/php-monorepo)
[![License](https://img.shields.io/packagist/l/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/tourze/server-command-bundle.svg?style=flat-square)](https://packagist.org/packages/tourze/server-command-bundle)

一个全面的 Symfony 服务器命令执行 Bundle，提供远程 SSH 命令执行、文件传输、Web 终端模拟器和服务器管理功能。

## 目录

- [功能特性](#功能特性)
- [系统要求](#系统要求)
- [安装](#安装)
- [路由配置](#路由配置)
- [快速入门](#快速入门)
- [SSH 认证](#ssh-认证)
- [API 接口](#api-接口)
- [开发与测试](#开发与测试)
- [高级用法](#高级用法)
- [高级功能](#高级功能)
- [故障排除](#故障排除)
- [贡献](#贡献)
- [参考文档](#参考文档)
- [License](#license)

## 功能特性

- **远程 SSH 命令执行** - 在远程服务器上执行命令，并完整追踪状态
- **Web 终端模拟器** - 基于浏览器的终端界面，带命令历史功能
- **文件传输** - 基于 SFTP 的文件上传/下载，带进度追踪
- **Docker 环境管理** - 自动化 Docker 安装和配置
- **DNS 配置管理** - DNS 污染检测和修复
- **多种认证方式** - SSH 密钥认证，带密码回退机制
- **异步处理** - 集成消息队列支持长时间运行的命令
- **EasyAdmin 集成** - 完整的管理界面进行命令管理
- **全面日志** - 详细的执行日志和错误追踪

## 系统要求

- PHP 8.1 或更高版本
- Symfony 6.4 或更高版本
- ext-filter 扩展
- tourze/server-node-bundle 用于节点管理

## 安装

### 通过 Composer 安装

```bash
composer require tourze/server-command-bundle
```

### Bundle 注册

```php
// config/bundles.php
return [
    // ...
    ServerCommandBundle\ServerCommandBundle::class => ['all' => true],
    // ...
];
```

## 路由配置

如需自定义路由前缀：

```yaml
# config/routes.yaml
server_command_terminal:
    resource: "@ServerCommandBundle/Controller/TerminalController.php"
    type: attribute
    prefix: /admin/terminal
```

## 快速入门

### 远程命令执行

```php
<?php

use ServerNodeBundle\Entity\Node;
use ServerCommandBundle\Service\RemoteCommandService;

// 获取服务
$service = $container->get(RemoteCommandService::class);

// 创建命令
$command = $service->createCommand(
    $node,              // ServerNodeBundle\Entity\Node 实例
    '系统更新',         // 命令名称
    'apt update',       // 命令内容
    '/root',           // 工作目录
    true,              // 使用 sudo
    300,               // 超时时间（秒）
    ['system']         // 标签
);

// 同步执行
$result = $service->executeCommand($command);

// 通过消息队列异步执行
$service->scheduleCommand($command);
```

### 文件传输

```php
<?php

use ServerCommandBundle\Service\RemoteFileService;

// 获取服务
$service = $container->get(RemoteFileService::class);

// 创建文件传输
$transfer = $service->createTransfer(
    $node,                          // 目标节点
    '上传配置',                    // 传输名称
    '/local/path/config.json',      // 本地文件路径
    '/remote/path/config.json',     // 远程文件路径
    true,                          // 使用 sudo
    300,                           // 超时时间
    ['config']                     // 标签
);

// 执行传输
$result = $service->executeTransfer($transfer);
```

### Web 终端使用

1. 访问管理面板
2. 导航到“服务器管理”-> “终端”
3. 选择你的服务器节点
4. 在基于 Web 的终端中执行命令

**终端功能：**
- 支持多个服务器节点
- 使用箭头键导航的命令历史
- 实时执行状态和计时
- 自动执行日志记录
- 类似终端的显示样式

## SSH 认证

该 Bundle 支持多种 SSH 认证方式，带智能回退机制：

### 1. SSH 密钥认证（推荐）

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----
...
-----END PRIVATE KEY-----');
```

**支持的密钥格式：**
- RSA 私钥 (`-----BEGIN RSA PRIVATE KEY-----`)
- OpenSSH 私钥 (`-----BEGIN OPENSSH PRIVATE KEY-----`)
- PKCS#8 私钥 (`-----BEGIN PRIVATE KEY-----`)
- 加密私钥 (`-----BEGIN ENCRYPTED PRIVATE KEY-----`)

### 2. 密码认证

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPassword('your_password');
```

### 3. 混合认证（智能回退）

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----');
$node->setSshPassword('fallback_password'); // 密钥认证失败时使用
```

**认证流程：**
1. **优先密钥认证**：如果配置了私钥，先尝试密钥认证
2. **回退密码认证**：如果密钥认证失败或未配置密钥，尝试密码认证
3. **连接失败**：如果所有方法都失败，抛出连接异常

## API 接口

### 终端 API

```bash
# 执行命令
POST /admin/terminal/execute
{
    "nodeId": "1",
    "command": "ls -la",
    "workingDir": "/root",
    "useSudo": false
}

# 获取命令历史
GET /admin/terminal/history/{nodeId}
```

## 控制台命令

```bash
# 执行指定ID的命令
php bin/console server-node:remote-command:execute <command-id>

# 创建并执行新命令
php bin/console server-node:remote-command:execute \
    --node-id=1 \
    --name="系统更新" \
    --command="apt update && apt upgrade -y" \
    --working-dir="/root" \
    --sudo \
    --timeout=600

# 执行所有待执行的命令
php bin/console server-node:remote-command:execute --execute-all-pending
```

## 开发与测试

### 数据填充

该 Bundle 为开发和测试提供了全面的数据填充：

```bash
# 加载所有填充数据
php bin/console doctrine:fixtures:load --group=server_ssh

# 加载 SSH 密钥认证演示数据
php bin/console doctrine:fixtures:load --group=ssh-key-auth

# 加载终端命令演示数据
php bin/console doctrine:fixtures:load --group=terminal-commands

# 追加数据（不清除现有数据）
php bin/console doctrine:fixtures:load --append --group=server_ssh
```

**填充数据类别：**
- **脚本**：系统信息、磁盘清理、服务状态检查
- **执行记录**：各种命令状态和结果
- **SSH 认证**：密钥认证测试和回退演示
- **终端历史**：常用 Linux 命令示例

## 高级用法

### 自定义命令处理器

为特殊操作创建自定义命令处理器：

```php
<?php

use ServerCommandBundle\Service\RemoteCommandService;
use ServerCommandBundle\Entity\RemoteCommand;

class CustomCommandHandler
{
    public function __construct(
        private RemoteCommandService $commandService
    ) {}

    public function deployApplication(Node $node, string $version): void
    {
        $command = new RemoteCommand();
        $command->setNode($node)
            ->setName("部署应用程序 v{$version}")
            ->setCommand("cd /var/www && git pull && git checkout {$version}")
            ->setWorkingDirectory('/var/www')
            ->setUseSudo(false);

        $this->commandService->executeCommand($command);
    }
}
```

### 批量操作

在多个节点上执行多个命令：

```php
<?php

use ServerCommandBundle\Service\BatchCommandService;

// 在多个节点上执行相同命令
$batchService->executeOnNodes(
    nodes: [$node1, $node2, $node3],
    command: 'systemctl restart nginx',
    parallel: true
);

// 按顺序执行不同命令
$batchService->executeSequence([
    ['node' => $node1, 'command' => 'service mysql stop'],
    ['node' => $node1, 'command' => 'mysqldump --all-databases > backup.sql'],
    ['node' => $node1, 'command' => 'service mysql start'],
]);
```

### 事件监听器

监听命令执行事件：

```php
<?php

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use ServerCommandBundle\Event\CommandExecutedEvent;

class CommandAuditSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            CommandExecutedEvent::class => 'onCommandExecuted',
        ];
    }

    public function onCommandExecuted(CommandExecutedEvent $event): void
    {
        $command = $event->getCommand();
        $this->logger->info('命令已执行', [
            'command' => $command->getCommand(),
            'node' => $command->getNode()->getName(),
            'status' => $command->getStatus(),
        ]);
    }
}
```

## 高级功能

### Docker 环境管理

```php
<?php

use ServerCommandBundle\Service\Quick\DockerEnvironmentService;

// 自动化 Docker 安装和配置
$dockerService = $container->get(DockerEnvironmentService::class);
$dockerService->checkDockerEnvironment($progressModel, $node);
```

### DNS 配置管理

```php
<?php

use ServerCommandBundle\Service\Quick\DnsConfigurationService;

// DNS 污染检测和修复
$dnsService = $container->get(DnsConfigurationService::class);
$dnsService->checkAndFixDns($progressModel, $node);
```

## 故障排除

### 常见问题

1. **SSH 连接失败**
    - 验证服务器节点 SSH 连接设置
    - 检查 SSH 访问防火墙规则
    - 验证 SSH 密钥格式和权限

2. **命令执行超时**
    - 在创建命令时调整超时值
    - 检查网络连接稳定性
    - 监控服务器资源使用情况

3. **权限错误**
    - 验证 SSH 用户是否具有所需命令权限
    - 检查特权操作的 sudo 配置
    - 验证文件/目录访问权限

### 安全考虑

- 尽可能使用 SSH 密钥认证
- 为终端功能实施适当的访问控制
- 监控和记录所有命令执行
- 定期轮换 SSH 密钥和密码
- 仅限制对必要命令的 sudo 访问

## 贡献

欢迎贡献！请确保：
- 代码遵循 PSR-12 标准
- 所有测试通过 (`./vendor/bin/phpunit`)
- PHPStan 分析无问题 (`./vendor/bin/phpstan analyse`)
- 新功能更新文档

## 参考文档

- [phpseclib3 文档](https://phpseclib.com/docs/ssh)
- [Symfony Messenger 组件](https://symfony.com/doc/current/messenger.html)
- [EasyAdmin Bundle](https://symfony.com/doc/current/bundles/EasyAdminBundle/index.html)

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
