<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\MessageRepository::class)]
#[ORM\Table(name: 'messages')]
#[ORM\Index(name: 'idx_messages_company', columns: ['company_id'])]
#[ORM\Index(name: 'idx_messages_recipient', columns: ['recipient_id'])]
class Message
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'sentMessages')]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedMessages')]
    #[ORM\JoinColumn(name: 'recipient_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?User $recipient = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $body = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $relatedEntityType = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $relatedEntityId = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $readAt = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): Company { return $this->company; }

    public function getSender(): ?User { return $this->sender; }

    public function setSender(?User $sender): void { $this->sender = $sender; }

    public function getRecipient(): ?User { return $this->recipient; }

    public function setRecipient(?User $recipient): void { $this->recipient = $recipient; }

    public function getSubject(): ?string { return $this->subject; }

    public function setSubject(?string $subject): void { $this->subject = $subject; }

    public function getBody(): ?string { return $this->body; }

    public function setBody(?string $body): void { $this->body = $body; }

    public function getRelatedEntityType(): ?string { return $this->relatedEntityType; }

    public function setRelatedEntityType(?string $relatedEntityType): void { $this->relatedEntityType = $relatedEntityType; }

    public function getRelatedEntityId(): ?int { return $this->relatedEntityId; }

    public function setRelatedEntityId(?int $relatedEntityId): void { $this->relatedEntityId = $relatedEntityId; }

    public function getReadAt(): ?\DateTimeInterface { return $this->readAt; }

    public function setReadAt(?\DateTimeInterface $readAt): void { $this->readAt = $readAt; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function __toString(): string { return $this->subject ?? ('Message #' . $this->id); }
}
