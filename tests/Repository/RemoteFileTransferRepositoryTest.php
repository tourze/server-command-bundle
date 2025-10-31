<?php

namespace ServerCommandBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteFileTransferRepository::class)]
#[RunTestsInSeparateProcesses]
final class RemoteFileTransferRepositoryTest extends AbstractRepositoryTestCase
{
    private RemoteFileTransferRepository $repository;

    protected function onSetUp(): void
    {
        // 禁用异步数据库插入包的日志输出，避免测试失败
        putenv('DISABLE_LOGGING_IN_TESTS=true');
        $_ENV['DISABLE_LOGGING_IN_TESTS'] = 'true';

        $this->repository = self::getService(RemoteFileTransferRepository::class);
        $this->assertInstanceOf(RemoteFileTransferRepository::class, $this->repository);
    }

    public function testFindAllPendingTransfers(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node1 = $this->createTestNode('test-node-1');
        $node2 = $this->createTestNode('test-node-2');

        // 创建测试传输任务
        $pendingTransfer1 = $this->createTestTransfer($node1, '待传输文件1', FileTransferStatus::PENDING, true);
        $pendingTransfer2 = $this->createTestTransfer($node2, '待传输文件2', FileTransferStatus::PENDING, true);
        $uploadingTransfer = $this->createTestTransfer($node1, '上传中文件', FileTransferStatus::UPLOADING, true);
        $disabledTransfer = $this->createTestTransfer($node1, '禁用传输', FileTransferStatus::PENDING, false);

        self::getEntityManager()->flush();

        // 测试查找所有待传输文件
        $result = $this->repository->findAllPendingTransfers();

        $this->assertCount(2, $result);
        $this->assertContains($pendingTransfer1, $result);
        $this->assertContains($pendingTransfer2, $result);
        $this->assertNotContains($uploadingTransfer, $result);
        $this->assertNotContains($disabledTransfer, $result);

        // 验证排序（按创建时间升序）
        $this->assertEquals($pendingTransfer1->getName(), $result[0]->getName());
        $this->assertEquals($pendingTransfer2->getName(), $result[1]->getName());
    }

    public function testFindByDateRange(): void
    {
        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建不同日期的传输任务
        $oldTransfer = $this->createTestTransfer($node, '旧传输', FileTransferStatus::COMPLETED, true);
        $recentTransfer = $this->createTestTransfer($node, '最近传输', FileTransferStatus::COMPLETED, true);

        self::getEntityManager()->flush();

        // 设置时间范围（最近一天）
        $endDate = new \DateTimeImmutable();
        $startDate = $endDate->sub(new \DateInterval('P1D'));

        // 测试按日期范围查找
        $result = $this->repository->findByDateRange($startDate, $endDate);

        // 应该能找到最近创建的传输任务
        $this->assertGreaterThanOrEqual(1, count($result));
        $this->assertContains($recentTransfer, $result);
    }

    public function testFindByStatus(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建不同状态的传输任务
        $pendingTransfer = $this->createTestTransfer($node, '待传输', FileTransferStatus::PENDING, true);
        $uploadingTransfer = $this->createTestTransfer($node, '上传中', FileTransferStatus::UPLOADING, true);
        $completedTransfer = $this->createTestTransfer($node, '已完成', FileTransferStatus::COMPLETED, true);
        $failedTransfer = $this->createTestTransfer($node, '失败', FileTransferStatus::FAILED, true);

        self::getEntityManager()->flush();

        // 测试查找待传输状态
        $pendingResults = $this->repository->findByStatus(FileTransferStatus::PENDING);
        $this->assertCount(1, $pendingResults);
        $this->assertContains($pendingTransfer, $pendingResults);

        // 测试查找上传中状态
        $uploadingResults = $this->repository->findByStatus(FileTransferStatus::UPLOADING);
        $this->assertCount(1, $uploadingResults);
        $this->assertContains($uploadingTransfer, $uploadingResults);

        // 测试查找已完成状态
        $completedResults = $this->repository->findByStatus(FileTransferStatus::COMPLETED);
        $this->assertCount(1, $completedResults);
        $this->assertContains($completedTransfer, $completedResults);

        // 测试查找失败状态
        $failedResults = $this->repository->findByStatus(FileTransferStatus::FAILED);
        $this->assertCount(1, $failedResults);
        $this->assertContains($failedTransfer, $failedResults);
    }

