<?php

namespace ServerCommandBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\PositiveOrZero;
use Symfony\Component\Validator\Constraints\Type;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Traits\BlameableAware;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

#[AsScheduleClean(expression: '0 5 * * *', defaultKeepDay: 60, keepDayEnv: 'SERVER_COMMAND_LOG_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: RemoteCommandRepository::class)]
#[ORM\Table(name: 'ims_server_remote_command', options: ['comment' => '远程命令'])]
class RemoteCommand implements \Stringable
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
    #[ORM\Column(length: 100, options: ['comment' => '命令名称'])]
    #[NotBlank(message: '命令名称不能为空')]
    #[Length(max: 100, maxMessage: '命令名称长度不能超过{{ limit }}个字符')]
    private string $name;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => '命令内容'])]
    #[NotBlank(message: '命令内容不能为空')]
    #[Length(max: 65535, maxMessage: '命令内容长度不能超过{{ limit }}个字符')]
    private string $command;

    #[TrackColumn]
    #[ORM\Column(length: 200, nullable: true, options: ['comment' => '工作目录'])]
    #[Length(max: 200, maxMessage: '工作目录长度不能超过{{ limit }}个字符')]
    private ?string $workingDirectory = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否使用sudo执行', 'default' => false])]
    #[Type(type: 'bool', message: 'useSudo 必须是布尔值')]
    private ?bool $useSudo = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否启用', 'default' => true])]
    #[Type(type: 'bool', message: 'enabled 必须是布尔值')]
    private ?bool $enabled = true;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行结果'])]
    #[Length(max: 65535, maxMessage: '执行结果长度不能超过{{ limit }}个字符')]
    private ?string $result = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '超时时间(秒)', 'default' => 300])]
    #[Positive(message: '超时时间必须为正数')]
    private ?int $timeout = 300;

    #[ORM\Column(length: 40, nullable: true, enumType: CommandStatus::class, options: ['comment' => '状态'])]
    #[Choice(callback: [CommandStatus::class, 'cases'], message: '状态值无效')]
    private ?CommandStatus $status = CommandStatus::PENDING;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true, options: ['comment' => '执行时间'])]
    #[Type(type: '\DateTimeInterface', message: '执行时间必须是有效的日期时间')]
    private ?\DateTimeImmutable $executedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '执行耗时(秒)'])]
    #[PositiveOrZero(message: '执行耗时必须为非负数')]
    private ?float $executionTime = null;

    #[TrackColumn]
    #[ORM\Column(name: 'tags', type: Types::TEXT, nullable: true, options: ['comment' => '标签列表（JSON格式存储）'])]
    #[Length(max: 65535, maxMessage: '标签数据长度不能超过{{ limit }}个字符')]
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

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): void
    {
        $this->command = $command;
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function setWorkingDirectory(?string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
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

    public function getStatus(): ?CommandStatus
    {
        return $this->status;
    }

    public function setStatus(?CommandStatus $status): void
    {
        $this->status = $status;
    }

    public function getExecutedAt(): ?\DateTimeImmutable
    {
        return $this->executedAt;
    }

    public function setExecutedAt(?\DateTimeImmutable $executedAt): void
    {
        $this->executedAt = $executedAt;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?float $executionTime): void
    {
        $this->executionTime = $executionTime;
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

            return;
        }

        // 过滤空值并转换为JSON存储
        $filteredTags = array_filter(
            array_map(fn (string $tag): string => trim($tag), $tags),
            fn ($tag) => '' !== $tag
        );
        if (0 === count($filteredTags)) {
            $this->tagsJsonData = null;
        } else {
            $encoded = json_encode(array_values($filteredTags));
            $this->tagsJsonData = false !== $encoded ? $encoded : null;
        }
    }

    public function getTagsDisplay(): string
    {
        $tags = $this->getTags();

        return null !== $tags && [] !== $tags ? implode(', ', $tags) : '无标签';
    }

    /**
     * 获取标签的原始JSON数据（用于表单编辑）
     */
    public function getTagsRaw(): ?string
    {
        return $this->tagsJsonData;
    }

    /**
     * 设置标签的原始JSON数据（用于表单编辑）
     */
    public function setTagsRaw(?string $tagsRaw): void
    {
        if (null === $tagsRaw || '' === trim($tagsRaw)) {
            $this->tagsJsonData = null;

            return;
        }

        // 验证JSON格式
        $decoded = json_decode($tagsRaw, true);
        if (JSON_ERROR_NONE === json_last_error() && is_array($decoded)) {
            $this->tagsJsonData = $tagsRaw;
        } else {
            // 如果不是有效JSON，尝试按逗号分割并转换为JSON
            $tags = array_filter(
                array_map(fn (string $tag): string => trim($tag), explode(',', $tagsRaw)),
                fn ($tag) => '' !== $tag
            );
            $this->setTags($tags);
        }
    }

    public function __toString(): string
    {
        return $this->name ?? 'RemoteCommand';
    }
}
