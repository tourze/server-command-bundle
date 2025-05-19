# ServerCommandBundle

服务器 SSH 命令执行 Bundle，用于在远程服务器上执行命令。

## 安装

```bash
composer require tourze/server-command-bundle
```

## 功能

- 远程命令执行
- 命令执行状态追踪
- 支持同步和异步执行命令
- 提供 sudo 命令执行支持
- 使用 phpseclib3 库进行 SSH 连接
- Shell脚本存储和执行
- 脚本执行结果记录和追踪

## 使用方法

### 远程命令执行

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

// 异步执行命令
$service->scheduleCommand($command);
```

### Shell脚本管理和执行

```php
<?php

use ServerNodeBundle\Entity\Node;
use ServerCommandBundle\Service\ShellScriptService;

// 获取服务
$service = $container->get(ShellScriptService::class);

// 创建Shell脚本
$script = $service->createScript(
    '系统信息脚本',                   // 脚本名称
    '#!/bin/bash\nuname -a\ndf -h',  // 脚本内容
    '/root',                         // 工作目录
    true,                            // 是否使用 sudo
    300,                             // 超时时间（秒）
    ['system'],                      // 标签
    '获取系统基本信息'                 // 描述
);

// 在指定节点上执行脚本
$execution = $service->executeScript($script, $node);

// 异步执行脚本
$execution = $service->scheduleScript($script, $node);

// 查询脚本执行结果
$result = $execution->getResult();
$status = $execution->getStatus();
```

## 数据填充

Bundle 提供了用于开发和测试的数据填充功能：

```bash
# 加载所有数据填充
php bin/console doctrine:fixtures:load --group=server_ssh

# 仅加载脚本数据，不加载执行记录
php bin/console doctrine:fixtures:load --group=server_ssh_script 

# 追加数据（不清除现有数据）
php bin/console doctrine:fixtures:load --append --group=server_ssh
```

数据填充包含：

1. **Shell脚本**：
   - 系统信息脚本 - 收集系统基本信息
   - 磁盘清理脚本 - 清理临时文件和日志
   - 服务状态检查脚本 - 检查关键服务运行状态

2. **执行记录**：
   - 不同状态的脚本执行记录（已完成、失败、超时、运行中、待执行）
   - 真实的执行结果示例

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
