<?php

namespace ServerCommandBundle\Tests\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\AbstractRepositoryTestCase;

/**
 * @internal
 */
#[CoversClass(RemoteCommandRepository::class)]
#[RunTestsInSeparateProcesses]
final class RemoteCommandRepositoryTest extends AbstractRepositoryTestCase
{
    private RemoteCommandRepository $repository;

    protected function onSetUp(): void
    {
        // 禁用异步数据库插入包的日志输出，避免测试失败
        putenv('DISABLE_LOGGING_IN_TESTS=true');
        $_ENV['DISABLE_LOGGING_IN_TESTS'] = 'true';

        $this->repository = self::getService(RemoteCommandRepository::class);
        $this->assertInstanceOf(RemoteCommandRepository::class, $this->repository);
    }

    public function testFindAllPendingCommands(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node1 = $this->createTestNode('test-node-1');
        $node2 = $this->createTestNode('test-node-2');

        // 创建测试命令
        $pendingCommand1 = $this->createTestCommand($node1, '待执行命令1', CommandStatus::PENDING, true);
        $pendingCommand2 = $this->createTestCommand($node2, '待执行命令2', CommandStatus::PENDING, true);
        $runningCommand = $this->createTestCommand($node1, '运行中命令', CommandStatus::RUNNING, true);
        $disabledCommand = $this->createTestCommand($node1, '禁用命令', CommandStatus::PENDING, false);

        self::getEntityManager()->flush();

        // 测试查找所有待执行命令
        $result = $this->repository->findAllPendingCommands();

        $this->assertCount(2, $result);
        $this->assertContains($pendingCommand1, $result);
        $this->assertContains($pendingCommand2, $result);
        $this->assertNotContains($runningCommand, $result);
        $this->assertNotContains($disabledCommand, $result);

        // 验证排序（按创建时间升序）
        $this->assertEquals($pendingCommand1->getName(), $result[0]->getName());
        $this->assertEquals($pendingCommand2->getName(), $result[1]->getName());
    }

