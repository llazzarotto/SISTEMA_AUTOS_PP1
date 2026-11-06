<?php

namespace App\Repository;

use App\Entity\Auto;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use SortDirection;

/**
 * @extends ServiceEntityRepository<Auto>
 */
class AutoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Auto::class);
    }

    /**
     * Busca exclusivamente unidades disponibles y combina todos los criterios recibidos.
     *
     * @param array{
     *     q?: string,
     *     precioMin?: int|null,
     *     precioMax?: int|null,
     *     kilometrajeMax?: int|null,
     *     anioDesde?: int|null,
     *     anioHasta?: int|null,
     *     marca?: int|null,
     *     orden?: string
     * } $filtros
     */
    public function buscarDisponibles(array $filtros, int $pagina = 1, int $porPagina = 9): Paginator
    {
        $qb = $this->createQueryBuilder('a')
            ->addSelect('modelo', 'marca', 'imagenes')
            ->innerJoin('a.modelo', 'modelo')
            ->innerJoin('modelo.marca', 'marca')
            ->leftJoin('a.imagenes', 'imagenes')
            ->andWhere('a.estado = :estadoDisponible')
            ->setParameter('estadoDisponible', 'disponible')
            ->distinct();

        $busqueda = trim((string) ($filtros['q'] ?? ''));
        if ($busqueda !== '') {
            $coincidencias = $qb->expr()->orX(
                'LOWER(marca.nombre) LIKE :busqueda',
                'LOWER(modelo.nombre) LIKE :busqueda',
            );
            $qb->setParameter('busqueda', '%'.mb_strtolower($busqueda).'%');

            if (ctype_digit($busqueda)) {
                $coincidencias->add('a.anio = :anioBuscado');
                $qb->setParameter('anioBuscado', (int) $busqueda);
            }

            $qb->andWhere($coincidencias);
        }

        $this->aplicarMinimo($qb, 'a.precio', 'precioMin', $filtros['precioMin'] ?? null);
        $this->aplicarMaximo($qb, 'a.precio', 'precioMax', $filtros['precioMax'] ?? null);
        $this->aplicarMaximo($qb, 'a.kilometraje', 'kilometrajeMax', $filtros['kilometrajeMax'] ?? null);
        $this->aplicarMinimo($qb, 'a.anio', 'anioDesde', $filtros['anioDesde'] ?? null);
        $this->aplicarMaximo($qb, 'a.anio', 'anioHasta', $filtros['anioHasta'] ?? null);

        if (($filtros['marca'] ?? null) !== null) {
            $qb
                ->andWhere('marca.id = :marca')
                ->setParameter('marca', $filtros['marca']);
        }

        match ($filtros['orden'] ?? 'recientes') {
            'precio_asc' => $qb->orderBy('a.precio', SortDirection::Ascending),
            'precio_desc' => $qb->orderBy('a.precio', SortDirection::Descending),
            'anio_asc' => $qb->orderBy('a.anio', SortDirection::Ascending),
            'km_asc' => $qb->orderBy('a.kilometraje', SortDirection::Ascending),
            default => $qb->orderBy('a.anio', SortDirection::Descending)->addOrderBy('a.id', SortDirection::Descending),
        };

        $pagina = max(1, $pagina);
        $query = $qb
            ->setFirstResult(($pagina - 1) * $porPagina)
            ->setMaxResults($porPagina)
            ->getQuery();

        return new Paginator($query, true);
    }

    /** @param list<int> $ids
     *  @return list<Auto>
     */
    public function buscarDisponiblesPorIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $autos = $this->createQueryBuilder('a')
            ->addSelect('modelo', 'marca', 'imagenes')
            ->innerJoin('a.modelo', 'modelo')
            ->innerJoin('modelo.marca', 'marca')
            ->leftJoin('a.imagenes', 'imagenes')
            ->andWhere('a.id IN (:ids)')
            ->andWhere('a.estado = :estadoDisponible')
            ->setParameter('ids', $ids)
            ->setParameter('estadoDisponible', 'disponible')
            ->distinct()
            ->getQuery()
            ->getResult();

        $porId = [];
        foreach ($autos as $auto) {
            $porId[$auto->getId()] = $auto;
        }

        return array_values(array_filter(array_map(
            static fn (int $id): ?Auto => $porId[$id] ?? null,
            $ids
        )));
    }

    private function aplicarMinimo(
        \Doctrine\ORM\QueryBuilder $qb,
        string $campo,
        string $parametro,
        ?int $valor,
    ): void {
        if ($valor !== null) {
            $qb->andWhere(sprintf('%s >= :%s', $campo, $parametro))->setParameter($parametro, $valor);
        }
    }

    private function aplicarMaximo(
        \Doctrine\ORM\QueryBuilder $qb,
        string $campo,
        string $parametro,
        ?int $valor,
    ): void {
        if ($valor !== null) {
            $qb->andWhere(sprintf('%s <= :%s', $campo, $parametro))->setParameter($parametro, $valor);
        }
    }
}
