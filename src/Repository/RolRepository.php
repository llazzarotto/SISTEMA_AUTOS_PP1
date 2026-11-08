<?php

namespace App\Repository;

use App\Entity\Rol;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rol>
 */
class RolRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rol::class);
    }

    // Agregá aquí consultas personalizadas cuando las necesites.
}
