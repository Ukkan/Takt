<?php

namespace App\DataFixtures;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\Shift;
use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {}

    public function load(ObjectManager $manager): void
    {
        // Platform super admin: not tied to any company.
        $superAdmin = new User();
        $superAdmin->setEmail('admin@example.com');
        $superAdmin->setFullName('Super Admin');
        $superAdmin->setRole('super_admin');
        $superAdmin->setIsActive(true);
        $superAdmin->setCompany(null);
        $superAdmin->setPasswordHash($this->hasher->hashPassword($superAdmin, 'password'));
        $manager->persist($superAdmin);

        $this->loadCompany(
            $manager,
            'Demo Company',
            companyAdminEmail: 'company-admin@example.com',
            managerEmail: 'manager@example.com',
            employeeEmail: 'employee@example.com',
        );

        $this->loadCompany(
            $manager,
            'Second Company',
            companyAdminEmail: null,
            managerEmail: 'manager-b@example.com',
            employeeEmail: 'employee-b@example.com',
        );

        $manager->flush();
    }

    /**
     * Creates one company with a manager, an employee, and a demo month of
     * time entries and vacation requests. Optionally also seeds a
     * company-scoped admin (ROLE_ADMIN) for that company.
     */
    private function loadCompany(
        ObjectManager $manager,
        string $companyName,
        ?string $companyAdminEmail,
        string $managerEmail,
        string $employeeEmail,
    ): Company {
        $company = new Company();
        $company->setName($companyName);
        $manager->persist($company);

        if ($companyAdminEmail !== null) {
            $this->makeUser($manager, $companyAdminEmail, 'Company Admin', 'admin', $company);
        }

        $this->makeUser($manager, $managerEmail, 'Manager User', 'manager', $company);

        $employeeUser = $this->makeUser($manager, $employeeEmail, 'Employee User', 'employee', $company);

        $employee = new Employee($company);
        $employee->setUser($employeeUser);
        $employee->setPosition('Developer');
        $employee->setContractMinutesPerWeek(2400); // 40 h/week
        $manager->persist($employee);

        $this->loadDemoMonth($manager, $company, $employee, $employeeUser);

        return $company;
    }

    private function makeUser(
        ObjectManager $manager,
        string $email,
        string $fullName,
        string $role,
        Company $company,
    ): User {
        $user = new User();
        $user->setEmail($email);
        $user->setFullName($fullName);
        $user->setRole($role);
        $user->setIsActive(true);
        $user->setCompany($company);
        $user->setPasswordHash($this->hasher->hashPassword($user, 'password'));
        $manager->persist($user);

        return $user;
    }

    /**
     * Fills the previous calendar month with time entries on every workday,
     * a 3-day approved vacation, and two rejected vacation requests.
     */
    private function loadDemoMonth(
        ObjectManager $manager,
        Company $company,
        Employee $employee,
        User $employeeUser,
    ): void {
        $monthStart = new \DateTimeImmutable('first day of last month midnight');
        $monthEnd   = $monthStart->modify('last day of this month');

        // 3-day approved vacation: Mon-Wed of the second week (always weekdays, always inside the month)
        $vacationStart = $monthStart->modify('first monday of this month')->modify('+7 days');
        $vacationEnd   = $vacationStart->modify('+2 days');
        $manager->persist($this->makeVacation(
            $company, $employee, $employeeUser,
            $vacationStart, $vacationEnd,
            'approved', 'Short trip',
        ));

        // Rejected request #1: Thu-Fri of the third week of the same month
        $rejectedStart = $vacationStart->modify('+10 days');
        $manager->persist($this->makeVacation(
            $company, $employee, $employeeUser,
            $rejectedStart, $rejectedStart->modify('+1 day'),
            'rejected', 'Long weekend',
        ));

        // Rejected request #2: a full week in the current month
        $rejectedStart2 = $monthEnd->modify('first monday of next month')->modify('+7 days');
        $manager->persist($this->makeVacation(
            $company, $employee, $employeeUser,
            $rejectedStart2, $rejectedStart2->modify('+4 days'),
            'rejected', 'Family visit',
        ));

        // Pending request awaiting manager review: first Monday-Tuesday of next month from today
        $pendingStart = new \DateTimeImmutable('first monday of next month');
        $manager->persist($this->makeVacation(
            $company, $employee, $employeeUser,
            $pendingStart, $pendingStart->modify('+1 day'),
            'pending', 'Long weekend request',
        ));

        // Completed time entries on every workday of the month, skipping the approved vacation
        $current = $monthStart;
        while ($current <= $monthEnd) {
            $isWorkday  = (int) $current->format('N') <= 5;
            $onVacation = $current >= $vacationStart && $current <= $vacationEnd;

            if ($isWorkday && !$onVacation) {
                $manager->persist($this->makeTimeEntry($company, $employee, $current));
            }

            $current = $current->modify('+1 day');
        }
    }

    private function makeVacation(
        Company $company,
        Employee $employee,
        User $createdBy,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
        string $status,
        string $note,
    ): Shift {
        $shift = new Shift($company, $employee, $start);
        $shift->setEndTime($end);
        $shift->setType('vacation');
        $shift->setStatus($status);
        $shift->setNote($note);
        $shift->setCreatedBy($createdBy);

        return $shift;
    }

    private function makeTimeEntry(Company $company, Employee $employee, \DateTimeImmutable $day): TimeEntry
    {
        // Deterministic variation per day of month: start 08:00-09:28, work 450-539 min, break 30-60 min
        $dayNumber    = (int) $day->format('j');
        $startMinutes = (8 + $dayNumber % 2) * 60 + ($dayNumber * 7) % 30;
        $workMinutes  = 450 + ($dayNumber * 13) % 90;
        $breakMinutes = 30 + ($dayNumber % 3) * 15;

        $start = $day->modify(sprintf('+%d minutes', $startMinutes));
        $end   = $start->modify(sprintf('+%d minutes', $workMinutes + $breakMinutes));

        // TimeEntry columns use the mutable "datetime" type, which rejects DateTimeImmutable
        $entry = new TimeEntry($company, $employee, \DateTime::createFromImmutable($start));
        $entry->setEndTime(\DateTime::createFromImmutable($end));
        $entry->setBreakMinutes($breakMinutes);
        $entry->setSource('app');

        return $entry;
    }
}
