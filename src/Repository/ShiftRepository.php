<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\Shift;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShiftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shift::class);
    }

    /**
     * Find approved vacation shifts for an employee that overlap a period.
     * Overlap means: shift.startTime < periodEnd AND shift.endTime >= periodStart.
     *
     * @return Shift[]
     */
    public function findApprovedVacationsInPeriod(
        Employee $employee,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.employee = :employee')
            ->andWhere('s.type = :type')
            ->andWhere('s.status = :status')
            ->andWhere('s.startTime < :periodEnd')
            ->andWhere('s.endTime >= :periodStart')
            ->setParameter('employee', $employee)
            ->setParameter('type', 'vacation')
            ->setParameter('status', 'approved')
            ->setParameter('periodStart', $periodStart)
            ->setParameter('periodEnd', $periodEnd->modify('+1 day'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all vacation shifts for an employee (any status), newest first.
     *
     * @return Shift[]
     */
    public function findVacationRequestsForEmployee(Employee $employee): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.employee = :employee')
            ->andWhere('s.type = :type')
            ->setParameter('employee', $employee)
            ->setParameter('type', 'vacation')
            ->orderBy('s.startTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all pending vacation shifts for a company, ordered by start date ascending.
     *
     * @return Shift[]
     */
    public function findPendingVacationsForCompany(Company $company): array
    {
        return $this->createQueryBuilder('s')
            ->join('s.employee', 'e')
            ->where('s.company = :company')
            ->andWhere('s.type = :type')
            ->andWhere('s.status = :status')
            ->setParameter('company', $company)
            ->setParameter('type', 'vacation')
            ->setParameter('status', 'pending')
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check whether the employee already has a pending or approved vacation
     * request overlapping the given date range (both dates inclusive).
     */
    public function hasOverlappingVacation(
        Employee $employee,
        \DateTimeImmutable $start,
        \DateTimeImmutable $end,
    ): bool {
        $count = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.employee = :employee')
            ->andWhere('s.type = :type')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.startTime <= :end')
            ->andWhere('s.endTime >= :start')
            ->setParameter('employee', $employee)
            ->setParameter('type', 'vacation')
            ->setParameter('statuses', ['approved', 'pending'])
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find approved and pending vacation shifts for one employee that overlap a period.
     *
     * @return Shift[]
     */
    public function findVacationsInPeriodForEmployee(
        Employee $employee,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): array {
        return $this->createQueryBuilder('s')
            ->where('s.employee = :employee')
            ->andWhere('s.type = :type')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.startTime < :periodEnd')
            ->andWhere('s.endTime >= :periodStart')
            ->setParameter('employee', $employee)
            ->setParameter('type', 'vacation')
            ->setParameter('statuses', ['approved', 'pending'])
            ->setParameter('periodStart', $periodStart)
            ->setParameter('periodEnd', $periodEnd->modify('+1 day'))
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find approved and pending vacation shifts across a whole company that
     * overlap a period, with employees and their users eager-loaded.
     *
     * @return Shift[]
     */
    public function findVacationsInPeriodForCompany(
        Company $company,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): array {
        return $this->createQueryBuilder('s')
            ->join('s.employee', 'e')->addSelect('e')
            ->leftJoin('e.user', 'u')->addSelect('u')
            ->where('s.company = :company')
            ->andWhere('s.type = :type')
            ->andWhere('s.status IN (:statuses)')
            ->andWhere('s.startTime < :periodEnd')
            ->andWhere('s.endTime >= :periodStart')
            ->setParameter('company', $company)
            ->setParameter('type', 'vacation')
            ->setParameter('statuses', ['approved', 'pending'])
            ->setParameter('periodStart', $periodStart)
            ->setParameter('periodEnd', $periodEnd->modify('+1 day'))
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
