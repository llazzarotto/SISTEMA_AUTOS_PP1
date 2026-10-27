<?php

namespace App\Repository;

use App\Entity\Cita;
use App\Entity\Usuario;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cita>
 */
class CitaRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cita::class);
    }

    /** @return list<Cita> */
    public function buscarProximasDelCliente(Usuario $usuario, \DateTimeImmutable $ahora): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.usuario = :usuario')
            ->andWhere('c.fechaHora >= :ahora')
            ->andWhere('c.estado IN (:estados)')
            ->setParameter('usuario', $usuario)
            ->setParameter('ahora', $ahora)
            ->setParameter('estados', [Cita::ESTADO_PENDIENTE, Cita::ESTADO_CONFIRMADA])
            ->orderBy('c.fechaHora', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /** @return list<Cita> */
    public function buscarHistorialDelCliente(Usuario $usuario, \DateTimeImmutable $ahora): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.usuario = :usuario')
            ->andWhere('(c.fechaHora < :ahora OR c.estado IN (:estadosFinales))')
            ->setParameter('usuario', $usuario)
            ->setParameter('ahora', $ahora)
            ->setParameter('estadosFinales', [Cita::ESTADO_CANCELADA, Cita::ESTADO_COMPLETADA])
            ->orderBy('c.fechaHora', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function contarPendientesDelCliente(Usuario $usuario): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.usuario = :usuario')
            ->andWhere('c.estado = :estado')
            ->setParameter('usuario', $usuario)
            ->setParameter('estado', Cita::ESTADO_PENDIENTE)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return list<\DateTimeImmutable> */
    public function buscarHorariosOcupados(
        \DateTimeImmutable $desde,
        \DateTimeImmutable $hasta,
        ?int $citaIgnoradaId = null,
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->select('c.fechaHora')
            ->andWhere('c.fechaHoraBloqueada IS NOT NULL')
            ->andWhere('c.fechaHora >= :desde')
            ->andWhere('c.fechaHora < :hasta')
            ->setParameter('desde', $desde)
            ->setParameter('hasta', $hasta);

        if ($citaIgnoradaId !== null) {
            $qb->andWhere('c.id != :citaIgnoradaId')
                ->setParameter('citaIgnoradaId', $citaIgnoradaId);
        }

        return array_map(
            static fn (array $fila): \DateTimeImmutable => $fila['fechaHora'],
            $qb->getQuery()->getArrayResult(),
        );
    }
}
