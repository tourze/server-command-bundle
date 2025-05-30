# 远程文件上传功能

## 概述

RemoteFileService 提供了将本地文件上传到远程服务器的功能。该服务采用两步上传策略：

1. 先将文件上传到远程服务器的 `/tmp` 目录
2. 使用命令将文件从临时目录移动到最终目标位置

这种方式确保了文件传输的安全性和完整性。

## 主要特性

- **两步上传策略**：先上传到临时目录，再移动到目标位置
- **sudo 支持**：可以使用 sudo 权限移动文件到需要权限的目录
- **传输状态跟踪**：完整的状态记录（PENDING → UPLOADING → MOVING → COMPLETED）
- **错误处理**：详细的错误信息和日志记录
- **文件验证**：上传后验证文件是否存在和完整
- **标签系统**：支持给传输任务添加标签便于分类管理

## 快速开始

### 基本用法

```php
use ServerCommandBundle\Service\RemoteFileService;
use ServerNodeBundle\Entity\Node;

// 注入服务
private RemoteFileService $remoteFileService;

// 上传文件（一步到位）
$transfer = $this->remoteFileService->uploadFile(
    $node,                    // 目标节点
    '/local/path/file.txt',   // 本地文件路径
    '/var/www/html/file.txt', // 远程目标路径
    true,                     // 是否使用sudo
    '上传配置文件'              // 可选：任务名称
);

echo "传输状态: " . $transfer->getStatus()->getLabel();
```

### 分步操作

```php
// 1. 创建传输任务
$transfer = $this->remoteFileService->createTransfer(
    $node,
    '上传应用配置',
    '/local/config/app.yaml',
    '/etc/myapp/app.yaml',
    true,  // 使用sudo（因为要写入/etc目录）
    600,   // 10分钟超时
    ['config', 'deployment']  // 标签
);

// 2. 执行传输
$result = $this->remoteFileService->executeTransfer($transfer);

// 3. 检查结果
if ($result->getStatus() === FileTransferStatus::COMPLETED) {
    echo "文件上传成功！";
    echo "耗时: " . $result->getTransferTime() . " 秒";
} else {
    echo "上传失败: " . $result->getResult();
}
```

## 传输状态

| 状态 | 说明 |
|------|------|
| PENDING | 等待传输 |
| UPLOADING | 正在上传到临时目录 |
| MOVING | 正在移动到目标位置 |
| COMPLETED | 传输完成 |
| FAILED | 传输失败 |
| CANCELED | 已取消 |

## 查询功能

```php
// 根据ID查找传输记录
$transfer = $this->remoteFileService->findById('123');

// 查找节点上待传输的文件
$pendingTransfers = $this->remoteFileService->findPendingTransfersByNode($node);

// 查找所有待传输的文件
$allPending = $this->remoteFileService->findAllPendingTransfers();

// 按标签查找
$configTransfers = $this->remoteFileService->findByTags(['config']);

// 使用Repository的高级查询
$repository = $this->remoteFileService->getRepository();
$failedTransfers = $repository->findFailedTransfers();
$recentTransfers = $repository->findByDateRange(
    new DateTime('-1 day'),
    new DateTime()
);
```

## 取消传输

```php
// 只能取消待传输状态的任务
if ($transfer->getStatus() === FileTransferStatus::PENDING) {
    $this->remoteFileService->cancelTransfer($transfer);
}
```

## 注意事项

1. **文件权限**：确保目标目录有适当的写权限，或使用 sudo 选项
2. **网络稳定性**：大文件传输时确保网络连接稳定
3. **磁盘空间**：确保远程服务器有足够的磁盘空间
4. **临时文件清理**：系统会自动清理临时文件，但建议定期检查 `/tmp` 目录
5. **超时设置**：根据文件大小和网络速度合理设置超时时间

## 实体字段说明

### RemoteFileTransfer 实体

- `node`: 目标服务器节点
- `name`: 传输任务名称
- `localPath`: 本地文件路径
- `remotePath`: 远程目标路径
- `tempPath`: 临时上传路径（自动生成）
- `fileSize`: 文件大小（字节）
- `useSudo`: 是否使用sudo移动文件
- `timeout`: 超时时间（秒）
- `status`: 传输状态
- `startedAt`: 开始传输时间
- `completedAt`: 完成时间
- `transferTime`: 传输耗时（秒）
- `result`: 结果信息或错误消息
- `tags`: 标签数组

## 错误处理

常见错误类型：

- `本地文件不存在`: 检查本地文件路径
- `SSH连接失败`: 检查节点配置和网络连接
- `SFTP认证失败`: 检查SSH认证信息
- `文件上传到临时目录失败`: 检查远程磁盘空间和权限
- `文件移动验证失败`: 检查目标路径权限和sudo配置

## 性能优化建议

1. **批量上传**：对于多个小文件，考虑先打包再上传
2. **并发控制**：避免同时进行大量文件传输
3. **网络优化**：在网络条件良好时进行大文件传输
4. **监控日志**：定期检查传输日志，及时发现问题

## 示例：部署脚本上传

```php
/**
 * 部署应用配置文件
 */
public function deployConfig(Node $node, string $configPath): bool
{
    try {
        $transfer = $this->remoteFileService->uploadFile(
            $node,
            $configPath,
            '/opt/myapp/config.yml',
            true, // 需要sudo权限
            '部署应用配置'
        );
        
        if ($transfer->getStatus() === FileTransferStatus::COMPLETED) {
            $this->logger->info('配置文件部署成功', [
                'node' => $node->getName(),
                'file' => $configPath,
                'transferTime' => $transfer->getTransferTime()
            ]);
            return true;
        }
        
        $this->logger->error('配置文件部署失败', [
            'node' => $node->getName(),
            'error' => $transfer->getResult()
        ]);
        return false;
        
    } catch (\Exception $e) {
        $this->logger->error('部署过程中出现异常', [
            'error' => $e->getMessage()
        ]);
        return false;
    }
}
```

这样就完成了一个功能完整的远程文件上传服务！
