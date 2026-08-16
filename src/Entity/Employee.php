<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\EmployeeRepository::class)]
#[ORM\Table(name: 'employees')]
#[ORM\UniqueConstraint(name: 'ux_employees_company_external', columns: ['company_id', 'external_id'])]
class Employee
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'employees')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $externalId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $position = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $contractMinutesPerWeek = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $hiredAt = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $terminatedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'employee')]
    private Collection $shifts;

    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'employee')]
    private Collection $timeEntries;

    #[ORM\OneToMany(targetEntity: OvertimeRecord::class, mappedBy: 'employee')]
    private Collection $overtimeRecords;

    public function __construct(Company $company)
    {
        $this->company = $company;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->shifts = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
        $this->overtimeRecords = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): Company { return $this->company; }

    public function setCompany(Company $company): void { $this->company = $company; }

    public function getUser(): ?User { return $this->user; }

    public function setUser(?User $user): void { $this->user = $user; }

    public function getPosition(): ?string { return $this->position; }

    public function setPosition(?string $position): void { $this->position = $position; }

    public function getExternalId(): ?string { return $this->externalId; }

    public function setExternalId(?string $externalId): void { $this->externalId = $externalId; }

    public function getContractMinutesPerWeek(): ?int { return $this->contractMinutesPerWeek; }

    public function setContractMinutesPerWeek(?int $contractMinutesPerWeek): void { $this->contractMinutesPerWeek = $contractMinutesPerWeek; }

    public function getHiredAt(): ?\DateTimeInterface { return $this->hiredAt; }

    public function setHiredAt(?\DateTimeInterface $hiredAt): void { $this->hiredAt = $hiredAt; }

    public function getTerminatedAt(): ?\DateTimeInterface { return $this->terminatedAt; }

    public function setTerminatedAt(?\DateTimeInterface $terminatedAt): void { $this->terminatedAt = $terminatedAt; }

    public function getShifts(): Collection { return $this->shifts; }

    public function getTimeEntries(): Collection { return $this->timeEntries; }

    public function getOvertimeRecords(): Collection { return $this->overtimeRecords; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function __toString(): string
    {
        $label = $this->position ?? ('Employee #' . $this->id);
        if ($this->user !== null) {
            $label .= ' (' . $this->user->getEmail() . ')';
        }
        return $label;
    }
}
