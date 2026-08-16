<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ChangeLogRepository::class)]
#[ORM\Table(name: 'changelog')]
#[ORM\Index(name: 'idx_changelog_company', columns: ['company_id'])]
#[ORM\Index(name: 'idx_changelog_entity', columns: ['entity_type', 'entity_id'])]
class ChangeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'changeLogs')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Company $company = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $entityType;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $entityId = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'changed_by', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $changeType;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $diff = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(string $entityType, string $changeType, ?array $diff = null)
    {
        $this->entityType = $entityType;
        $this->changeType = $changeType;
        $this->diff = $diff;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): ?Company { return $this->company; }

    public function setCompany(?Company $company): void { $this->company = $company; }

    public function getEntityType(): string { return $this->entityType; }

    public function getEntityId(): ?int { return $this->entityId; }

    public function setEntityId(?int $entityId): void { $this->entityId = $entityId; }

    public function getChangedBy(): ?User { return $this->changedBy; }

    public function setChangedBy(?User $changedBy): void { $this->changedBy = $changedBy; }

    public function getChangeType(): string { return $this->changeType; }

    public function getDiff(): ?array { return $this->diff; }

    public function setDiff(?array $diff): void { $this->diff = $diff; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function __toString(): string { return $this->changeType . ':' . $this->entityType; }
}
