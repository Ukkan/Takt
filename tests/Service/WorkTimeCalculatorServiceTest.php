<?php

namespace App\Tests\Service;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use App\Repository\TimeEntryRepository;
use App\Service\WorkTimeCalculatorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WorkTimeCalculatorServiceTest extends TestCase
{
    private TimeEntryRepository&MockObject $timeEntryRepository;
    private ShiftRepository&MockObject $shiftRepository;
    private WorkTimeCalculatorService $service;
    private Company $company;

    protected function setUp(): void
    {
        $this->timeEntryRepository = $this->createMock(TimeEntryRepository::class);
        $this->shiftRepository = $this->createMock(ShiftRepository::class);
        $this->service = new WorkTimeCalculatorService($this->timeEntryRepository, $this->shiftRepository);
        $this->company = new Company();
    }

    private function makeEmployee(?int $contractMinutesPerWeek = null): Employee
    {
        $employee = new Employee($this->company);
        $employee->setContractMinutesPerWeek($contractMinutesPerWeek);

        return $employee;
    }

    private function approvedVacation(Employee $employee, string $start, string $end): Shift
    {
        $shift = new Shift($this->company, $employee, new \DateTimeImmutable($start));
        $shift->setEndTime(new \DateTimeImmutable($end));
        $shift->setType('vacation');
        $shift->setStatus('approved');

        return $shift;
    }

    /**
     * computeMonthSummary must pass the correct inclusive month boundaries as
     * periodStart/periodEnd, and an EXCLUSIVE upper bound (periodEnd + 1 day)
     * to the repository query — this is also what lets the repository exclude
     * a still-open entry from the sum (BE-05), since that filtering happens
     * on the repository side, not in the service.
     */
    public function testComputeMonthSummaryUsesCorrectPeriodBoundaries(): void
    {
        $employee = $this->makeEmployee();

        $this->timeEntryRepository->expects($this->once())
            ->method('sumWorkedMinutesForPeriod')
            ->with(
                $employee,
                $this->callback(fn (\DateTimeImmutable $d) => $d->format('Y-m-d H:i:s') === '2026-02-01 00:00:00'),
                $this->callback(fn (\DateTimeImmutable $d) => $d->format('Y-m-d H:i:s') === '2026-03-01 00:00:00'),
            )
            ->willReturn(0);

        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([]);

        $result = $this->service->computeMonthSummary($employee, 2026, 2);

        $this->assertSame('2026-02-01', $result['periodStart']->format('Y-m-d'));
        $this->assertSame('2026-02-28', $result['periodEnd']->format('Y-m-d'));
    }

    /**
     * BE-01: a period covering only a weekend has zero workdays, so zero
     * expected minutes, regardless of how much was worked.
     */
    public function testWeekendOnlyPeriodHasNoExpectedMinutes(): void
    {
        $employee = $this->makeEmployee();
        $this->timeEntryRepository->method('sumWorkedMinutesForPeriod')->willReturn(0);
        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([]);

        // 2026-08-08 is a Saturday, 2026-08-09 a Sunday.
        $result = $this->service->computePeriodSummary(
            $employee,
            new \DateTimeImmutable('2026-08-08'),
            new \DateTimeImmutable('2026-08-09'),
        );

        $this->assertSame(0, $result['expectedMinutes']);
        $this->assertSame(0, $result['netMinutes']);
    }

    /**
     * BE-02 (fixed sign): a vacation covering the whole period drives
     * expectedMinutes to zero, so the balance equals the worked time
     * (positive), NOT its negative.
     */
    public function testVacationCoveringWholePeriodGivesPositiveBalance(): void
    {
        $employee = $this->makeEmployee();
        $this->timeEntryRepository->method('sumWorkedMinutesForPeriod')->willReturn(5000);

        // August 2026: 1st (Sat) to 31st (Mon), vacation spans the entire period.
        $vacation = $this->approvedVacation($employee, '2026-08-01', '2026-08-31');
        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([$vacation]);

        $result = $this->service->computePeriodSummary(
            $employee,
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-31'),
        );

        $this->assertSame(0, $result['expectedMinutes']);
        $this->assertSame(5000, $result['workedMinutes']);
        $this->assertSame(5000, $result['netMinutes']);
    }

    /**
     * BE-03: a vacation that starts before the period and ends inside it
     * must be clamped to the period, counting only the overlapping workdays.
     */
    public function testVacationPartiallyOutsidePeriodIsClamped(): void
    {
        $employee = $this->makeEmployee();
        $this->timeEntryRepository->method('sumWorkedMinutesForPeriod')->willReturn(0);

        // Vacation: 2026-07-28 (Tue, before period) to 2026-08-05 (Wed, inside period).
        $vacation = $this->approvedVacation($employee, '2026-07-28', '2026-08-05');
        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([$vacation]);

        // Period: 2026-08-01 (Sat) to 2026-08-14 (Fri) — 10 workdays total.
        $result = $this->service->computePeriodSummary(
            $employee,
            new \DateTimeImmutable('2026-08-01'),
            new \DateTimeImmutable('2026-08-14'),
        );

        // Clamped vacation range is 2026-08-01 to 2026-08-05: Mon 3, Tue 4, Wed 5 = 3 workdays.
        $this->assertSame(3, $result['approvedVacationDays']);
        $this->assertSame((10 - 3) * 480, $result['expectedMinutes']);
    }

    public function testPartTimeContractUsesProRatedDailyRate(): void
    {
        $employee = $this->makeEmployee(contractMinutesPerWeek: 1200); // 20h/week -> 240 min/day
        $this->timeEntryRepository->method('sumWorkedMinutesForPeriod')->willReturn(0);
        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([]);

        // 2026-08-03 (Mon) to 2026-08-07 (Fri): 5 workdays.
        $result = $this->service->computePeriodSummary(
            $employee,
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-07'),
        );

        $this->assertSame(5 * 240, $result['expectedMinutes']);
    }

    /**
     * BE-04: no contract set falls back to the 8h/day default.
     */
    public function testDefaultDailyRateWhenNoContractSet(): void
    {
        $employee = $this->makeEmployee(contractMinutesPerWeek: null);
        $this->timeEntryRepository->method('sumWorkedMinutesForPeriod')->willReturn(0);
        $this->shiftRepository->method('findApprovedVacationsInPeriod')->willReturn([]);

        $result = $this->service->computePeriodSummary(
            $employee,
            new \DateTimeImmutable('2026-08-03'),
            new \DateTimeImmutable('2026-08-07'),
        );

        $this->assertSame(5 * 480, $result['expectedMinutes']);
    }
}
