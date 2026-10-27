<?php

namespace App\Repository;

use App\Entity\ImagenAuto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ImagenAuto>
 */
class ImagenAutoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ImagenAuto::class);
    }

    // Agregá aquí consultas personalizadas cuando las necesites.
}
