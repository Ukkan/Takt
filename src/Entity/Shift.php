<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\ShiftRepository::class)]
#[ORM\Table(name: 'shifts')]
#[ORM\Index(name: 'idx_shifts_employee_time', columns: ['employee_id', 'start_time', 'end_time'])]
#[ORM\Index(name: 'idx_shifts_company_time', columns: ['company_id', 'start_time'])]
#[ORM\Index(name: 'idx_shifts_employee_type_status', columns: ['employee_id', 'type', 'status'])]
class Shift
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'shifts')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Employee::class, inversedBy: 'shifts')]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Employee $employee;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = 'shift';

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $status = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $note = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'created_by', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?User $createdBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeInterface $updatedAt;

    public function __construct(Company $company, Employee $employee, \DateTimeInterface $startTime)
    {
        $this->company = $company;
        $this->employee = $employee;
        $this->startTime = $startTime;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): Company { return $this->company; }

    public function getEmployee(): Employee { return $this->employee; }

    public function getStartTime(): \DateTimeInterface { return $this->startTime; }

    public function setStartTime(\DateTimeInterface $startTime): void { $this->startTime = $startTime; }

    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }

    public function setEndTime(?\DateTimeInterface $endTime): void { $this->endTime = $endTime; }

    public function getType(): string { return $this->type; }

    public function setType(string $type): void { $this->type = $type; }

    public function getStatus(): ?string { return $this->status; }

    public function setStatus(?string $status): void { $this->status = $status; }

    public function getNote(): ?string { return $this->note; }

    public function setNote(?string $note): void { $this->note = $note; }

    public function getCreatedBy(): ?User { return $this->createdBy; }

    public function setCreatedBy(?User $createdBy): void { $this->createdBy = $createdBy; }

    public function getCreatedAt(): \DateTimeInterface { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeInterface { return $this->updatedAt; }

    public function __toString(): string { return 'Shift #' . $this->id; }
}
