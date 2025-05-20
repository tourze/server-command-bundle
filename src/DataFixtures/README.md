# ServerCommandBundle 数据填充（Fixtures）

该目录包含用于 ServerCommandBundle 的数据填充（Fixtures）。

## 可用的 Fixtures

1. `RemoteCommandFixtures` - 基本的远程命令样例
2. `MaintenanceCommandFixtures` - 维护相关的远程命令样例

## 依赖关系

这些 Fixtures 依赖于 `ServerNodeBundle\DataFixtures\NodeFixtures`，确保在加载之前已经加载了 NodeFixtures。

## 使用方法

### 加载所有 Fixtures

```bash
php bin/console doctrine:fixtures:load
```

### 只加载特定的 Fixtures

```bash
# 加载所有服务器命令相关的 Fixtures
php bin/console doctrine:fixtures:load --group=server-command

# 只加载系统命令
php bin/console doctrine:fixtures:load --group=system-commands

# 只加载维护命令
php bin/console doctrine:fixtures:load --group=maintenance-commands
```

### 追加数据（不清空数据库）

```bash
php bin/console doctrine:fixtures:load --append
```

## Fixture 组

- `server-command` - 所有服务器命令相关的 Fixtures
- `system-commands` - 只包含 RemoteCommandFixtures
- `maintenance-commands` - 只包含 MaintenanceCommandFixtures

## 引用

您可以在其他 Fixtures 中引用以下对象：

### RemoteCommandFixtures

- `RemoteCommandFixtures::SYSTEM_UPDATE_COMMAND` - 系统更新命令
- `RemoteCommandFixtures::SYSTEM_RESTART_COMMAND` - 系统重启命令
- `RemoteCommandFixtures::NGINX_RESTART_COMMAND` - Nginx重启命令
- `RemoteCommandFixtures::DISK_SPACE_COMMAND` - 磁盘空间查询命令

### MaintenanceCommandFixtures

- `MaintenanceCommandFixtures::CLEANUP_LOGS_COMMAND` - 日志清理命令
- `MaintenanceCommandFixtures::MYSQL_BACKUP_COMMAND` - MySQL备份命令
- `MaintenanceCommandFixtures::SERVICE_CHECK_COMMAND` - 服务检查命令

## 示例代码

```php
// 在其他 Fixture 中引用
use ServerCommandBundle\DataFixtures\RemoteCommandFixtures;
use ServerCommandBundle\Entity\RemoteCommand;

// ...

/** @var RemoteCommand $updateCommand */
$updateCommand = $this->getReference(RemoteCommandFixtures::SYSTEM_UPDATE_COMMAND, RemoteCommand::class);
```
