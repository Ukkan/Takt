<?php

namespace App\Controller;

use App\Entity\Shift;
use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Month calendar of vacations. Employees see their own days off;
 * managers and admins see everyone in their company (including themselves).
 */
#[IsGranted('ROLE_EMPLOYEE')]
class CalendarController extends AbstractController
{
    public function __construct(
        private readonly ShiftRepository $shiftRepository,
        private readonly EmployeeRepository $employeeRepository,
    ) {}

    #[Route('/calendar', name: 'app_calendar', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $now = new \DateTimeImmutable('today');
        $year = (int) $request->query->get('year', $now->format('Y'));
        $month = (int) $request->query->get('month', $now->format('n'));
        if ($month < 1 || $month > 12 || $year < 2000 || $year > 2100) {
            $year = (int) $now->format('Y');
            $month = (int) $now->format('n');
        }

        $from = new \DateTimeImmutable(sprintf('%04d-%02d-01', $year, $month));
        $to = $from->modify('last day of this month');

        $companyWide = $this->isGranted('ROLE_MANAGER');
        if ($companyWide) {
            $shifts = $this->shiftRepository->findVacationsInPeriodForCompany(
                $this->getUser()->getCompany(), $from, $to
            );
        } else {
            $employee = $this->employeeRepository->findByUser($this->getUser());
            $shifts = $employee
                ? $this->shiftRepository->findVacationsInPeriodForEmployee($employee, $from, $to)
                : [];
        }

        $prev = $from->modify('-1 month');
        $next = $from->modify('+1 month');

        return $this->render('calendar/index.html.twig', [
            'calendar'    => $this->buildCalendar($from, $to, $shifts),
            'companyWide' => $companyWide,
            'prev'        => ['year' => (int) $prev->format('Y'), 'month' => (int) $prev->format('n')],
            'next'        => ['year' => (int) $next->format('Y'), 'month' => (int) $next->format('n')],
        ]);
    }

    /**
     * Collapse overlapping vacation shifts into one summary entry per day.
     *
     * @param Shift[] $shifts
     *
     * @return array{
     *   month: string, year: int, today: int|null, firstDow: int, daysInMonth: int,
     *   events: array<int, array{kind: string, count: int, label: string, who: string}>,
     *   eventCount: int, peopleOut: int
     * }
     */
    private function buildCalendar(\DateTimeImmutable $from, \DateTimeImmutable $to, array $shifts): array
    {
        $byDay = [];
        $allNames = [];

        foreach ($shifts as $shift) {
            $user = $shift->getEmployee()?->getUser();
            $name = $user?->getFullName() ?: ($user?->getEmail() ?? 'Unknown');
            $allNames[$name] = true;

            $start = \DateTimeImmutable::createFromInterface($shift->getStartTime())->setTime(0, 0);
            $end = \DateTimeImmutable::createFromInterface($shift->getEndTime() ?? $shift->getStartTime())->setTime(0, 0);
            $day = max($start, $from);
            $last = min($end, $to);

            while ($day <= $last) {
                $byDay[(int) $day->format('j')][] = ['who' => $name, 'status' => $shift->getStatus()];
                $day = $day->modify('+1 day');
            }
        }

        $events = [];
        foreach ($byDay as $dayNum => $list) {
            $names = array_values(array_unique(array_column($list, 'who')));
            $statuses = array_column($list, 'status');
            $events[$dayNum] = [
                'kind'  => in_array('approved', $statuses, true) ? 'approved' : 'pending',
                'count' => count($names),
                'label' => count($names) === 1 ? $names[0] : count($names) . ' people out',
                'who'   => count($names) === 1
                    ? ucfirst($list[0]['status']) . ' vacation'
                    : implode(', ', $names),
            ];
        }

        $today = $from->format('Y-n') === (new \DateTimeImmutable('today'))->format('Y-n')
            ? (int) (new \DateTimeImmutable('today'))->format('j')
            : null;

        return [
            'month'       => $from->format('F'),
            'year'        => (int) $from->format('Y'),
            'today'       => $today,
            'firstDow'    => (int) $from->format('w'),
            'daysInMonth' => (int) $from->format('t'),
            'events'      => $events,
            'eventCount'  => count($shifts),
            'peopleOut'   => count($allNames),
        ];
    }
}
