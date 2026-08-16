<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Repository\ShiftRepository;
use App\Repository\TimeEntryRepository;

class WorkTimeCalculatorService
{
    public function __construct(
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly ShiftRepository $shiftRepository,
    ) {}

    /**
     * Compute a work-time summary for the current month from the 1st to today.
     *
     * @return array{workedMinutes: int, expectedMinutes: int, netMinutes: int, approvedVacationDays: int, periodStart: \DateTimeImmutable, periodEnd: \DateTimeImmutable}
     */
    public function computeCurrentMonthToDate(Employee $employee): array
    {
        $from = new \DateTimeImmutable('first day of this month midnight');
        $to   = new \DateTimeImmutable('today');

        return $this->computePeriodSummary($employee, $from, $to);
    }

    /**
     * Compute a work-time summary for a full calendar month.
     *
     * @return array{workedMinutes: int, expectedMinutes: int, netMinutes: int, approvedVacationDays: int, periodStart: \DateTimeImmutable, periodEnd: \DateTimeImmutable}
     */
    public function computeMonthSummary(Employee $employee, int $year, int $month): array
    {
        $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
        $to   = $from->modify('last day of this month');

        return $this->computePeriodSummary($employee, $from, $to);
    }

    /**
     * Compute a work-time summary for an arbitrary period (both dates inclusive).
     *
     * @return array{workedMinutes: int, expectedMinutes: int, netMinutes: int, approvedVacationDays: int, periodStart: \DateTimeImmutable, periodEnd: \DateTimeImmutable}
     */
    public function computePeriodSummary(
        Employee $employee,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        // $to + 1 day is the exclusive upper bound for the DB query
        $exclusiveTo = $to->modify('+1 day');

        $workedMinutes = $this->timeEntryRepository->sumWorkedMinutesForPeriod(
            $employee,
            $from,
            $exclusiveTo,
        );

        $approvedVacationDays = $this->countApprovedVacationWorkdays($employee, $from, $to);

        $totalWorkdays = $this->countWorkdays($from, $to);
        $effectiveWorkdays = max(0, $totalWorkdays - $approvedVacationDays);

        $expectedMinutes = $effectiveWorkdays * $this->getDailyRateMinutes($employee);

        return [
            'workedMinutes'        => $workedMinutes,
            'expectedMinutes'      => $expectedMinutes,
            'netMinutes'           => $workedMinutes - $expectedMinutes,
            'approvedVacationDays' => $approvedVacationDays,
            'periodStart'          => $from,
            'periodEnd'            => $to,
        ];
    }

    /**
     * Count Mon–Fri workdays in a date range (inclusive).
     */
    private function countWorkdays(\DateTimeImmutable $from, \DateTimeImmutable $to): int
    {
        $count = 0;
        $current = $from->setTime(0, 0);
        $end = $to->setTime(0, 0);

        while ($current <= $end) {
            $dow = (int) $current->format('N'); // 1=Mon … 7=Sun
            if ($dow <= 5) {
                $count++;
            }
            $current = $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Count Mon–Fri vacation days from approved vacation shifts that overlap the period.
     */
    private function countApprovedVacationWorkdays(
        Employee $employee,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): int {
        $shifts = $this->shiftRepository->findApprovedVacationsInPeriod($employee, $from, $to);

        $vacationDays = 0;
        foreach ($shifts as $shift) {
            /** @var Shift $shift */
            $shiftStart = \DateTimeImmutable::createFromInterface($shift->getStartTime())->setTime(0, 0);
            $shiftEnd   = \DateTimeImmutable::createFromInterface($shift->getEndTime())->setTime(0, 0);

            // Clamp to the requested period
            $clampedStart = $shiftStart < $from->setTime(0, 0) ? $from->setTime(0, 0) : $shiftStart;
            $clampedEnd   = $shiftEnd   > $to->setTime(0, 0)   ? $to->setTime(0, 0)   : $shiftEnd;

            $current = $clampedStart;
            while ($current <= $clampedEnd) {
                $dow = (int) $current->format('N');
                if ($dow <= 5) {
                    $vacationDays++;
                }
                $current = $current->modify('+1 day');
            }
        }

        return $vacationDays;
    }

    /**
     * Daily rate in minutes from the employee's contract.
     * Falls back to 8h = 480 min when contractMinutesPerWeek is not set.
     */
    private function getDailyRateMinutes(Employee $employee): int
    {
        $weekly = $employee->getContractMinutesPerWeek();

        return $weekly !== null ? (int) round($weekly / 5) : 480;
    }
}
