<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Alias;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alias>
 */
class AliasRepository extends ServiceEntityRepository implements AliasRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alias::class);
    }

    public function existsByLocalPart(string $localPart): bool
    {
        return $this->findOneBy(['localPart' => $localPart]) !== null;
    }

    /**
     * @return list<Alias>
     */
    public function findByUserOrderByCreatedAtDesc(User $user): array
    {
        return $this->findBy(
            ['user' => $user],
            ['createdAt' => 'DESC']
        );
    }
}
