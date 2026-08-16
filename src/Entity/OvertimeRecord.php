<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\OvertimeRecordRepository::class)]
#[ORM\Table(name: 'overtime_records')]
#[ORM\UniqueConstraint(name: 'ux_overtime_employee_period', columns: ['employee_id', 'period_start', 'period_end'])]
class OvertimeRecord
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'overtimeRecords')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Employee::class, inversedBy: 'overtimeRecords')]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Employee $employee;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $periodStart;

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $periodEnd;

    #[ORM\Column(type: 'integer')]
    private int $overtimeMinutes = 0;

    #[ORM\Column(type: 'integer')]
    private int $deficitMinutes = 0;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $computedAt;

    public function __construct(Company $company, Employee $employee, \DateTimeInterface $periodStart, \DateTimeInterface $periodEnd)
    {
        $this->company = $company;
        $this->employee = $employee;
        $this->periodStart = $periodStart;
        $this->periodEnd = $periodEnd;
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): int { return $this->id; }

    public function getCompany(): Company { return $this->company; }

    public function getEmployee(): Employee { return $this->employee; }

    public function getPeriodStart(): \DateTimeInterface { return $this->periodStart; }

    public function getPeriodEnd(): \DateTimeInterface { return $this->periodEnd; }

    public function getOvertimeMinutes(): int { return $this->overtimeMinutes; }

    public function setOvertimeMinutes(int $overtimeMinutes): void { $this->overtimeMinutes = $overtimeMinutes; }

    public function getDeficitMinutes(): int { return $this->deficitMinutes; }

    public function setDeficitMinutes(int $deficitMinutes): void { $this->deficitMinutes = $deficitMinutes; }

    public function getComputedAt(): \DateTimeInterface { return $this->computedAt; }

    public function __toString(): string { return 'OvertimeRecord #' . $this->id; }
}
