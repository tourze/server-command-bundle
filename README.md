# ServerSshCommandBundle

服务器 SSH 命令执行 Bundle，用于在远程服务器上执行命令。

## 安装

```bash
composer require tourze/server-ssh-command-bundle
```

## 功能

- 远程命令执行
- 命令执行状态追踪
- 支持同步和异步执行命令
- 提供 sudo 命令执行支持
- 使用 phpseclib3 库进行 SSH 连接

## 使用方法

```php
<?php

use ServerNodeBundle\Entity\Node;
use ServerSshCommandBundle\Service\RemoteCommandService;

// 获取服务
$service = $container->get(RemoteCommandService::class);

// 创建命令
$command = $service->createCommand(
    $node,           // ServerNodeBundle\Entity\Node 实例
    '更新系统',       // 命令名称
    'apt update',    // 命令内容
    '/root',         // 工作目录
    true,            // 是否使用 sudo
    300,             // 超时时间（秒）
    ['system']       // 标签
);

// 同步执行命令
$result = $service->executeCommand($command);

// 异步执行命令
$service->scheduleCommand($command);
```

## 配置

使用时无需额外配置，直接加载 bundle 即可：

```php
// config/bundles.php
return [
    // ...
    ServerSshCommandBundle\ServerSshCommandBundle::class => ['all' => true],
    // ...
];
```
