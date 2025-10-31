<?php

namespace ServerCommandBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<RemoteCommand>
 */
#[AsRepository(entityClass: RemoteCommand::class)]
class RemoteCommandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteCommand::class);
    }

    /**
     * 查找指定节点上待执行的命令
     *
     * @return RemoteCommand[]
     */
    public function findPendingCommandsByNode(Node $node): array
    {
        /** @var RemoteCommand[] */
        return $this->createQueryBuilder('c')
            ->where('c.node = :node')
            ->andWhere('c.status = :status')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('node', $node)
            ->setParameter('status', CommandStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('c.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找所有待执行的命令
     *
     * @return RemoteCommand[]
     */
    public function findAllPendingCommands(): array
    {
        /** @var RemoteCommand[] */
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('status', CommandStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('c.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 按标签查找命令
     *
     * @param string[] $tags
     *
     * @return RemoteCommand[]
     */
    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('c');
        $qb->where('c.tagsJsonData IS NOT NULL');

        $orConditions = [];
        foreach ($tags as $index => $tag) {
            $orConditions[] = $qb->expr()->like('c.tagsJsonData', ":tag{$index}");
            $qb->setParameter("tag{$index}", '%"' . $tag . '"%');
        }

        if (count($orConditions) > 0) {
            $qb->andWhere($qb->expr()->orX(...$orConditions));
        }

        /** @var RemoteCommand[] */
        return $qb->orderBy('c.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找指定节点的终端命令历史
     *
     * @return RemoteCommand[]
     */
    public function findTerminalCommandsByNode(Node $node, int $limit = 20): array
    {
        /** @var RemoteCommand[] */
        return $this->createQueryBuilder('c')
            ->where('c.node = :node')
            ->andWhere('c.tagsJsonData LIKE :terminal_tag')
            ->setParameter('node', $node)
            ->setParameter('terminal_tag', '%terminal%')
            ->orderBy('c.createTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult()
        ;
    }

    public function save(RemoteCommand $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RemoteCommand $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
