<?php

namespace App\Controller;

use App\Entity\TimeEntry;
use App\Form\TimeEntryType;
use App\Repository\EmployeeRepository;
use App\Repository\TimeEntryRepository;
use App\Service\StaleTimeEntryCloser;
use App\Service\WorkTimeCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/time', name: 'app_time_entry_')]
#[IsGranted('ROLE_EMPLOYEE')]
class TimeEntryController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly EntityManagerInterface $em,
        private readonly WorkTimeCalculatorService $workTimeCalculator,
        private readonly StaleTimeEntryCloser $staleEntryCloser,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('warning', 'No employee record is linked to your account. Please contact your manager.');
            return $this->render('time_entry/no_employee.html.twig');
        }

        $activeEntry = $this->timeEntryRepository->findActiveEntry($employee);
        $recentEntries = $this->timeEntryRepository->findRecentForEmployee($employee);
        $monthlySummary = $this->workTimeCalculator->computeCurrentMonthToDate($employee);

        return $this->render('time_entry/index.html.twig', [
            'employee' => $employee,
            'activeEntry' => $activeEntry,
            'recentEntries' => $recentEntries,
            'monthlySummary' => $monthlySummary,
        ]);
    }

    #[Route('/clock-in', name: 'clock_in', methods: ['POST'])]
    public function clockIn(): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('error', 'No employee record linked to your account.');
            return $this->redirectToRoute('app_time_entry_index');
        }

        $active = $this->timeEntryRepository->findActiveEntry($employee);
        if ($active !== null) {
            if (!$this->staleEntryCloser->isStale($active)) {
                $this->addFlash('warning', 'You are already clocked in.');
                return $this->redirectToRoute('app_time_entry_index');
            }

            $this->staleEntryCloser->closeAtMidnight($active);
            $this->addFlash('info', sprintf(
                'Your entry from %s was left open and has been closed automatically at midnight.',
                $active->getStartTime()->format('Y-m-d'),
            ));
        }

        $entry = new TimeEntry($employee->getCompany(), $employee, new \DateTime());
        $entry->setSource('app');
        $this->em->persist($entry);
        $this->em->flush();

        $this->addFlash('success', 'Clocked in at ' . (new \DateTime())->format('H:i') . '.');
        return $this->redirectToRoute('app_time_entry_index');
    }

    #[Route('/clock-out', name: 'clock_out', methods: ['POST'])]
    public function clockOut(): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('error', 'No employee record linked to your account.');
            return $this->redirectToRoute('app_time_entry_index');
        }

        $active = $this->timeEntryRepository->findActiveEntry($employee);
        if ($active === null) {
            $this->addFlash('warning', 'You are not clocked in.');
            return $this->redirectToRoute('app_time_entry_index');
        }

        if ($this->staleEntryCloser->isStale($active)) {
            $this->staleEntryCloser->closeAtMidnight($active);
            $this->em->flush();

            $this->addFlash('info', sprintf(
                'Your entry from %s was left open and has been closed automatically at midnight.',
                $active->getStartTime()->format('Y-m-d'),
            ));
            return $this->redirectToRoute('app_time_entry_index');
        }

        $active->setEndTime(new \DateTime());
        $this->em->flush();

        $this->addFlash('success', 'Clocked out at ' . (new \DateTime())->format('H:i') . '.');
        return $this->redirectToRoute('app_time_entry_index');
    }

    #[Route('/log', name: 'log', methods: ['GET', 'POST'])]
    public function log(Request $request): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('error', 'No employee record linked to your account.');
            return $this->redirectToRoute('app_time_entry_index');
        }

        $entry = new TimeEntry($employee->getCompany(), $employee, new \DateTime());
        $entry->setSource('manual');

        $form = $this->createForm(TimeEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->timeEntryRepository->hasOverlappingEntry($employee, $entry->getStartTime(), $entry->getEndTime())) {
                $form->get('startTime')->addError(
                    new FormError('This entry overlaps with another time entry.')
                );
            } else {
                $this->em->persist($entry);
                $this->em->flush();

                $this->addFlash('success', 'Time entry logged successfully.');
                return $this->redirectToRoute('app_time_entry_index');
            }
        }

        return $this->render('time_entry/log.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(TimeEntry $entry): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null || $entry->getEmployee()->getId() !== $employee->getId()) {
            throw $this->createAccessDeniedException();
        }

        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', 'Time entry deleted.');
        return $this->redirectToRoute('app_time_entry_index');
    }

    #[Route('/summary', name: 'summary')]
    public function summary(Request $request): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('warning', 'No employee record is linked to your account.');
            return $this->render('time_entry/no_employee.html.twig');
        }

        $now = new \DateTimeImmutable();
        $selectedYear  = (int) ($request->query->get('year',  $now->format('Y')));
        $selectedMonth = (int) ($request->query->get('month', $now->format('n')));

        // Clamp to valid range
        $selectedMonth = max(1, min(12, $selectedMonth));
        $selectedYear  = max(2000, min((int) $now->format('Y'), $selectedYear));

        $monthlySummary = $this->workTimeCalculator->computeMonthSummary($employee, $selectedYear, $selectedMonth);

        // Build list of last 12 months for the selector
        $availableMonths = [];
        $cursor = $now->modify('first day of this month');
        for ($i = 0; $i < 12; $i++) {
            $availableMonths[] = [
                'year'  => (int) $cursor->format('Y'),
                'month' => (int) $cursor->format('n'),
                'label' => $cursor->format('F Y'),
            ];
            $cursor = $cursor->modify('-1 month');
        }

        return $this->render('time_entry/summary.html.twig', [
            'employee'       => $employee,
            'monthlySummary' => $monthlySummary,
            'selectedYear'   => $selectedYear,
            'selectedMonth'  => $selectedMonth,
            'availableMonths' => $availableMonths,
        ]);
    }
}