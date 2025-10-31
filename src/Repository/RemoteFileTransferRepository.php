<?php

namespace ServerCommandBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use ServerCommandBundle\Entity\RemoteFileTransfer;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerNodeBundle\Entity\Node;
use Tourze\PHPUnitSymfonyKernelTest\Attribute\AsRepository;

/**
 * @extends ServiceEntityRepository<RemoteFileTransfer>
 */
#[AsRepository(entityClass: RemoteFileTransfer::class)]
class RemoteFileTransferRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RemoteFileTransfer::class);
    }

    /**
     * 查找指定节点上待传输的文件
     *
     * @return RemoteFileTransfer[]
     */
    public function findPendingTransfersByNode(Node $node): array
    {
        /** @var RemoteFileTransfer[] */
        return $this->createQueryBuilder('t')
            ->where('t.node = :node')
            ->andWhere('t.status = :status')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('node', $node)
            ->setParameter('status', FileTransferStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找所有待传输的文件
     *
     * @return RemoteFileTransfer[]
     */
    public function findAllPendingTransfers(): array
    {
        /** @var RemoteFileTransfer[] */
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.enabled = :enabled')
            ->setParameter('status', FileTransferStatus::PENDING)
            ->setParameter('enabled', true)
            ->orderBy('t.createTime', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 按标签查找文件传输记录
     *
     * @param string[] $tags
     *
     * @return RemoteFileTransfer[]
     */
    public function findByTags(array $tags): array
    {
        $qb = $this->createQueryBuilder('t');
        $qb->where('t.tagsJsonData IS NOT NULL');

        $orConditions = [];
        foreach ($tags as $index => $tag) {
            $orConditions[] = $qb->expr()->like('t.tagsJsonData', ":tag{$index}");
            $qb->setParameter("tag{$index}", '%"' . $tag . '"%');
        }

        if (count($orConditions) > 0) {
            $qb->andWhere($qb->expr()->orX(...$orConditions));
        }

        /** @var RemoteFileTransfer[] */
        return $qb->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找指定状态的传输记录
     *
     * @return RemoteFileTransfer[]
     */
    public function findByStatus(FileTransferStatus $status): array
    {
        /** @var RemoteFileTransfer[] */
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找指定时间范围内的传输记录
     *
     * @return RemoteFileTransfer[]
     */
    public function findByDateRange(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        /** @var RemoteFileTransfer[] */
        return $this->createQueryBuilder('t')
            ->where('t.createTime >= :startDate')
            ->andWhere('t.createTime <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createTime', 'DESC')
            ->getQuery()
            ->getResult()
        ;
    }

    /**
     * 查找失败的传输记录
     *
     * @return RemoteFileTransfer[]
     */
    public function findFailedTransfers(): array
    {
        return $this->findByStatus(FileTransferStatus::FAILED);
    }

    /**
     * 查找已完成的传输记录
     *
     * @return RemoteFileTransfer[]
     */
    public function findCompletedTransfers(): array
    {
        return $this->findByStatus(FileTransferStatus::COMPLETED);
    }

    public function save(RemoteFileTransfer $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(RemoteFileTransfer $entity, bool $flush = true): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