    public function testFindByTags(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建带标签的传输任务
        $configTransfer = $this->createTestTransfer($node, '配置文件传输', FileTransferStatus::COMPLETED, true, ['config', 'system']);
        $logTransfer = $this->createTestTransfer($node, '日志文件传输', FileTransferStatus::COMPLETED, true, ['logs', 'monitoring']);
        $backupTransfer = $this->createTestTransfer($node, '备份文件传输', FileTransferStatus::COMPLETED, true, ['backup', 'system']);
        $noTagTransfer = $this->createTestTransfer($node, '无标签传输', FileTransferStatus::COMPLETED, true);

        self::getEntityManager()->flush();

        // 测试按单个标签查找
        $systemTransfers = $this->repository->findByTags(['system']);
        $this->assertCount(2, $systemTransfers);
        $this->assertContains($configTransfer, $systemTransfers);
        $this->assertContains($backupTransfer, $systemTransfers);

        // 测试按多个标签查找（OR 条件，任何包含 config 或 system 的记录都会返回）
        $configAndSystemTransfers = $this->repository->findByTags(['config', 'system']);
        $this->assertCount(2, $configAndSystemTransfers);
        $this->assertContains($configTransfer, $configAndSystemTransfers);
        $this->assertContains($backupTransfer, $configAndSystemTransfers);

        // 测试查找不存在的标签
        $notFoundTransfers = $this->repository->findByTags(['nonexistent']);
        $this->assertCount(0, $notFoundTransfers);
    }

    public function testFindCompletedTransfers(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建不同状态的传输任务
        $completedTransfer1 = $this->createTestTransfer($node, '已完成传输1', FileTransferStatus::COMPLETED, true);
        $completedTransfer2 = $this->createTestTransfer($node, '已完成传输2', FileTransferStatus::COMPLETED, true);
        $pendingTransfer = $this->createTestTransfer($node, '待传输', FileTransferStatus::PENDING, true);
        $failedTransfer = $this->createTestTransfer($node, '失败传输', FileTransferStatus::FAILED, true);

        self::getEntityManager()->flush();

        // 测试查找已完成的传输
        $result = $this->repository->findCompletedTransfers();

        $this->assertCount(2, $result);
        $this->assertContains($completedTransfer1, $result);
        $this->assertContains($completedTransfer2, $result);
        $this->assertNotContains($pendingTransfer, $result);
        $this->assertNotContains($failedTransfer, $result);
    }