    public function testFindByTags(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建带标签的命令
        $command1 = $this->createTestCommand($node, '系统命令', CommandStatus::PENDING, true, ['system', 'monitoring']);
        $command2 = $this->createTestCommand($node, '部署命令', CommandStatus::COMPLETED, true, ['deploy', 'production']);
        $command3 = $this->createTestCommand($node, '监控命令', CommandStatus::PENDING, true, ['system', 'health']);
        $command4 = $this->createTestCommand($node, '无标签命令', CommandStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试按单个标签查找
        $systemCommands = $this->repository->findByTags(['system']);
        $this->assertCount(2, $systemCommands);
        $this->assertContains($command1, $systemCommands);
        $this->assertContains($command3, $systemCommands);

        // 测试按单个标签查找
        $deployCommands = $this->repository->findByTags(['deploy']);
        $this->assertCount(1, $deployCommands);
        $this->assertContains($command2, $deployCommands);

        // 测试查找不存在的标签
        $notFoundCommands = $this->repository->findByTags(['nonexistent']);
        $this->assertCount(0, $notFoundCommands);
    }

    public function testFindPendingCommandsByNode(): void
    {
        // 创建测试节点
        $node1 = $this->createTestNode('test-node-1');
        $node2 = $this->createTestNode('test-node-2');

        // 为节点1创建命令
        $pendingCommand1 = $this->createTestCommand($node1, '节点1待执行命令1', CommandStatus::PENDING, true);
        $pendingCommand2 = $this->createTestCommand($node1, '节点1待执行命令2', CommandStatus::PENDING, true);
        $runningCommand = $this->createTestCommand($node1, '节点1运行中命令', CommandStatus::RUNNING, true);
        $disabledCommand = $this->createTestCommand($node1, '节点1禁用命令', CommandStatus::PENDING, false);

        // 为节点2创建命令
        $node2Command = $this->createTestCommand($node2, '节点2待执行命令', CommandStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试查找节点1的待执行命令
        $node1Commands = $this->repository->findPendingCommandsByNode($node1);
        $this->assertCount(2, $node1Commands);
        $this->assertContains($pendingCommand1, $node1Commands);
        $this->assertContains($pendingCommand2, $node1Commands);
        $this->assertNotContains($runningCommand, $node1Commands);
        $this->assertNotContains($disabledCommand, $node1Commands);
        $this->assertNotContains($node2Command, $node1Commands);

        // 测试查找节点2的待执行命令
        $node2Commands = $this->repository->findPendingCommandsByNode($node2);
        $this->assertCount(1, $node2Commands);
        $this->assertContains($node2Command, $node2Commands);
    }

    public function testFindTerminalCommandsByNode(): void
    {
        // 创建测试节点
        $node1 = $this->createTestNode('test-node-1');
        $node2 = $this->createTestNode('test-node-2');

        // 为节点1创建终端命令（带terminal标签）
        $terminalCommand1 = $this->createTestCommand($node1, '终端命令1', CommandStatus::COMPLETED, true, ['terminal', 'bash']);
        $terminalCommand2 = $this->createTestCommand($node1, '终端命令2', CommandStatus::COMPLETED, true, ['terminal', 'zsh']);
        $normalCommand = $this->createTestCommand($node1, '普通命令', CommandStatus::COMPLETED, true, ['system']);

        // 为节点2创建终端命令
        $node2TerminalCommand = $this->createTestCommand($node2, '节点2终端命令', CommandStatus::COMPLETED, true, ['terminal']);

        self::getEntityManager()->flush();

        // 测试查找节点1的终端命令（默认限制20个）
        $node1TerminalCommands = $this->repository->findTerminalCommandsByNode($node1);
        $this->assertCount(2, $node1TerminalCommands);
        $this->assertContains($terminalCommand1, $node1TerminalCommands);
        $this->assertContains($terminalCommand2, $node1TerminalCommands);
        $this->assertNotContains($normalCommand, $node1TerminalCommands);
        $this->assertNotContains($node2TerminalCommand, $node1TerminalCommands);

        // 测试限制数量
        $limitedCommands = $this->repository->findTerminalCommandsByNode($node1, 1);
        $this->assertCount(1, $limitedCommands);

        // 测试查找节点2的终端命令
        $node2TerminalCommands = $this->repository->findTerminalCommandsByNode($node2);
        $this->assertCount(1, $node2TerminalCommands);
        $this->assertContains($node2TerminalCommand, $node2TerminalCommands);
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
     * 创建测试命令
     *
     * @param string[]|null $tags
     */
    private function createTestCommand(
        Node $node,
        string $name,
        CommandStatus $status = CommandStatus::PENDING,
        bool $enabled = true,
        ?array $tags = null,
    ): RemoteCommand {
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName($name);
        $command->setCommand('echo "test"');
        $command->setStatus($status);
        $command->setEnabled($enabled);

        if (null !== $tags) {
            $command->setTags($tags);
        }

        self::getEntityManager()->persist($command);

        return $command;
    }

    public function testSave(): void
    {
        $node = $this->createTestNode('save-test-node');
        $command = $this->createTestCommand($node, '保存测试命令');

        $this->repository->save($command, true);

        // getId() 方法已声明返回 int 类型，无需验证非空
        $saved = $this->repository->find($command->getId());
        $this->assertNotNull($saved);
        $this->assertEquals('保存测试命令', $saved->getName());
    }

    public function testRemove(): void
    {
        $node = $this->createTestNode('remove-test-node');
        $command = $this->createTestCommand($node, '删除测试命令');
        self::getEntityManager()->flush();

        $commandId = $command->getId();
        // getId() 方法已声明返回 int 类型，无需验证非空

        $this->repository->remove($command, true);

        $removed = $this->repository->find($commandId);
        $this->assertNull($removed);
    }

    public function testFindByNodeAssociation(): void
    {
        $node = $this->createTestNode('association-test-node');
        $command = $this->createTestCommand($node, '关联查询测试命令');
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['node' => $node]);
        $this->assertCount(1, $commands);
        $this->assertEquals('关联查询测试命令', $commands[0]->getName());
    }

    public function testCountByNodeAssociation(): void
    {
        $node = $this->createTestNode('count-test-node');
        $this->createTestCommand($node, '计数测试命令1');
        $this->createTestCommand($node, '计数测试命令2');
        self::getEntityManager()->flush();

        $count = $this->repository->count(['node' => $node]);
        $this->assertEquals(2, $count);
    }

    public function testFindByWorkingDirectoryIsNull(): void
    {
        // 清理现有数据，避免干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空状态命令');
        $command->setCommand('echo "test"');
        $command->setWorkingDirectory(null); // 使用可为null的字段
        $command->setStatus(CommandStatus::PENDING); // 设置必需的状态
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['workingDirectory' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByWorkingDirectoryIsNull(): void
    {
        $node = $this->createTestNode('null-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空工作目录计数命令');
        $command->setCommand('echo "test"');
        $command->setWorkingDirectory(null); // 使用可为null的字段
        $command->setStatus(CommandStatus::PENDING); // 设置必需的状态
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['workingDirectory' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByExecutedAtIsNull(): void
    {
        // 清理现有数据，避免干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-enabled-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空启用状态命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setExecutedAt(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['executedAt' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByExecutedAtIsNull(): void
    {
        $node = $this->createTestNode('null-executed-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空执行时间计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setExecutedAt(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['executedAt' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindOneByWithOrderByShouldReturnFirstMatchingEntity(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-order-test-node');
        $commandA = $this->createTestCommand($node, 'A命令', CommandStatus::PENDING);
        $commandB = $this->createTestCommand($node, 'B命令', CommandStatus::PENDING);
        self::getEntityManager()->flush();

        // 按名称升序，应该返回A命令
        $ascResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['name' => 'ASC']);
        $this->assertInstanceOf(RemoteCommand::class, $ascResult);
        $this->assertEquals('A命令', $ascResult->getName());

        // 按名称降序，应该返回B命令
        $descResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['name' => 'DESC']);
        $this->assertInstanceOf(RemoteCommand::class, $descResult);
        $this->assertEquals('B命令', $descResult->getName());
    }

    public function testFindOneByWithOrderByIdShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-id-order-test-node');
        $command1 = $this->createTestCommand($node, '第一命令', CommandStatus::PENDING);
        $command2 = $this->createTestCommand($node, '第二命令', CommandStatus::PENDING);
        self::getEntityManager()->flush();

        // 按ID升序，应该返回较小ID的命令
        $ascResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['id' => 'ASC']);
        $this->assertInstanceOf(RemoteCommand::class, $ascResult);
        $this->assertLessThanOrEqual($command2->getId(), $ascResult->getId());

        // 按ID降序，应该返回较大ID的命令
        $descResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['id' => 'DESC']);
        $this->assertInstanceOf(RemoteCommand::class, $descResult);
        $this->assertGreaterThanOrEqual($command1->getId(), $descResult->getId());
    }

    public function testFindOneByWithMultipleOrderByFieldsShouldRespectPriority(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-multi-order-test-node');
        $command1 = $this->createTestCommand($node, 'A命令', CommandStatus::PENDING);
        $command2 = $this->createTestCommand($node, 'A命令', CommandStatus::RUNNING);
        self::getEntityManager()->flush();

        // 按名称升序，状态升序，应该返回PENDING状态的（因为PENDING在枚举中优先）
        $result = $this->repository->findOneBy([], ['name' => 'ASC', 'status' => 'ASC']);
        $this->assertInstanceOf(RemoteCommand::class, $result);
        $this->assertEquals('A命令', $result->getName());
    }

    public function testFindOneByWithOrderByCommandFieldShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-command-order-test-node');

        $command1 = new RemoteCommand();
        $command1->setNode($node);
        $command1->setName('命令1');
        $command1->setCommand('echo "aaa"');
        $command1->setStatus(CommandStatus::PENDING);
        self::getEntityManager()->persist($command1);

        $command2 = new RemoteCommand();
        $command2->setNode($node);
        $command2->setName('命令2');
        $command2->setCommand('echo "zzz"');
        $command2->setStatus(CommandStatus::PENDING);
        self::getEntityManager()->persist($command2);

        self::getEntityManager()->flush();

        // 按命令内容升序，应该返回 "echo "aaa""
        $ascResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['command' => 'ASC']);
        $this->assertInstanceOf(RemoteCommand::class, $ascResult);
        $this->assertEquals('echo "aaa"', $ascResult->getCommand());

        // 按命令内容降序，应该返回 "echo "zzz""
        $descResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['command' => 'DESC']);
        $this->assertInstanceOf(RemoteCommand::class, $descResult);
        $this->assertEquals('echo "zzz"', $descResult->getCommand());
    }

    public function testFindOneByWithOrderByNullableFieldsShouldRespectSortOrder(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('find-one-by-nullable-order-test-node');

        $command1 = new RemoteCommand();
        $command1->setNode($node);
        $command1->setName('命令1');
        $command1->setCommand('echo "test1"');
        $command1->setStatus(CommandStatus::PENDING);
        $command1->setTimeout(100);
        self::getEntityManager()->persist($command1);

        $command2 = new RemoteCommand();
        $command2->setNode($node);
        $command2->setName('命令2');
        $command2->setCommand('echo "test2"');
        $command2->setStatus(CommandStatus::PENDING);
        $command2->setTimeout(300);
        self::getEntityManager()->persist($command2);

        self::getEntityManager()->flush();

        // 按超时时间升序，应该返回100秒的命令
        $ascResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['timeout' => 'ASC']);
        $this->assertInstanceOf(RemoteCommand::class, $ascResult);
        $this->assertEquals(100, $ascResult->getTimeout());

        // 按超时时间降序，应该返回300秒的命令
        $descResult = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['timeout' => 'DESC']);
        $this->assertInstanceOf(RemoteCommand::class, $descResult);
        $this->assertEquals(300, $descResult->getTimeout());
    }

    public function testFindByResultIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-result-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空结果命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setResult(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['result' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByResultIsNull(): void
    {
        $node = $this->createTestNode('null-result-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空结果计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setResult(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['result' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTimeoutIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-timeout-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空超时命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setTimeout(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['timeout' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByTimeoutIsNull(): void
    {
        $node = $this->createTestNode('null-timeout-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空超时计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setTimeout(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['timeout' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByUseSudoIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-use-sudo-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空使用Sudo命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setUseSudo(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['useSudo' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByUseSudoIsNull(): void
    {
        $node = $this->createTestNode('null-use-sudo-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空使用Sudo计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setUseSudo(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['useSudo' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByEnabledIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-enabled-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空启用状态命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setEnabled(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['enabled' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByEnabledIsNull(): void
    {
        $node = $this->createTestNode('null-enabled-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空启用状态计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setEnabled(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['enabled' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByExecutionTimeIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-execution-time-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空执行耗时命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setExecutionTime(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['executionTime' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByExecutionTimeIsNull(): void
    {
        $node = $this->createTestNode('null-execution-time-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空执行耗时计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setExecutionTime(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['executionTime' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByTagsIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-tags-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空标签命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setTags(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['tagsJsonData' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByTagsIsNull(): void
    {
        $node = $this->createTestNode('null-tags-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空标签计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(CommandStatus::PENDING);
        $command->setTags(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['tagsJsonData' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindByStatusAssociation(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('status-association-test-node');
        $command = $this->createTestCommand($node, '状态关联测试命令', CommandStatus::RUNNING);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['status' => CommandStatus::RUNNING]);
        $this->assertCount(1, $commands);
        $this->assertEquals('状态关联测试命令', $commands[0]->getName());
    }

    public function testCountByStatusAssociation(): void
    {
        $node = $this->createTestNode('status-count-association-test-node');
        $this->createTestCommand($node, '状态计数关联测试命令1', CommandStatus::COMPLETED);
        $this->createTestCommand($node, '状态计数关联测试命令2', CommandStatus::COMPLETED);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['status' => CommandStatus::COMPLETED]);
        $this->assertGreaterThanOrEqual(2, $count);
    }

    public function testFindByEnabledAssociation(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('enabled-association-test-node');
        $command = $this->createTestCommand($node, '启用关联测试命令', CommandStatus::PENDING, false);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['enabled' => false]);
        $this->assertCount(1, $commands);
        $this->assertEquals('启用关联测试命令', $commands[0]->getName());
    }

    public function testFindByStatusIsNull(): void
    {
        // 清理现有数据，避免 DataFixtures 数据干扰
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node = $this->createTestNode('null-status-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空状态命令');
        $command->setCommand('echo "test"');
        $command->setStatus(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $commands = $this->repository->findBy(['status' => null]);
        $this->assertCount(1, $commands);
    }

    public function testCountByStatusIsNull(): void
    {
        $node = $this->createTestNode('null-status-count-test-node');
        $command = new RemoteCommand();
        $command->setNode($node);
        $command->setName('空状态计数命令');
        $command->setCommand('echo "test"');
        $command->setStatus(null); // 使用可为null的字段
        self::getEntityManager()->persist($command);
        self::getEntityManager()->flush();

        $count = $this->repository->count(['status' => null]);
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function testFindBySpecificNodeWithMultipleCommands(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建多个节点
        $node1 = $this->createTestNode('target-node');
        $node2 = $this->createTestNode('other-node');
        $node3 = $this->createTestNode('another-node');

        // 为目标节点创建多个命令
        $command1 = $this->createTestCommand($node1, '目标节点命令1', CommandStatus::PENDING);
        $command2 = $this->createTestCommand($node1, '目标节点命令2', CommandStatus::COMPLETED);
        $command3 = $this->createTestCommand($node1, '目标节点命令3', CommandStatus::RUNNING);

        // 为其他节点创建命令
        $otherCommand1 = $this->createTestCommand($node2, '其他节点命令1', CommandStatus::PENDING);
        $otherCommand2 = $this->createTestCommand($node3, '另一节点命令1', CommandStatus::COMPLETED);

        self::getEntityManager()->flush();

        // 测试关联查询 - 查找特定节点的所有命令
        $targetNodeCommands = $this->repository->findBy(['node' => $node1]);
        $this->assertCount(3, $targetNodeCommands);
        $this->assertContains($command1, $targetNodeCommands);
        $this->assertContains($command2, $targetNodeCommands);
        $this->assertContains($command3, $targetNodeCommands);
        $this->assertNotContains($otherCommand1, $targetNodeCommands);
        $this->assertNotContains($otherCommand2, $targetNodeCommands);

        // 测试关联查询 - 查找其他节点的命令
        $otherNodeCommands = $this->repository->findBy(['node' => $node2]);
        $this->assertCount(1, $otherNodeCommands);
        $this->assertContains($otherCommand1, $otherNodeCommands);
    }

    public function testCountBySpecificNodeWithMultipleCommands(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建多个节点
        $node1 = $this->createTestNode('count-target-node');
        $node2 = $this->createTestNode('count-other-node');

        // 为目标节点创建多个命令
        $this->createTestCommand($node1, '目标节点命令1', CommandStatus::PENDING);
        $this->createTestCommand($node1, '目标节点命令2', CommandStatus::COMPLETED);
        $this->createTestCommand($node1, '目标节点命令3', CommandStatus::RUNNING);
        $this->createTestCommand($node1, '目标节点命令4', CommandStatus::FAILED);

        // 为其他节点创建命令
        $this->createTestCommand($node2, '其他节点命令1', CommandStatus::PENDING);
        $this->createTestCommand($node2, '其他节点命令2', CommandStatus::COMPLETED);

        self::getEntityManager()->flush();

        // 测试count关联查询 - 统计特定节点的命令数量
        $targetNodeCount = $this->repository->count(['node' => $node1]);
        $this->assertEquals(4, $targetNodeCount);

        // 测试count关联查询 - 统计其他节点的命令数量
        $otherNodeCount = $this->repository->count(['node' => $node2]);
        $this->assertEquals(2, $otherNodeCount);

        // 测试复合条件 - 特定节点的特定状态命令数量
        $targetNodePendingCount = $this->repository->count([
            'node' => $node1,
            'status' => CommandStatus::PENDING,
        ]);
        $this->assertEquals(1, $targetNodePendingCount);

        // 测试复合条件 - 特定节点的已完成命令数量
        $targetNodeCompletedCount = $this->repository->count([
            'node' => $node1,
            'status' => CommandStatus::COMPLETED,
        ]);
        $this->assertEquals(1, $targetNodeCompletedCount);
    }

    public function testFindByNodeWithStatusCombination(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $node1 = $this->createTestNode('combo-node-1');
        $node2 = $this->createTestNode('combo-node-2');

        // 创建不同状态的命令
        $command1 = $this->createTestCommand($node1, '节点1待执行命令', CommandStatus::PENDING);
        $command2 = $this->createTestCommand($node1, '节点1已完成命令', CommandStatus::COMPLETED);
        $command3 = $this->createTestCommand($node2, '节点2待执行命令', CommandStatus::PENDING);
        $command4 = $this->createTestCommand($node2, '节点2运行中命令', CommandStatus::RUNNING);

        self::getEntityManager()->flush();

        // 测试特定节点的特定状态查询
        $node1PendingCommands = $this->repository->findBy([
            'node' => $node1,
            'status' => CommandStatus::PENDING,
        ]);
        $this->assertCount(1, $node1PendingCommands);
        $this->assertContains($command1, $node1PendingCommands);
        $this->assertNotContains($command2, $node1PendingCommands);

        // 测试另一个节点的不同状态查询
        $node2RunningCommands = $this->repository->findBy([
            'node' => $node2,
            'status' => CommandStatus::RUNNING,
        ]);
        $this->assertCount(1, $node2RunningCommands);
        $this->assertContains($command4, $node2RunningCommands);
        $this->assertNotContains($command3, $node2RunningCommands);
    }

    public function testFindOneByAssociationNodeShouldReturnMatchingEntity(): void
    {
        $node = $this->createTestNode('one-association-node');
        $command = $this->createTestCommand($node, '关联查询命令', CommandStatus::PENDING);
        self::getEntityManager()->flush();

        $result = $this->repository->findOneBy(['node' => $node]);

        $this->assertInstanceOf(RemoteCommand::class, $result);
        $this->assertEquals($command->getId(), $result->getId());
        $this->assertEquals('关联查询命令', $result->getName());
    }

    public function testCountByAssociationNodeShouldReturnCorrectNumber(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        $targetNode = $this->createTestNode('count-association-node');
        $otherNode = $this->createTestNode('other-count-node');

        // 为目标节点创建 4 个命令
        $this->createTestCommand($targetNode, '目标节点命令1', CommandStatus::PENDING);
        $this->createTestCommand($targetNode, '目标节点命令2', CommandStatus::COMPLETED);
        $this->createTestCommand($targetNode, '目标节点命令3', CommandStatus::RUNNING);
        $this->createTestCommand($targetNode, '目标节点命令4', CommandStatus::FAILED);

        // 为其他节点创建 2 个命令
        $this->createTestCommand($otherNode, '其他节点命令1', CommandStatus::PENDING);
        $this->createTestCommand($otherNode, '其他节点命令2', CommandStatus::COMPLETED);

        self::getEntityManager()->flush();

        $count = $this->repository->count(['node' => $targetNode]);
        $this->assertSame(4, $count);
    }

    public function testFindOneByOrderByLogic(): void
    {
        // 清理现有数据
        self::getEntityManager()->createQuery('DELETE FROM ServerCommandBundle\Entity\RemoteCommand')->execute();
        self::getEntityManager()->createQuery('DELETE FROM ServerNodeBundle\Entity\Node')->execute();

        // 创建测试节点
        $node = $this->createTestNode('test-node');

        // 创建多个命令用于排序测试
        $command1 = $this->createTestCommand($node, 'ZZZ命令', CommandStatus::PENDING, true);
        $command2 = $this->createTestCommand($node, 'AAA命令', CommandStatus::PENDING, true);
        $command3 = $this->createTestCommand($node, 'MMM命令', CommandStatus::PENDING, true);

        self::getEntityManager()->flush();

        // 测试按命令名称排序 - ASC
        $result = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['name' => 'ASC']);
        $this->assertNotNull($result);
        $this->assertEquals('AAA命令', $result->getName());

        // 测试按命令名称排序 - DESC
        $result = $this->repository->findOneBy(['status' => CommandStatus::PENDING], ['name' => 'DESC']);
        $this->assertNotNull($result);
        $this->assertEquals('ZZZ命令', $result->getName());

        // 测试复合排序条件
        $result = $this->repository->findOneBy(
            ['status' => CommandStatus::PENDING],
            ['name' => 'ASC', 'id' => 'DESC']
        );
        $this->assertNotNull($result);
        $this->assertEquals('AAA命令', $result->getName());
    }

    /**
     * @return RemoteCommand
     */
    protected function createNewEntity(): RemoteCommand
    {
        $node = $this->createTestNode('test-node-' . uniqid());

        $entity = new RemoteCommand();
        $entity->setNode($node);
        $entity->setName('Test RemoteCommand ' . uniqid());
        $entity->setCommand('echo "test command"');
        $entity->setStatus(CommandStatus::PENDING);
        $entity->setEnabled(true);

        return $entity;
    }

    /**
     * @return ServiceEntityRepository<RemoteCommand>
     */
    protected function getRepository(): ServiceEntityRepository
    {
        return $this->repository;
    }
}
