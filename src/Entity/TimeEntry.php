<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: \App\Repository\TimeEntryRepository::class)]
#[ORM\Table(name: 'time_entries')]
#[ORM\Index(name: 'idx_time_entries_employee_time', columns: ['employee_id', 'start_time'])]
#[ORM\Index(name: 'idx_time_entries_company_time', columns: ['company_id', 'start_time'])]
#[Assert\Callback('validateDuration')]
class TimeEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\ManyToOne(targetEntity: Company::class, inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(name: 'company_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Company $company;

    #[ORM\ManyToOne(targetEntity: Employee::class, inversedBy: 'timeEntries')]
    #[ORM\JoinColumn(name: 'employee_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Employee $employee;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startTime;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'integer')]
    private int $breakMinutes = 0;

    #[ORM\Column(type: 'string', length: 20)]
    private string $source = 'app';

    #[ORM\ManyToOne(targetEntity: Shift::class)]
    #[ORM\JoinColumn(name: 'linked_shift_id', referencedColumnName: 'id', onDelete: 'SET NULL', nullable: true)]
    private ?Shift $linkedShift = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $meta = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

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

    public function setStartTime(\DateTimeInterface $startTime): void
    {
        $this->startTime = $startTime;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getEndTime(): ?\DateTimeInterface { return $this->endTime; }

    public function setEndTime(?\DateTimeInterface $endTime): void
    {
        $this->endTime = $endTime;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getBreakMinutes(): int { return $this->breakMinutes; }

    public function setBreakMinutes(int $breakMinutes): void
    {
        $this->breakMinutes = $breakMinutes;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSource(): string { return $this->source; }

    public function setSource(string $source): void { $this->source = $source; }

    public function getMeta(): array { return $this->meta; }

    public function setMeta(array $meta): void { $this->meta = $meta; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getDurationMinutes(): ?int
    {
        if ($this->endTime === null) {
            return null;
        }
        $diff = $this->endTime->getTimestamp() - $this->startTime->getTimestamp();
        return (int) floor($diff / 60) - $this->breakMinutes;
    }

    public function isActive(): bool
    {
        return $this->endTime === null;
    }

    /**
     * Validates that endTime is after startTime and that breakMinutes doesn't
     * exceed the raw (pre-break) duration. A no-op while the entry is active
     * (endTime null).
     */
    public function validateDuration(ExecutionContextInterface $context): void
    {
        if ($this->endTime === null) {
            return;
        }

        if ($this->endTime->getTimestamp() <= $this->startTime->getTimestamp()) {
            $context->buildViolation('End time must be after start time.')
                ->atPath('endTime')
                ->addViolation();

            return;
        }

        $rawMinutes = (int) floor(($this->endTime->getTimestamp() - $this->startTime->getTimestamp()) / 60);
        if ($this->breakMinutes > $rawMinutes) {
            $context->buildViolation('Break cannot be longer than the time entry itself.')
                ->atPath('breakMinutes')
                ->addViolation();
        }
    }
}
