<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\TimeEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class TimeEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TimeEntry::class);
    }

    public function findActiveEntry(Employee $employee): ?TimeEntry
    {
        return $this->createQueryBuilder('t')
            ->where('t.employee = :employee')
            ->andWhere('t.endTime IS NULL')
            ->setParameter('employee', $employee)
            ->orderBy('t.startTime', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findRecentForEmployee(Employee $employee, int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('t.startTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countClockedInNow(?Company $company = null): int
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.endTime IS NULL');

        if ($company !== null) {
            $qb->andWhere('t.company = :company')->setParameter('company', $company);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countStartedToday(?Company $company = null): int
    {
        $start = new \DateTime('today');
        $end   = new \DateTime('tomorrow');

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.startTime >= :start')
            ->andWhere('t.startTime < :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($company !== null) {
            $qb->andWhere('t.company = :company')->setParameter('company', $company);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Active entries (endTime IS NULL) that started before the given boundary,
     * i.e. entries left open past midnight.
     *
     * @return TimeEntry[]
     */
    public function findStaleActiveEntries(\DateTimeInterface $before): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.endTime IS NULL')
            ->andWhere('t.startTime < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check whether the employee already has another time entry overlapping
     * the given start/end (end null means an open-ended/active entry).
     * $excludeId excludes the entry being edited from the check.
     */
    public function hasOverlappingEntry(
        Employee $employee,
        \DateTimeInterface $start,
        ?\DateTimeInterface $end,
        ?int $excludeId = null,
    ): bool {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.employee = :employee')
            ->andWhere('t.startTime < :effectiveEnd')
            ->andWhere('(t.endTime IS NULL OR t.endTime > :start)')
            ->setParameter('employee', $employee)
            ->setParameter('start', $start)
            ->setParameter('effectiveEnd', $end ?? new \DateTime('9999-12-31'));

        if ($excludeId !== null) {
            $qb->andWhere('t.id != :excludeId')->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Sum worked minutes for all completed time entries in a period.
     * $periodEnd is exclusive (midnight of the day after the last day).
     *
     * @return int total worked minutes
     */
    public function sumWorkedMinutesForPeriod(
        Employee $employee,
        \DateTimeImmutable $periodStart,
        \DateTimeImmutable $periodEnd,
    ): int {
        /** @var TimeEntry[] $entries */
        $entries = $this->createQueryBuilder('t')
            ->where('t.employee = :employee')
            ->andWhere('t.endTime IS NOT NULL')
            ->andWhere('t.startTime >= :start')
            ->andWhere('t.startTime < :end')
            ->setParameter('employee', $employee)
            ->setParameter('start', $periodStart)
            ->setParameter('end', $periodEnd)
            ->getQuery()
            ->getResult();

        return array_sum(array_map(
            fn(TimeEntry $e) => $e->getDurationMinutes() ?? 0,
            $entries
        ));
    }
}
