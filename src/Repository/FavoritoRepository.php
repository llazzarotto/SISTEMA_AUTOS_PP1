<?php

namespace App\Repository;

use App\Entity\Favorito;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Favorito>
 */
class FavoritoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorito::class);
    }

    /** @return list<Favorito> */
    public function buscarPorUsuario(Usuario $usuario): array
    {
        return $this->createQueryBuilder('f')
            ->addSelect('auto', 'modelo', 'marca', 'imagenes')
            ->innerJoin('f.auto', 'auto')
            ->innerJoin('auto.modelo', 'modelo')
            ->innerJoin('modelo.marca', 'marca')
            ->leftJoin('auto.imagenes', 'imagenes')
            ->andWhere('f.usuario = :usuario')
            ->setParameter('usuario', $usuario)
            ->orderBy('f.fechaCreacion', SortDirection::Descending)
            ->distinct()
            ->getQuery()
            ->getResult();
    }
}
