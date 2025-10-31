<?php

namespace ServerCommandBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ServerCommandBundle\Enum\FileTransferStatus;
use ServerCommandBundle\Repository\RemoteFileTransferRepository;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Validator\Constraints as Assert;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

#[AsScheduleClean(expression: '0 5 * * *', defaultKeepDay: 60, keepDayEnv: 'SERVER_FILE_TRANSFER_LOG_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: RemoteFileTransferRepository::class)]
#[ORM\Table(name: 'ims_server_remote_file_transfer', options: ['comment' => '远程文件传输'])]
class RemoteFileTransfer implements \Stringable
{
    use TimestampableAware;
    use BlameableAware;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['comment' => '唯一标识符'])]
    private int $id = 0;

    #[ORM\ManyToOne(targetEntity: Node::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Node $node;

    #[TrackColumn]
    #[ORM\Column(length: 255, options: ['comment' => '传输名称'])]
    #[Assert\NotBlank(message: '传输名称不能为空')]
    #[Assert\Length(max: 255, maxMessage: '传输名称长度不能超过{{ limit }}个字符')]
    private string $name;

    #[TrackColumn]
    #[ORM\Column(length: 500, options: ['comment' => '本地文件路径'])]
    #[Assert\NotBlank(message: '本地路径不能为空')]
    #[Assert\Length(max: 500, maxMessage: '本地路径长度不能超过{{ limit }}个字符')]
    private string $localPath;

    #[TrackColumn]
    #[ORM\Column(length: 500, options: ['comment' => '远程目标路径'])]
    #[Assert\NotBlank(message: '远程路径不能为空')]
    #[Assert\Length(max: 500, maxMessage: '远程路径长度不能超过{{ limit }}个字符')]
    private string $remotePath;

    #[TrackColumn]
    #[ORM\Column(length: 500, nullable: true, options: ['comment' => '临时上传路径'])]
    #[Assert\Length(max: 500, maxMessage: '临时路径长度不能超过{{ limit }}个字符')]
    private ?string $tempPath = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BIGINT, nullable: true, options: ['comment' => '文件大小(字节)'])]
    #[Assert\PositiveOrZero(message: '文件大小必须为非负数')]
    private ?int $fileSize = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否使用sudo移动', 'default' => false])]
    #[Assert\Type(type: 'bool', message: 'useSudo 必须是布尔值')]
    private ?bool $useSudo = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否启用', 'default' => true])]
    #[Assert\Type(type: 'bool', message: 'enabled 必须是布尔值')]
    private ?bool $enabled = true;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '传输结果或错误信息'])]
    #[Assert\Length(max: 65535, maxMessage: '传输结果长度不能超过{{ limit }}个字符')]
    private ?string $result = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '超时时间(秒)', 'default' => 300])]
    #[Assert\Positive(message: '超时时间必须为正数')]
    private ?int $timeout = 300;

    #[ORM\Column(length: 40, nullable: true, enumType: FileTransferStatus::class, options: ['comment' => '状态'])]
    #[Assert\Choice(callback: [FileTransferStatus::class, 'cases'], message: '状态值无效')]
    private ?FileTransferStatus $status = FileTransferStatus::PENDING;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '开始传输时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '开始时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $startedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '完成时间'])]
    #[Assert\Type(type: '\DateTimeImmutable', message: '完成时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $completedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '传输耗时(秒)'])]
    #[Assert\PositiveOrZero(message: '传输耗时必须为非负数')]
    private ?float $transferTime = null;

    #[TrackColumn]
    #[ORM\Column(name: 'tags', type: Types::TEXT, nullable: true, options: ['comment' => '标签列表（JSON格式存储）'])]
    #[Assert\Length(max: 65535, maxMessage: '标签数据长度不能超过{{ limit }}个字符')]
    private ?string $tagsJsonData = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getNode(): Node
    {
        return $this->node;
    }

    public function setNode(Node $node): void
    {
        $this->node = $node;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
    }

    public function setLocalPath(string $localPath): void
    {
        $this->localPath = $localPath;
    }

    public function getRemotePath(): string
    {
        return $this->remotePath;
    }

    public function setRemotePath(string $remotePath): void
    {
        $this->remotePath = $remotePath;
    }

    public function getTempPath(): ?string
    {
        return $this->tempPath;
    }

    public function setTempPath(?string $tempPath): void
    {
        $this->tempPath = $tempPath;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): void
    {
        $this->fileSize = $fileSize;
    }

    public function isUseSudo(): ?bool
    {
        return $this->useSudo;
    }

    public function setUseSudo(?bool $useSudo): void
    {
        $this->useSudo = $useSudo;
    }

    public function isEnabled(): ?bool
    {
        return $this->enabled;
    }

    public function setEnabled(?bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function getResult(): ?string
    {
        return $this->result;
    }

    public function setResult(?string $result): void
    {
        $this->result = $result;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setTimeout(?int $timeout): void
    {
        $this->timeout = $timeout;
    }

    public function getStatus(): ?FileTransferStatus
    {
        return $this->status;
    }

    public function setStatus(?FileTransferStatus $status): void
    {
        $this->status = $status;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): void
    {
        $this->startedAt = $startedAt;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): void
    {
        $this->completedAt = $completedAt;
    }

    public function getTransferTime(): ?float
    {
        return $this->transferTime;
    }

    public function setTransferTime(?float $transferTime): void
    {
        $this->transferTime = $transferTime;
    }

    /** @return string[]|null */
    public function getTags(): ?array
    {
        if (null === $this->tagsJsonData || '' === $this->tagsJsonData) {
            return null;
        }

        $decoded = json_decode($this->tagsJsonData, true);

        if (JSON_ERROR_NONE !== json_last_error() || !is_array($decoded)) {
            return null;
        }

        // 确保所有元素都是字符串
        return array_filter(
            array_map(
                fn (mixed $tag): string => trim(
                    is_string($tag)
                    ? $tag
                    : (is_scalar($tag) ? (string) $tag : '')
                ),
                $decoded
            ),
            fn ($tag) => '' !== $tag
        );
    }

    /** @param string[]|null $tags */
    public function setTags(?array $tags): void
    {
        if (null === $tags || 0 === count($tags)) {
            $this->tagsJsonData = null;
        } else {
            $encoded = json_encode(array_values($tags));
            $this->tagsJsonData = false === $encoded ? null : $encoded;
        }
    }

    public function getTagsDisplay(): string
    {
        $tags = $this->getTags();

        return null !== $tags && [] !== $tags ? implode(', ', $tags) : '无标签';
    }

    public function __toString(): string
    {
        return sprintf(
            '%s -> %s:%s',
            $this->name,
            $this->node->getName(),
            $this->remotePath
        );
    }
}
