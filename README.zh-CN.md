# server-command-bundle

服务器 SSH 命令执行工具，用于在远程服务器上执行命令并管理命令执行状态。

## 安装

```bash
composer require tourze/server-command-bundle
```

## 功能特性

- 远程 SSH 命令执行与管理
- 命令执行状态记录与追踪
- 支持同步和异步执行命令
- 提供 sudo 命令执行支持
- 使用 phpseclib3 库进行 SSH 连接，不再依赖 ServerNodeBundle

## 使用方法

```php
<?php

use ServerNodeBundle\Entity\Node;
use ServerCommandBundle\Service\RemoteCommandService;

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

// 异步执行命令（通过消息队列）
$service->scheduleCommand($command);
```

## 配置

使用时无需额外配置，直接加载 bundle 即可：

```php
// config/bundles.php
return [
    // ...
    ServerCommandBundle\ServerCommandBundle::class => ['all' => true],
    // ...
];
```

## 常见问题

- 命令执行超时：可以在创建命令时设置适当的超时时间
- SSH 连接失败：请检查服务器节点的 SSH 连接信息是否正确

## 参考文档

- [phpseclib3 文档](https://phpseclib.com/docs/ssh)
- [Symfony Messenger 组件](https://symfony.com/doc/current/messenger.html)
