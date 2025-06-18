<?php

namespace ServerCommandBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ServerCommandBundle\Enum\CommandStatus;
use ServerCommandBundle\Repository\RemoteCommandRepository;
use ServerNodeBundle\Entity\Node;
use Tourze\DoctrineTimestampBundle\Traits\TimestampableAware;
use Tourze\DoctrineTrackBundle\Attribute\TrackColumn;
use Tourze\DoctrineUserBundle\Attribute\CreatedByColumn;
use Tourze\DoctrineUserBundle\Attribute\UpdatedByColumn;
use Tourze\ScheduleEntityCleanBundle\Attribute\AsScheduleClean;

#[AsScheduleClean(expression: '0 5 * * *', defaultKeepDay: 60, keepDayEnv: 'SERVER_COMMAND_LOG_PERSIST_DAY_NUM')]
#[ORM\Entity(repositoryClass: RemoteCommandRepository::class)]
#[ORM\Table(name: 'ims_server_remote_command', options: ['comment' => '远程命令'])]
class RemoteCommand implements \Stringable
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
    #[ORM\Column(length: 100, options: ['comment' => '命令名称'])]
    private string $name;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, options: ['comment' => '命令内容'])]
    private string $command;

    #[TrackColumn]
    #[ORM\Column(length: 200, nullable: true, options: ['comment' => '工作目录'])]
    private ?string $workingDirectory = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否使用sudo执行', 'default' => false])]
    private ?bool $useSudo = false;

    #[TrackColumn]
    #[ORM\Column(type: Types::BOOLEAN, nullable: true, options: ['comment' => '是否启用', 'default' => true])]
    private ?bool $enabled = true;

    #[TrackColumn]
    #[ORM\Column(type: Types::TEXT, nullable: true, options: ['comment' => '执行结果'])]
    private ?string $result = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['comment' => '超时时间(秒)', 'default' => 300])]
    private ?int $timeout = 300;

    #[ORM\Column(length: 40, nullable: true, enumType: CommandStatus::class, options: ['comment' => '状态'])]
    private ?CommandStatus $status = CommandStatus::PENDING;

    #[TrackColumn]
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true, options: ['comment' => '执行时间'])]
    private ?\DateTimeInterface $executedAt = null;

    #[TrackColumn]
    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ['comment' => '执行耗时(秒)'])]
    private ?float $executionTime = null;

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

    public function getCommand(): string
    {
        return $this->command;
    }

    public function setCommand(string $command): static
    {
        $this->command = $command;

        return $this;
    }

    public function getWorkingDirectory(): ?string
    {
        return $this->workingDirectory;
    }

    public function setWorkingDirectory(?string $workingDirectory): static
    {
        $this->workingDirectory = $workingDirectory;

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

    public function getStatus(): ?CommandStatus
    {
        return $this->status;
    }

    public function setStatus(?CommandStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getExecutedAt(): ?\DateTimeInterface
    {
        return $this->executedAt;
    }

    public function setExecutedAt(?\DateTimeInterface $executedAt): static
    {
        $this->executedAt = $executedAt;

        return $this;
    }

    public function getExecutionTime(): ?float
    {
        return $this->executionTime;
    }

    public function setExecutionTime(?float $executionTime): static
    {
        $this->executionTime = $executionTime;

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
        return $this->name;
    }
}
