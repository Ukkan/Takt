<?php

namespace App\Controller;

use App\Entity\Employee;
use App\Entity\Shift;
use App\Entity\TimeEntry;
use App\Form\TimeEntryType;
use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use App\Repository\TimeEntryRepository;
use App\Service\CompanyScopeChecker;
use App\Service\WorkTimeCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager', name: 'app_manager_')]
#[IsGranted('ROLE_MANAGER')]
class ManagerController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly ShiftRepository $shiftRepository,
        private readonly EntityManagerInterface $em,
        private readonly WorkTimeCalculatorService $workTimeCalculator,
        private readonly CompanyScopeChecker $companyScopeChecker,
    ) {}

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $company = $this->getUser()->getCompany();

        if ($company === null) {
            $this->addFlash('warning', 'Your account is not assigned to a company.');
            return $this->render('manager/no_company.html.twig');
        }

        $employees = $this->employeeRepository->findByCompany($company);

        $activeEntries = [];
        $monthlyStatus = [];
        foreach ($employees as $employee) {
            $activeEntries[$employee->getId()] = $this->timeEntryRepository->findActiveEntry($employee);
            $monthlyStatus[$employee->getId()]  = $this->workTimeCalculator->computeCurrentMonthToDate($employee);
        }

        return $this->render('manager/dashboard.html.twig', [
            'employees'     => $employees,
            'activeEntries' => $activeEntries,
            'monthlyStatus' => $monthlyStatus,
        ]);
    }

    #[Route('/employee/{id}', name: 'employee_time')]
    public function employeeTime(Employee $employee, Request $request): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $employee->getCompany());

        $now = new \DateTimeImmutable();
        $selectedYear  = (int) ($request->query->get('year',  $now->format('Y')));
        $selectedMonth = (int) ($request->query->get('month', $now->format('n')));
        $selectedMonth = max(1, min(12, $selectedMonth));
        $selectedYear  = max(2000, min((int) $now->format('Y'), $selectedYear));

        $activeEntry    = $this->timeEntryRepository->findActiveEntry($employee);
        $entries        = $this->timeEntryRepository->findRecentForEmployee($employee, 50);
        $monthlySummary = $this->workTimeCalculator->computeMonthSummary($employee, $selectedYear, $selectedMonth);

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

        return $this->render('manager/employee_time.html.twig', [
            'employee'        => $employee,
            'activeEntry'     => $activeEntry,
            'entries'         => $entries,
            'monthlySummary'  => $monthlySummary,
            'selectedYear'    => $selectedYear,
            'selectedMonth'   => $selectedMonth,
            'availableMonths' => $availableMonths,
        ]);
    }

    #[Route('/employee/{id}/time/add', name: 'time_add', methods: ['GET', 'POST'])]
    public function timeAdd(Employee $employee, Request $request): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $employee->getCompany());

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

                $this->addFlash('success', 'Time entry added.');
                return $this->redirectToRoute('app_manager_employee_time', ['id' => $employee->getId()]);
            }
        }

        return $this->render('manager/time_form.html.twig', [
            'form' => $form,
            'employee' => $employee,
            'title' => 'Add Time Entry',
        ]);
    }

    #[Route('/time/{id}/edit', name: 'time_edit', methods: ['GET', 'POST'])]
    public function timeEdit(TimeEntry $entry, Request $request): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $entry->getEmployee()->getCompany());

        $form = $this->createForm(TimeEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($this->timeEntryRepository->hasOverlappingEntry(
                $entry->getEmployee(),
                $entry->getStartTime(),
                $entry->getEndTime(),
                $entry->getId(),
            )) {
                $form->get('startTime')->addError(
                    new FormError('This entry overlaps with another time entry.')
                );
            } else {
                $this->em->flush();

                $this->addFlash('success', 'Time entry updated.');
                return $this->redirectToRoute('app_manager_employee_time', ['id' => $entry->getEmployee()->getId()]);
            }
        }

        return $this->render('manager/time_form.html.twig', [
            'form' => $form,
            'employee' => $entry->getEmployee(),
            'title' => 'Edit Time Entry',
        ]);
    }

    #[Route('/time/{id}/delete', name: 'time_delete', methods: ['POST'])]
    public function timeDelete(TimeEntry $entry): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $entry->getEmployee()->getCompany());

        $employeeId = $entry->getEmployee()->getId();
        $this->em->remove($entry);
        $this->em->flush();

        $this->addFlash('success', 'Time entry deleted.');
        return $this->redirectToRoute('app_manager_employee_time', ['id' => $employeeId]);
    }

    #[Route('/vacations', name: 'vacations')]
    public function vacations(): Response
    {
        $company = $this->getUser()->getCompany();

        if ($company === null) {
            return $this->render('manager/no_company.html.twig');
        }

        $requests = $this->shiftRepository->findPendingVacationsForCompany($company);

        return $this->render('manager/vacation_requests.html.twig', [
            'requests' => $requests,
        ]);
    }

    #[Route('/vacation/{id}/approve', name: 'vacation_approve', methods: ['POST'])]
    public function vacationApprove(Shift $shift, Request $request): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $shift->getEmployee()->getCompany());

        if (!$this->isCsrfTokenValid('vacation_approve_' . $shift->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token, please try again.');
            return $this->redirectToRoute('app_manager_vacations');
        }

        if ($shift->getType() !== 'vacation' || $shift->getStatus() !== 'pending') {
            throw $this->createNotFoundException();
        }

        $shift->setStatus('approved');
        $this->em->flush();

        $this->addFlash('success', 'Vacation request approved.');
        return $this->redirectToRoute('app_manager_vacations');
    }

    #[Route('/vacation/{id}/reject', name: 'vacation_reject', methods: ['POST'])]
    public function vacationReject(Shift $shift, Request $request): Response
    {
        $this->companyScopeChecker->denyAccessUnlessCompanyMatch($this->getUser(), $shift->getEmployee()->getCompany());

        if (!$this->isCsrfTokenValid('vacation_reject_' . $shift->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token, please try again.');
            return $this->redirectToRoute('app_manager_vacations');
        }

        if ($shift->getType() !== 'vacation' || $shift->getStatus() !== 'pending') {
            throw $this->createNotFoundException();
        }

        $shift->setStatus('rejected');
        $this->em->flush();

        $this->addFlash('success', 'Vacation request rejected.');
        return $this->redirectToRoute('app_manager_vacations');
    }
}