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
- **支持SSH密钥认证和密码认证**
- Shell脚本存储和执行
- 脚本执行结果记录和追踪
- **服务器终端模拟器** - 提供类似终端的Web界面

## SSH认证方式

Bundle 支持两种SSH认证方式，具有智能回退机制：

### 1. SSH密钥认证（推荐）

使用SSH私钥进行认证，安全性更高：

```php
<?php

use ServerNodeBundle\Entity\Node;

$node = new Node();
$node->setSshHost('192.168.1.100');
$node->setSshPort(22);
$node->setSshUser('root');
$node->setSshPrivateKey('-----BEGIN PRIVATE KEY-----
MIIEvQIBADANBgkqhkiG9w0BAQEFAASCBKcwggSjAgEAAoIBAQC7VJTUt9Us8cKB
UO1WOeNcPugFiTt0OzUpPtU3RLXZJ5VBL+wJ4w4YhOGxF5GhB8iV2jWzYkQpJLqE
...
-----END PRIVATE KEY-----');
```

支持的私钥格式：

- **RSA私钥** (`-----BEGIN RSA PRIVATE KEY-----`)
- **OpenSSH私钥** (`-----BEGIN OPENSSH PRIVATE KEY-----`)
- **PKCS#8私钥** (`-----BEGIN PRIVATE KEY-----`)
- **加密私钥** (`-----BEGIN ENCRYPTED PRIVATE KEY-----`)

### 2. 密码认证

使用用户名和密码进行认证：

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

同时配置私钥和密码，系统会优先使用私钥认证，失败时自动回退到密码认证：

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
$node->setSshPassword('fallback_password'); // 私钥认证失败时使用
```

### 认证流程

1. **优先私钥认证**：如果配置了私钥，系统首先尝试使用私钥认证
2. **降级密码认证**：如果私钥认证失败或未配置私钥，尝试使用密码认证
3. **认证失败**：如果所有认证方式都失败，抛出连接异常

系统会记录详细的认证日志，便于调试连接问题。

## 使用方法

### 服务器终端模拟器

Bundle 提供了一个Web终端界面，可以通过后台管理系统访问：

1. 登录后台管理系统
2. 在左侧菜单中选择 "服务器管理" -> "服务器终端"
3. 在顶部选择要连接的服务器节点
4. 在底部输入命令并按回车执行

**终端功能特性：**

- 支持选择不同的服务器节点
- 显示历史执行命令和结果
- 实时显示命令执行状态和耗时
- 支持上下箭头键浏览命令历史
- 模拟真实终端的显示样式
- 自动保存执行记录

**快捷键：**

- `Enter` - 执行命令
- `↑/↓` - 浏览命令历史

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

## API 接口

### 终端API

```bash
# 执行命令
POST /admin/terminal/execute
{
    "nodeId": "1",
    "command": "ls -la",
    "workingDir": "/root"
}

# 获取历史命令
GET /admin/terminal/history/{nodeId}
```

## 数据填充

Bundle 提供了用于开发和测试的数据填充功能：

```bash
# 加载所有数据填充
php bin/console doctrine:fixtures:load --group=server_ssh

# 仅加载脚本数据，不加载执行记录
php bin/console doctrine:fixtures:load --group=server_ssh_script 

# 加载SSH密钥认证演示数据
php bin/console doctrine:fixtures:load --group=ssh-key-auth

# 加载终端命令演示数据
php bin/console doctrine:fixtures:load --group=terminal-commands

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

3. **SSH密钥认证演示**：
   - SSH密钥认证测试命令
   - 混合认证回退演示
   - 密钥格式验证命令
   - 密钥权限检查示例

4. **终端命令历史**：
   - 常用Linux命令执行示例（ls、ps、df等）
   - 终端会话模拟数据

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

## 路由配置

如果需要自定义路由前缀，可以在路由配置中添加：

```yaml
# config/routes.yaml
server_command_terminal:
    resource: "@ServerCommandBundle/Controller/TerminalController.php"
    type: annotation
    prefix: /admin/terminal
```

## 注意事项

- 终端执行的命令默认超时时间为30秒
- 所有通过终端执行的命令都会被标记为 `terminal` 标签
- 历史命令最多显示最近20条记录
- 建议在生产环境中谨慎使用终端功能，避免执行危险命令

## 故障排除

### 常见问题

1. **SSH连接失败**
   - 检查服务器节点的SSH连接信息是否正确
   - 确认防火墙是否允许SSH连接

2. **命令执行超时**
   - 可以在创建命令时设置适当的超时时间
   - 检查网络连接是否稳定

3. **权限不足**
   - 确认SSH用户是否有执行相应命令的权限
   - 对于需要root权限的命令，检查sudo配置

## 参考文档

- [phpseclib3 文档](https://phpseclib.com/docs/ssh)
- [Symfony Messenger 组件](https://symfony.com/doc/current/messenger.html)
- [EasyAdmin Bundle](https://symfony.com/doc/current/bundles/EasyAdminBundle/index.html)

- [Symfony Messenger 组件](https://symfony.com/doc/current/messenger.html)
- [EasyAdmin Bundle](https://symfony.com/doc/current/bundles/EasyAdminBundle/index.html)
