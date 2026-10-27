<?php

namespace App\Service;

use App\Entity\Auto;
use App\Entity\Cita;
use App\Entity\Usuario;
use App\Repository\CitaRepository;
use Doctrine\ORM\EntityManagerInterface;

class ReservaCitaService
{
    public const MAXIMO_CITAS_PENDIENTES = 2;

    public function __construct(
        private readonly CitaRepository $citaRepository,
        private readonly DisponibilidadCitaService $disponibilidad,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function reservar(
        Usuario $usuario,
        string $tipoServicio,
        ?Auto $auto,
        \DateTimeImmutable $fechaHora,
        ?string $observaciones = null,
        ?\DateTimeImmutable $ahora = null,
    ): Cita {
        if ($this->citaRepository->contarPendientesDelCliente($usuario) >= self::MAXIMO_CITAS_PENDIENTES) {
            throw new \DomainException('Ya tenes dos citas pendientes. Espera la confirmacion de una antes de reservar otra.');
        }

        $this->validarDisponibilidad($fechaHora, null, $ahora);

        $cita = (new Cita())
            ->setUsuario($usuario)
            ->setTipoServicio($tipoServicio)
            ->setAuto($auto)
            ->setFechaHora($fechaHora)
            ->setEstado(Cita::ESTADO_PENDIENTE)
            ->setObservaciones($observaciones);

        $this->entityManager->persist($cita);
        $this->entityManager->flush();

        return $cita;
    }

    public function reprogramar(
        Cita $cita,
        \DateTimeImmutable $nuevaFechaHora,
        ?\DateTimeImmutable $ahora = null,
    ): void {
        $this->validarModificable($cita, $ahora);
        $this->validarDisponibilidad($nuevaFechaHora, $cita, $ahora);

        $cita->setFechaHora($nuevaFechaHora);
        $this->entityManager->flush();
    }

    public function cancelar(Cita $cita, ?\DateTimeImmutable $ahora = null): void
    {
        $this->validarModificable($cita, $ahora);
        $cita->setEstado(Cita::ESTADO_CANCELADA);
        $this->entityManager->flush();
    }

    public function puedeModificarse(Cita $cita, ?\DateTimeImmutable $ahora = null): bool
    {
        try {
            $this->validarModificable($cita, $ahora);

            return true;
        } catch (\DomainException) {
            return false;
        }
    }

    private function validarDisponibilidad(
        \DateTimeImmutable $fechaHora,
        ?Cita $citaIgnorada,
        ?\DateTimeImmutable $ahora,
    ): void {
        if (!$this->disponibilidad->esHorarioDisponible($fechaHora, $citaIgnorada, $ahora)) {
            throw new \DomainException('El horario seleccionado ya no esta disponible o no cumple las reglas de la agenda.');
        }
    }

    private function validarModificable(Cita $cita, ?\DateTimeImmutable $ahora): void
    {
        if (!$cita->estaActiva()) {
            throw new \DomainException('Esta cita ya no se encuentra activa.');
        }

        $ahora ??= $this->disponibilidad->ahora();
        if ($cita->getFechaHora() < $ahora->modify(sprintf('+%d hours', DisponibilidadCitaService::ANTICIPACION_MINIMA_HORAS))) {
            throw new \DomainException('La cita solo puede reprogramarse o cancelarse con al menos 24 horas de anticipacion.');
        }
    }
}
