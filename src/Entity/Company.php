<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\CompanyRepository::class)]
#[ORM\Table(name: 'companies')]
class Company
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private int $id;

    #[ORM\Column(type: 'text')]
    private string $name;

    #[ORM\Column(type: 'json', nullable: true)]
    private array $settings = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'company')]
    private Collection $users;

    #[ORM\OneToMany(targetEntity: Employee::class, mappedBy: 'company')]
    private Collection $employees;

    #[ORM\OneToMany(targetEntity: Shift::class, mappedBy: 'company')]
    private Collection $shifts;

    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'company')]
    private Collection $timeEntries;

    #[ORM\OneToMany(targetEntity: OvertimeRecord::class, mappedBy: 'company')]
    private Collection $overtimeRecords;

    #[ORM\OneToMany(targetEntity: ChangeLog::class, mappedBy: 'company')]
    private Collection $changeLogs;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'company')]
    private Collection $notifications;

    #[ORM\OneToMany(targetEntity: Message::class, mappedBy: 'company')]
    private Collection $messages;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->users = new ArrayCollection();
        $this->employees = new ArrayCollection();
        $this->shifts = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
        $this->overtimeRecords = new ArrayCollection();
        $this->changeLogs = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->messages = new ArrayCollection();
    }

    public function getId(): int { return $this->id; }

    public function getName(): string { return $this->name; }

    public function setName(string $name): void { $this->name = $name; }

    public function getSettings(): array { return $this->settings; }

    public function setSettings(array $settings): void { $this->settings = $settings; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): \DateTimeImmutable { return $this->updatedAt; }

    public function getUsers(): Collection { return $this->users; }

    public function getEmployees(): Collection { return $this->employees; }

    public function getShifts(): Collection { return $this->shifts; }

    public function getTimeEntries(): Collection { return $this->timeEntries; }

    public function getOvertimeRecords(): Collection { return $this->overtimeRecords; }

    public function getChangeLogs(): Collection { return $this->changeLogs; }

    public function getNotifications(): Collection { return $this->notifications; }

    public function getMessages(): Collection { return $this->messages; }

    public function __toString(): string { return $this->name; }
}
