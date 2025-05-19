<?php

namespace ServerCommandBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ServerCommandBundle\Entity\RemoteCommand;
use ServerCommandBundle\Enum\CommandStatus;
use ServerNodeBundle\Entity\Node;

/**
 * @extends ServiceEntityRepository<RemoteCommand>
 */
class RemoteCommandRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteCommand::class);
    }

    /**
     * 查找指定节点上待执行的命令
     */
    public function findPendingCommandsByNode(Node $node): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.node = :node')
            ->andWhere('c.status = :status')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('node', $node)
            ->setParameter('status', CommandStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('c.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找所有待执行的命令
     */
    public function findAllPendingCommands(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.enabled = :enabled')
            ->setParameter('status', CommandStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('c.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按标签查找命令
     */
    public function findByTags(array $tags): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.tags IS NOT NULL')
            ->andWhere('c.tags IN (:tags)')
            ->setParameter('tags', $tags)
            ->orderBy('c.createTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