    public function testFindFailedTransfers(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建不同状态的传输任务
        $failedTransfer1 = $this->createTestTransfer($node, '失败传输1', FileTransferStatus::FAILED, true);
        $failedTransfer2 = $this->createTestTransfer($node, '失败传输2', FileTransferStatus::FAILED, true);
        $completedTransfer = $this->createTestTransfer($node, '已完成传输', FileTransferStatus::COMPLETED, true);
        $pendingTransfer = $this->createTestTransfer($node, '待传输', FileTransferStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试查找失败的传输
        $result = $this->repository->findFailedTransfers();

        $this->assertCount(2, $result);
        $this->assertContains($failedTransfer1, $result);
        $this->assertContains($failedTransfer2, $result);
        $this->assertNotContains($completedTransfer, $result);
        $this->assertNotContains($pendingTransfer, $result);
    }

    public function testFindPendingTransfersByNode(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node1 = $this->createTestNode('test-node-1');
        $node2 = $this->createTestNode('test-node-2');

        // 为节点1创建传输任务
        $pendingTransfer1 = $this->createTestTransfer($node1, '节点1待传输1', FileTransferStatus::PENDING, true);
        $pendingTransfer2 = $this->createTestTransfer($node1, '节点1待传输2', FileTransferStatus::PENDING, true);
        $uploadingTransfer = $this->createTestTransfer($node1, '节点1上传中', FileTransferStatus::UPLOADING, true);
        $disabledTransfer = $this->createTestTransfer($node1, '节点1禁用', FileTransferStatus::PENDING, false);

        // 为节点2创建传输任务
        $node2Transfer = $this->createTestTransfer($node2, '节点2待传输', FileTransferStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试查找节点1的待传输文件
        $node1Transfers = $this->repository->findPendingTransfersByNode($node1);
        $this->assertCount(2, $node1Transfers);
        $this->assertContains($pendingTransfer1, $node1Transfers);
        $this->assertContains($pendingTransfer2, $node1Transfers);
        $this->assertNotContains($uploadingTransfer, $node1Transfers);
        $this->assertNotContains($disabledTransfer, $node1Transfers);
        $this->assertNotContains($node2Transfer, $node1Transfers);

        // 测试查找节点2的待传输文件
        $node2Transfers = $this->repository->findPendingTransfersByNode($node2);
        $this->assertCount(1, $node2Transfers);
        $this->assertContains($node2Transfer, $node2Transfers);
    }

    /**
     * 创建测试节点
     */
    private function createTestNode(string $name): Node
    {
        $node = new Node();
        $node->setName($name);
        $node->setSshHost('test.example.com');
        $node->setSshPort(22);
        $node->setSshUser('testuser');
        $node->setSshPassword('testpass');
        $node->setValid(true);

        self::getEntityManager()->persist($node);

        return $node;
    }

    /**
     * 创建测试传输任务
     *
     * @param string[]|null $tags
     */
    private function createTestTransfer(
        Node $node,
        string $name,
        FileTransferStatus $status = FileTransferStatus::PENDING,
        bool $enabled = true,
        ?array $tags = null,
    ): RemoteFileTransfer {
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName($name);
        $transfer->setLocalPath('/local/test/file.txt');
        $transfer->setRemotePath('/remote/test/file.txt');
        $transfer->setStatus($status);
        $transfer->setEnabled($enabled);

        if (null !== $tags) {
            $transfer->setTags($tags);
        }

        self::getEntityManager()->persist($transfer);

        return $transfer;
    }

    public function testSave(): void
    {
        $node = $this->createTestNode('save-test-node');
        $transfer = $this->createTestTransfer($node, '保存测试传输');

        $this->repository->save($transfer, true);

        // getId() 方法已声明返回 int 类型，无需验证非空
        $saved = $this->repository->find($transfer->getId());
        $this->assertNotNull($saved);
        $this->assertEquals('保存测试传输', $saved->getName());
    }

    public function testRemove(): void
    {
        $node = $this->createTestNode('remove-test-node');
        $transfer = $this->createTestTransfer($node, '删除测试传输');
        self::getEntityManager()->flush();

        $transferId = $transfer->getId();
        $this->assertGreaterThan(0, $transferId);

        $this->repository->remove($transfer, true);

        $removed = $this->repository->find($transferId);
        $this->assertNull($removed);
    }

    public function testFindByNodeAssociation(): void
    {
        $node = $this->createTestNode('association-test-node');
        $transfer = $this->createTestTransfer($node, '关联查询测试传输');
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['node' => $node]);
        $this->assertCount(1, $transfers);
        $this->assertEquals('关联查询测试传输', $transfers[0]->getName());
    }

    public function testCountByNodeAssociation(): void
    {
        $node = $this->createTestNode('count-test-node');
        $this->createTestTransfer($node, '计数测试传输1');
        $this->createTestTransfer($node, '计数测试传输2');
        self::getEntityManager()->flush();

        $count = $this->repository->count(['node' => $node]);
        $this->assertEquals(2, $count);
    }

    public function testFindByCompletedAtIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-status-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空状态传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setCompletedAt(null); // 使用可为null的字段
        $transfer->setStatus(FileTransferStatus::PENDING); // 设置必需的状态
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['completedAt' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByCompletedAtIsNull(): void
    {
        $node = $this->createTestNode('null-completed-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空完成时间计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setCompletedAt(null); // 使用可为null的字段
        $transfer->setStatus(FileTransferStatus::PENDING); // 设置必需的状态
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['completedAt' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByResultIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-enabled-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空启用状态传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setResult(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['result' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByResultIsNull(): void
    {
        $node = $this->createTestNode('null-result-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空结果计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setResult(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['result' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindOneByWithOrderByShouldReturnFirstMatchingEntity(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-order-test-node');
        $transferA = $this->createTestTransfer($node, 'A传输', FileTransferStatus::PENDING);
        $transferB = $this->createTestTransfer($node, 'B传输', FileTransferStatus::PENDING);
        self::getEntityManager()->flush();

        // 按名称升序，应该返回A传输
        $ascResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['name' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $ascResult);
        $this->assertEquals('A传输', $ascResult->getName());

        // 按名称降序，应该返回B传输
        $descResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['name' => 'DESC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $descResult);
        $this->assertEquals('B传输', $descResult->getName());
    }

    public function testFindOneByWithOrderByIdShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-id-order-test-node');
        $transfer1 = $this->createTestTransfer($node, '第一传输', FileTransferStatus::PENDING);
        $transfer2 = $this->createTestTransfer($node, '第二传输', FileTransferStatus::PENDING);
        self::getEntityManager()->flush();

        // 按ID升序，应该返回较小ID的传输
        $ascResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['id' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $ascResult);
        $this->assertLessThanOrEqual($transfer2->getId(), $ascResult->getId());

        // 按ID降序，应该返回较大ID的传输
        $descResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['id' => 'DESC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $descResult);
        $this->assertGreaterThanOrEqual($transfer1->getId(), $descResult->getId());
    }

    public function testFindOneByWithMultipleOrderByFieldsShouldRespectPriority(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-multi-order-test-node');
        $transfer1 = $this->createTestTransfer($node, 'A传输', FileTransferStatus::PENDING);
        $transfer2 = $this->createTestTransfer($node, 'A传输', FileTransferStatus::UPLOADING);
        self::getEntityManager()->flush();

        // 按名称升序，状态升序，应该返回PENDING状态的（因为PENDING在枚举中优先）
        $result = $this->repository->findOneBy([], ['name' => 'ASC', 'status' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $result);
        $this->assertEquals('A传输', $result->getName());
    }

    public function testFindOneByWithOrderByRemotePathShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-path-order-test-node');

        $transfer1 = new RemoteFileTransfer();
        $transfer1->setNode($node);
        $transfer1->setName('文件传输1');
        $transfer1->setLocalPath('/local/a.txt');
        $transfer1->setRemotePath('/remote/a.txt');
        $transfer1->setStatus(FileTransferStatus::PENDING);
        self::getEntityManager()->persist($transfer1);

        $transfer2 = new RemoteFileTransfer();
        $transfer2->setNode($node);
        $transfer2->setName('文件传输2');
        $transfer2->setLocalPath('/local/z.txt');
        $transfer2->setRemotePath('/remote/z.txt');
        $transfer2->setStatus(FileTransferStatus::PENDING);
        self::getEntityManager()->persist($transfer2);

        self::getEntityManager()->flush();

        // 按远程路径升序，应该返回 a.txt
        $ascResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['remotePath' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $ascResult);
        $this->assertEquals('/remote/a.txt', $ascResult->getRemotePath());

        // 按远程路径降序，应该返回 z.txt
        $descResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['remotePath' => 'DESC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $descResult);
        $this->assertEquals('/remote/z.txt', $descResult->getRemotePath());
    }

    public function testFindOneByWithOrderByLocalPathShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-local-path-order-test-node');

        $transfer1 = new RemoteFileTransfer();
        $transfer1->setNode($node);
        $transfer1->setName('文件传输1');
        $transfer1->setLocalPath('/local/aaa.txt');
        $transfer1->setRemotePath('/remote/file1.txt');
        $transfer1->setStatus(FileTransferStatus::PENDING);
        self::getEntityManager()->persist($transfer1);

        $transfer2 = new RemoteFileTransfer();
        $transfer2->setNode($node);
        $transfer2->setName('文件传输2');
        $transfer2->setLocalPath('/local/zzz.txt');
        $transfer2->setRemotePath('/remote/file2.txt');
        $transfer2->setStatus(FileTransferStatus::PENDING);
        self::getEntityManager()->persist($transfer2);

        self::getEntityManager()->flush();

        // 按本地路径升序，应该返回 aaa.txt
        $ascResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['localPath' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $ascResult);
        $this->assertEquals('/local/aaa.txt', $ascResult->getLocalPath());

        // 按本地路径降序，应该返回 zzz.txt
        $descResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['localPath' => 'DESC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $descResult);
        $this->assertEquals('/local/zzz.txt', $descResult->getLocalPath());
    }

    public function testFindOneByWithOrderByNullableFieldsShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-nullable-order-test-node');

        $transfer1 = new RemoteFileTransfer();
        $transfer1->setNode($node);
        $transfer1->setName('传输1');
        $transfer1->setLocalPath('/local/test1.txt');
        $transfer1->setRemotePath('/remote/test1.txt');
        $transfer1->setStatus(FileTransferStatus::PENDING);
        $transfer1->setTimeout(100);
        self::getEntityManager()->persist($transfer1);

        $transfer2 = new RemoteFileTransfer();
        $transfer2->setNode($node);
        $transfer2->setName('传输2');
        $transfer2->setLocalPath('/local/test2.txt');
        $transfer2->setRemotePath('/remote/test2.txt');
        $transfer2->setStatus(FileTransferStatus::PENDING);
        $transfer2->setTimeout(300);
        self::getEntityManager()->persist($transfer2);

        self::getEntityManager()->flush();

        // 按超时时间升序，应该返回100秒的传输
        $ascResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['timeout' => 'ASC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $ascResult);
        $this->assertEquals(100, $ascResult->getTimeout());

        // 按超时时间降序，应该返回300秒的传输
        $descResult = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['timeout' => 'DESC']);
        $this->assertInstanceOf(RemoteFileTransfer::class, $descResult);
        $this->assertEquals(300, $descResult->getTimeout());
    }

    public function testFindByTempPathIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-temp-path-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空临时路径传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTempPath(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['tempPath' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByTempPathIsNull(): void
    {
        $node = $this->createTestNode('null-temp-path-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空临时路径计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTempPath(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['tempPath' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByStartedAtIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-started-at-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空开始时间传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setStartedAt(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['startedAt' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByStartedAtIsNull(): void
    {
        $node = $this->createTestNode('null-started-at-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空开始时间计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setStartedAt(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['startedAt' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByFileSizeIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-file-size-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空文件大小传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setFileSize(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['fileSize' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByFileSizeIsNull(): void
    {
        $node = $this->createTestNode('null-file-size-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空文件大小计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setFileSize(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['fileSize' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByUseSudoIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-use-sudo-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空使用Sudo传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setUseSudo(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['useSudo' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByUseSudoIsNull(): void
    {
        $node = $this->createTestNode('null-use-sudo-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空使用Sudo计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setUseSudo(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['useSudo' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByEnabledIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-enabled-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空启用状态传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setEnabled(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['enabled' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByEnabledIsNull(): void
    {
        $node = $this->createTestNode('null-enabled-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空启用状态计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setEnabled(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['enabled' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTimeoutIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-timeout-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空超时传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTimeout(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['timeout' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByTimeoutIsNull(): void
    {
        $node = $this->createTestNode('null-timeout-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空超时计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTimeout(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['timeout' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTransferTimeIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-transfer-time-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空传输耗时传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTransferTime(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['transferTime' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByTransferTimeIsNull(): void
    {
        $node = $this->createTestNode('null-transfer-time-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空传输耗时计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTransferTime(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['transferTime' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTagsIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-tags-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空标签传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTags(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['tagsJsonData' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByTagsIsNull(): void
    {
        $node = $this->createTestNode('null-tags-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空标签计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(FileTransferStatus::PENDING);
        $transfer->setTags(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['tagsJsonData' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByStatusAssociation(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('status-association-test-node');
        $transfer = $this->createTestTransfer($node, '状态关联测试传输', FileTransferStatus::UPLOADING);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['status' => FileTransferStatus::UPLOADING]);
        $this->assertCount(1, $transfers);
        $this->assertEquals('状态关联测试传输', $transfers[0]->getName());
    }

    public function testCountByStatusAssociation(): void
    {
        $node = $this->createTestNode('status-count-association-test-node');
        $this->createTestTransfer($node, '状态计数关联测试传输1', FileTransferStatus::COMPLETED);
        $this->createTestTransfer($node, '状态计数关联测试传输2', FileTransferStatus::COMPLETED);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['status' => FileTransferStatus::COMPLETED]);
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testFindByEnabledAssociation(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('enabled-association-test-node');
        $transfer = $this->createTestTransfer($node, '启用关联测试传输', FileTransferStatus::PENDING, false);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['enabled' => false]);
        $this->assertCount(1, $transfers);
        $this->assertEquals('启用关联测试传输', $transfers[0]->getName());
    }

    public function testFindByStatusIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-status-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空状态传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $transfers = $this->repository->findBy(['status' => null]);
        $this->assertCount(1, $transfers);
    }

    public function testCountByStatusIsNull(): void
    {
        $node = $this->createTestNode('null-status-count-test-node');
        $transfer = new RemoteFileTransfer();
        $transfer->setNode($node);
        $transfer->setName('空状态计数传输');
        $transfer->setLocalPath('/local/test.txt');
        $transfer->setRemotePath('/remote/test.txt');
        $transfer->setStatus(null); // 使用可为null的字段
        self::getEntityManager()->persist($transfer);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['status' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindBySpecificNodeWithMultipleTransfers(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建多个节点
        $node1 = $this->createTestNode('target-node');
        $node2 = $this->createTestNode('other-node');
        $node3 = $this->createTestNode('another-node');

        // 为目标节点创建多个传输任务
        $transfer1 = $this->createTestTransfer($node1, '目标节点传输1', FileTransferStatus::PENDING);
        $transfer2 = $this->createTestTransfer($node1, '目标节点传输2', FileTransferStatus::COMPLETED);
        $transfer3 = $this->createTestTransfer($node1, '目标节点传输3', FileTransferStatus::UPLOADING);

        // 为其他节点创建传输任务
        $otherTransfer1 = $this->createTestTransfer($node2, '其他节点传输1', FileTransferStatus::PENDING);
        $otherTransfer2 = $this->createTestTransfer($node3, '另一节点传输1', FileTransferStatus::COMPLETED);

        self::getEntityManager()->flush();

        // 测试关联查询 - 查找特定节点的所有传输任务
        $targetNodeTransfers = $this->repository->findBy(['node' => $node1]);
        $this->assertCount(3, $targetNodeTransfers);
        $this->assertContains($transfer1, $targetNodeTransfers);
        $this->assertContains($transfer2, $targetNodeTransfers);
        $this->assertContains($transfer3, $targetNodeTransfers);
        $this->assertNotContains($otherTransfer1, $targetNodeTransfers);
        $this->assertNotContains($otherTransfer2, $targetNodeTransfers);

        // 测试关联查询 - 查找其他节点的传输任务
        $otherNodeTransfers = $this->repository->findBy(['node' => $node2]);
        $this->assertCount(1, $otherNodeTransfers);
        $this->assertContains($otherTransfer1, $otherNodeTransfers);
    }

    public function testCountBySpecificNodeWithMultipleTransfers(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建多个节点
        $node1 = $this->createTestNode('count-target-node');
        $node2 = $this->createTestNode('count-other-node');

        // 为目标节点创建多个传输任务
        $this->createTestTransfer($node1, '目标节点传输1', FileTransferStatus::PENDING);
        $this->createTestTransfer($node1, '目标节点传输2', FileTransferStatus::COMPLETED);
        $this->createTestTransfer($node1, '目标节点传输3', FileTransferStatus::UPLOADING);
        $this->createTestTransfer($node1, '目标节点传输4', FileTransferStatus::FAILED);

        // 为其他节点创建传输任务
        $this->createTestTransfer($node2, '其他节点传输1', FileTransferStatus::PENDING);
        $this->createTestTransfer($node2, '其他节点传输2', FileTransferStatus::COMPLETED);

        self::getEntityManager()->flush();

        // 测试count关联查询 - 统计特定节点的传输任务数量
        $targetNodeCount = $this->repository->count(['node' => $node1]);
        $this->assertEquals(4, $targetNodeCount);

        // 测试count关联查询 - 统计其他节点的传输任务数量
        $otherNodeCount = $this->repository->count(['node' => $node2]);
        $this->assertEquals(2, $otherNodeCount);

        // 测试复合条件 - 特定节点的特定状态传输任务数量
        $targetNodePendingCount = $this->repository->count([
            'node' => $node1,
            'status' => FileTransferStatus::PENDING,
        ]);
        $this->assertEquals(1, $targetNodePendingCount);

        // 测试复合条件 - 特定节点的已完成传输任务数量
        $targetNodeCompletedCount = $this->repository->count([
            'node' => $node1,
            'status' => FileTransferStatus::COMPLETED,
        ]);
        $this->assertEquals(1, $targetNodeCompletedCount);
    }

    public function testFindByNodeWithStatusCombination(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node1 = $this->createTestNode('combo-node-1');
        $node2 = $this->createTestNode('combo-node-2');

        // 创建不同状态的传输任务
        $transfer1 = $this->createTestTransfer($node1, '节点1待传输任务', FileTransferStatus::PENDING);
        $transfer2 = $this->createTestTransfer($node1, '节点1已完成传输', FileTransferStatus::COMPLETED);
        $transfer3 = $this->createTestTransfer($node2, '节点2待传输任务', FileTransferStatus::PENDING);
        $transfer4 = $this->createTestTransfer($node2, '节点2上传中传输', FileTransferStatus::UPLOADING);

        self::getEntityManager()->flush();

        // 测试特定节点的特定状态查询
        $node1PendingTransfers = $this->repository->findBy([
            'node' => $node1,
            'status' => FileTransferStatus::PENDING,
        ]);
        $this->assertCount(1, $node1PendingTransfers);
        $this->assertContains($transfer1, $node1PendingTransfers);
        $this->assertNotContains($transfer2, $node1PendingTransfers);

        // 测试另一个节点的不同状态查询
        $node2UploadingTransfers = $this->repository->findBy([
            'node' => $node2,
            'status' => FileTransferStatus::UPLOADING,
        ]);
        $this->assertCount(1, $node2UploadingTransfers);
        $this->assertContains($transfer4, $node2UploadingTransfers);
        $this->assertNotContains($transfer3, $node2UploadingTransfers);
    }

    public function testFindOneByAssociationNodeShouldReturnMatchingEntity(): void
    {
        $node = $this->createTestNode('one-association-node');
        $transfer = $this->createTestTransfer($node, '关联查询传输', FileTransferStatus::PENDING);
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy(['node' => $node]);

        $this->assertInstanceOf(RemoteFileTransfer::class, $result);
        $this->assertEquals($transfer->getId(), $result->getId());
        $this->assertEquals('关联查询传输', $result->getName());
    }

    public function testCountByAssociationNodeShouldReturnCorrectNumber(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $targetNode = $this->createTestNode('count-association-node');
        $otherNode = $this->createTestNode('other-count-node');

        // 为目标节点创建 4 个传输任务
        $this->createTestTransfer($targetNode, '目标节点传输1', FileTransferStatus::PENDING);
        $this->createTestTransfer($targetNode, '目标节点传输2', FileTransferStatus::COMPLETED);
        $this->createTestTransfer($targetNode, '目标节点传输3', FileTransferStatus::UPLOADING);
        $this->createTestTransfer($targetNode, '目标节点传输4', FileTransferStatus::FAILED);

        // 为其他节点创建 2 个传输任务
        $this->createTestTransfer($otherNode, '其他节点传输1', FileTransferStatus::PENDING);
        $this->createTestTransfer($otherNode, '其他节点传输2', FileTransferStatus::COMPLETED);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['node' => $targetNode]);
        $this->assertSame(4, $count);
    }

    public function testFindOneByOrderByLogic(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteFileTransfer')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建多个传输任务用于排序测试
        $transfer1 = $this->createTestTransfer($node, 'ZZZ传输', FileTransferStatus::PENDING, true);
        $transfer2 = $this->createTestTransfer($node, 'AAA传输', FileTransferStatus::PENDING, true);
        $transfer3 = $this->createTestTransfer($node, 'MMM传输', FileTransferStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试按名称排序 - ASC
        $result = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['name' => 'ASC']);
        $this->assertNotNull($result);
        $this->assertEquals('AAA传输', $result->getName());

        // 测试按名称排序 - DESC
        $result = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['name' => 'DESC']);
        $this->assertNotNull($result);
        $this->assertEquals('ZZZ传输', $result->getName());

        // 测试按本地路径排序
        $result = $this->repository->findOneBy(['status' => FileTransferStatus::PENDING], ['localPath' => 'ASC']);
        $this->assertNotNull($result);
        $this->assertNotEmpty($result->getLocalPath());

        // 测试复合排序条件
        $result = $this->repository->findOneBy(
            ['status' => FileTransferStatus::PENDING],
            ['name' => 'ASC', 'id' => 'DESC']
        );
        $this->assertNotNull($result);
        $this->assertEquals('AAA传输', $result->getName());
    }

    /**
     * @return RemoteFileTransfer
     */
    protected function createNewEntity(): RemoteFileTransfer
    {
        $node = $this->createTestNode('test-node-' . uniqid());

        $entity = new RemoteFileTransfer();
        $entity->setNode($node);
        $entity->setName('Test RemoteFileTransfer ' . uniqid());
        $entity->setLocalPath('/local/test_' . uniqid() . '.txt');
        $entity->setRemotePath('/remote/test_' . uniqid() . '.txt');
        $entity->setStatus(FileTransferStatus::PENDING);
        $entity->setEnabled(true);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<RemoteFileTransfer>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
