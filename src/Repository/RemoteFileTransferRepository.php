<?php

namespace ServerCommandBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerNodeBundle\Entity\Node;

/**
 * @extends ServiceEntityRepository<RemoteFileTransfer>
 */
class RemoteFileTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteFileTransfer::class);
    }

    /**
     * 查找指定节点上待传输的文件
     */
    public function findPendingTransfersByNode(Node $node): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.node = :node')
            ->andWhere('t.status = :status')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('node', $node)
            ->setParameter('status', FileTransferStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找所有待传输的文件
     */
    public function findAllPendingTransfers(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('status', FileTransferStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 按标签查找文件传输记录
     */
    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('t');

        foreach ($tags as $index => $tag) {
            $qb->andWhere("JSON_CONTAINS(t.tags, :tag{$index}) = 1")
               ->setParameter("tag{$index}", json_encode($tag));
        }

        return $qb->orderBy('t.createTime', 'DESC')
                 ->getQuery()
                 ->getResult();
    }

    /**
     * 查找指定状态的传输记录
     */
    public function findByStatus(FileTransferStatus $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找指定时间范围内的传输记录
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.createTime >= :startDate')
            ->andWhere('t.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * 查找失败的传输记录
     */
    public function findFailedTransfers(): array
    {
        return $this->findByStatus(FileTransferStatus::FAILED);
    }

    /**
     * 查找已完成的传输记录
     */
    public function findCompletedTransfers(): array
    {
        return $this->findByStatus(FileTransferStatus::COMPLETED);
    }
}
