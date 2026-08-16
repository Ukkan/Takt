<?php

namespace App\Repository;

use App\Entity\OvertimeRecord;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class OvertimeRecordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
    parent::__construct($registry, OvertimeRecord::class);
    }
}
