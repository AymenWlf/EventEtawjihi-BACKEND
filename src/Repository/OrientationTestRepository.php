<?php

namespace App\Repository;

use App\Entity\OrientationTest;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrientationTest>
 */
class OrientationTestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrientationTest::class);
    }

    public function findActiveTestByUser(User $user): ?OrientationTest
    {
        return $this->createQueryBuilder('ot')
            ->where('ot.user = :user')
            ->andWhere('ot.isCompleted = false')
            ->setParameter('user', $user)
            ->orderBy('ot.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findLatestTestByUser(User $user): ?OrientationTest
    {
        return $this->createQueryBuilder('ot')
            ->where('ot.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ot.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findByUuid(string $uuid): ?OrientationTest
    {
        return $this->findOneBy(['uuid' => $uuid]);
    }
}

