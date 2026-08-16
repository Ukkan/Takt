<?php

namespace App\Controller;

use App\Entity\Shift;
use App\Form\VacationRequestType;
use App\Repository\EmployeeRepository;
use App\Repository\ShiftRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee/vacation', name: 'app_vacation_')]
#[IsGranted('ROLE_EMPLOYEE')]
class VacationController extends AbstractController
{
    public function __construct(
        private readonly EmployeeRepository $employeeRepository,
        private readonly ShiftRepository $shiftRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('warning', 'No employee record is linked to your account.');
            return $this->render('time_entry/no_employee.html.twig');
        }

        $vacations = $this->shiftRepository->findVacationRequestsForEmployee($employee);

        return $this->render('vacation/index.html.twig', [
            'vacations' => $vacations,
        ]);
    }

    #[Route('/request', name: 'request', methods: ['GET', 'POST'])]
    public function request(Request $request): Response
    {
        $employee = $this->employeeRepository->findByUser($this->getUser());

        if ($employee === null) {
            $this->addFlash('error', 'No employee record linked to your account.');
            return $this->redirectToRoute('app_vacation_index');
        }

        $shift = new Shift($employee->getCompany(), $employee, new \DateTimeImmutable());
        $shift->setType('vacation');
        $shift->setStatus('pending');
        $shift->setCreatedBy($this->getUser());

        $form = $this->createForm(VacationRequestType::class, $shift);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $start = \DateTimeImmutable::createFromInterface($shift->getStartTime());
            $end = \DateTimeImmutable::createFromInterface($shift->getEndTime());

            if ($this->shiftRepository->hasOverlappingVacation($employee, $start, $end)) {
                $form->get('startTime')->addError(
                    new FormError('This period overlaps with a vacation request you already submitted.')
                );
            } else {
                $this->em->persist($shift);
                $this->em->flush();

                $this->addFlash('success', 'Vacation request submitted successfully.');
                return $this->redirectToRoute('app_vacation_index');
            }
        }

        return $this->render('vacation/request.html.twig', [
            'form' => $form,
        ]);
    }
}
