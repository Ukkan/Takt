<?php

namespace App\Repository;

use App\Entity\Company;
use App\Entity\Employee;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EmployeeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Employee::class);
    }

    public function findByUser(User $user): ?Employee
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function findByCompany(Company $company): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.user', 'u')
            ->addSelect('u')
            ->where('e.company = :company')
            ->setParameter('company', $company)
            ->orderBy('u.fullName', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
