<?php

/**
 * 临时脚本：转换RemoteCommand表中的tags字段数据
 * 将数组格式转换为JSON字符串格式
 */

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    // 连接数据库
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=ims_server', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4',
    ]);

    echo "开始转换tags字段数据...\n";

    // 查询所有有tags数据的记录
    $stmt = $pdo->query('SELECT id, tags FROM ims_server_remote_command WHERE tags IS NOT NULL');
    if (false === $stmt) {
        throw new PDOException('查询失败');
    }
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $converted = 0;
    $updateStmt = $pdo->prepare('UPDATE ims_server_remote_command SET tags = ? WHERE id = ?');

    foreach ($records as $record) {
        /** @var int|string $id */
        $id = $record['id'];
        /** @var mixed $tags */
        $tags = $record['tags'];

        // 如果已经是JSON字符串，跳过
        if (is_string($tags) && (null !== json_decode($tags) || 'null' === $tags)) {
            echo "ID {$id}: 已经是JSON格式，跳过\n";
            continue;
        }

        // 尝试反序列化PHP数组
        /** @var mixed $tagsArray */
        $tagsArray = is_string($tags) ? @unserialize($tags) : false;
        if (false === $tagsArray) {
            // 如果不是序列化数组，尝试直接作为字符串处理
            if (is_string($tags)) {
                $newValue = json_encode([$tags]);
            } else {
                $newValue = null;
            }
        } else {
            // 转换为JSON
            if (is_array($tagsArray)) {
                $filteredTags = array_filter(array_map(fn (mixed $tag): string => trim(is_string($tag) ? $tag : (is_scalar($tag) ? (string) $tag : '')), $tagsArray), fn ($tag) => '' !== $tag);
                $newValue = (0 === count($filteredTags)) ? null : json_encode(array_values($filteredTags));
            } else {
                $newValue = null;
            }
        }

        // 更新记录
        $updateStmt->execute([$newValue, $id]);
        ++$converted;

        $idStr = is_scalar($id) ? (string) $id : 'unknown';
        echo "ID {$idStr}: 转换完成 -> " . (null !== $newValue ? $newValue : 'NULL') . "\n";
    }

    echo "\n转换完成！共处理 {$converted} 条记录\n";
} catch (Exception $e) {
    echo '错误: ' . $e->getMessage() . "\n";
    exit(1);
}
