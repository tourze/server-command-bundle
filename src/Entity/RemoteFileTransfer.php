<?php

namespace ServerCommandBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

#[AsScheduleClean(expression: '0 5 * * *', defaultKeepDay: 60, keepDayEnv: 'SERVER_FILE_TRANSFER_LOG_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: RemoteFileTransferRepository::class)]
#[ORM\Table(name: 'ims_server_remote_file_transfer', options: ['comment' => '远程文件传输'])]
class RemoteFileTransfer implements \Stringable
{
    use TimestampableAware;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => 'ID'])]
    private ?int $id = 0;

    #[TrackColumn]
    #[ORM\ManyToOne(targetEntity: Node::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Node $node;

    #[TrackColumn]
    #[ORM\Column(length: 255, options: ['comment' => '传输名称'])]
    private string $name;

    #[TrackColumn]
    #[ORM\Column(length: 500, options: ['comment' => '本地文件路径'])]
    private string $localPath;

    #[TrackColumn]
    #[ORM\Column(length: 500, options: ['comment' => '远程目标路径'])]
    private string $remotePath;

    #[TrackColumn]
    #[ORM\Column(length: 500, nullable: true, options: ['comment' => '临时上传路径'])]
    private ?string $tempPath = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['comment' => '文件大小(字节)'])]
    private ?int $fileSize = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否使用sudo移动', 'default' => false])]
    private ?bool $useSudo = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否启用', 'default' => true])]
    private ?bool $enabled = true;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '传输结果或错误信息'])]
    private ?string $result = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '超时时间(秒)', 'default' => 300])]
    private ?int $timeout = 300;

    #[ORM\Column(length: 40, nullable: true, enumType: FileTransferStatus::class, options: ['comment' => '状态'])]
    private ?FileTransferStatus $status = FileTransferStatus::PENDING;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '开始传输时间'])]
    private ?\DateTimeInterface $startedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    private ?\DateTimeInterface $completedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '传输耗时(秒)'])]
    private ?float $transferTime = null;

    #[TrackColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '标签列表'])]
    private ?array $tags = null;

    #[CreatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '创建人'])]
    private ?string $createdBy = null;

    #[UpdatedByColumn]
    #[ORM\Column(nullable: true, options: ['comment' => '更新人'])]
    private ?string $updatedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNode(): Node
    {
        return $this->node;
    }

    public function setNode(Node $node): static
    {
        $this->node = $node;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
    }

    public function setLocalPath(string $localPath): static
    {
        $this->localPath = $localPath;
        return $this;
    }

    public function getRemotePath(): string
    {
        return $this->remotePath;
    }

    public function setRemotePath(string $remotePath): static
    {
        $this->remotePath = $remotePath;
        return $this;
    }

    public function getTempPath(): ?string
    {
        return $this->tempPath;
    }

    public function setTempPath(?string $tempPath): static
    {
        $this->tempPath = $tempPath;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): static
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function isUseSudo(): ?bool
    {
        return $this->useSudo;
    }

    public function setUseSudo(?bool $useSudo): static
    {
        $this->useSudo = $useSudo;
        return $this;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): static
    {
        $this->result = $result;
        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setTimeout(?int $timeout): static
    {
        $this->timeout = $timeout;
        return $this;
    }

    public function getStatus(): ?FileTransferStatus
    {
        return $this->status;
    }

    public function setStatus(?FileTransferStatus $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartedAt(): ?\DateTimeInterface
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeInterface $startedAt): static
    {
        $this->startedAt = $startedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): static
    {
        $this->completedAt = $completedAt;
        return $this;
    }

    public function getTransferTime(): ?float
    {
        return $this->transferTime;
    }

    public function setTransferTime(?float $transferTime): static
    {
        $this->transferTime = $transferTime;
        return $this;
    }

    public function getTags(): ?array
    {
        return $this->tags;
    }

    public function setTags(?array $tags): static
    {
        $this->tags = $tags;
        return $this;
    }

    public function getCreatedBy(): ?string
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?string $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getUpdatedBy(): ?string
    {
        return $this->updatedBy;
    }

    public function setUpdatedBy(?string $updatedBy): static
    {
        $this->updatedBy = $updatedBy;
        return $this;
    }public function __toString(): string
    {
        return sprintf(
            '%s -> %s:%s',
            $this->name,
            $this->node?->getName() ?? 'Unknown',
            $this->remotePath
        );
    }
}
