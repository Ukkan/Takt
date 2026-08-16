<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(name: 'idx_notifications_user_unread', columns: ['user_id', 'is_read'])]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', onDelete: 'CASCADE', nullable: true)]
    private ?Company $company = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $type = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $payload = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(User $user)
    {
        $this->user = $user;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): ?Company { return $this->company; }

    public function setCompany(?Company $company): void { $this->company = $company; }

    public function getUser(): User { return $this->user; }

    public function getType(): ?string { return $this->type; }

    public function setType(?string $type): void { $this->type = $type; }

    public function getPayload(): ?array { return $this->payload; }

    public function setPayload(?array $payload): void { $this->payload = $payload; }

    public function isRead(): bool { return $this->isRead; }

    public function setIsRead(bool $isRead): void { $this->isRead = $isRead; }

    public function getSentAt(): ?\DateTimeInterface { return $this->sentAt; }

    public function setSentAt(?\DateTimeInterface $sentAt): void { $this->sentAt = $sentAt; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function __toString(): string { return $this->type ?? ('Notification #' . $this->id); }
}
