<?php

namespace App\Controller;

use App\Repository\CompanyRepository;
use App\Repository\EmployeeRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\CompanyScopeChecker;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin-stats', name: 'app_admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly UserRepository $userRepository,
        private readonly EmployeeRepository $employeeRepository,
        private readonly TimeEntryRepository $timeEntryRepository,
        private readonly CompanyScopeChecker $companyScopeChecker,
    ) {}

    #[Route('', name: 'stats')]
    public function stats(): Response
    {
        // null scope = ROLE_SUPER_ADMIN, platform-wide counts; otherwise scoped to the admin's own company.
        $scope = $this->companyScopeChecker->getEffectiveCompanyScope($this->getUser());

        return $this->render('admin/stats.html.twig', [
            'scopedToCompany' => $scope,
            'totalCompanies'  => $this->companyRepository->count($scope !== null ? ['id' => $scope->getId()] : []),
            'totalUsers'      => $this->userRepository->count($scope !== null ? ['company' => $scope] : []),
            'totalEmployees'  => $this->employeeRepository->count($scope !== null ? ['company' => $scope] : []),
            'clockedInNow'    => $this->timeEntryRepository->countClockedInNow($scope),
            'entriestoday'    => $this->timeEntryRepository->countStartedToday($scope),
        ]);
    }
}