<?php

namespace App\Service;

use App\Entity\Cita;
use App\Repository\CitaRepository;

class DisponibilidadCitaService
{
    public const ZONA_HORARIA = 'America/Argentina/Buenos_Aires';
    public const HORA_APERTURA = '08:30';
    public const HORA_CIERRE = '18:00';
    public const DURACION_TURNO_MINUTOS = 60;
    public const ANTICIPACION_MINIMA_HORAS = 24;

    private readonly \DateTimeZone $zonaHoraria;

    public function __construct(private readonly CitaRepository $citaRepository)
    {
        $this->zonaHoraria = new \DateTimeZone(self::ZONA_HORARIA);
    }

    /** @return list<\DateTimeImmutable> */
    public function obtenerHorariosDisponibles(
        \DateTimeImmutable $fecha,
        ?Cita $citaIgnorada = null,
        ?\DateTimeImmutable $ahora = null,
    ): array {
        // La entrada representa un dia de calendario, no un instante a convertir.
        $fecha = $this->crearFechaHora($fecha->format('Y-m-d'), '00:00');
        $ahora = ($ahora ?? new \DateTimeImmutable('now', $this->zonaHoraria))
            ->setTimezone($this->zonaHoraria);

        if (!$this->esDiaLaborable($fecha)) {
            return [];
        }

        $finDelDia = $fecha->modify('+1 day');
        $ocupados = array_map(
            static fn (\DateTimeImmutable $turno): string => $turno->format('Y-m-d H:i'),
            $this->citaRepository->buscarHorariosOcupados($fecha, $finDelDia, $citaIgnorada?->getId()),
        );

        $inicio = $this->crearFechaHora($fecha->format('Y-m-d'), self::HORA_APERTURA);
        $cierre = $this->crearFechaHora($fecha->format('Y-m-d'), self::HORA_CIERRE);
        $minimoPermitido = $ahora->modify(sprintf('+%d hours', self::ANTICIPACION_MINIMA_HORAS));
        $disponibles = [];

        for (
            $turno = $inicio;
            $turno < $cierre;
            $turno = $turno->modify(sprintf('+%d minutes', self::DURACION_TURNO_MINUTOS))
        ) {
            if ($turno >= $minimoPermitido && !in_array($turno->format('Y-m-d H:i'), $ocupados, true)) {
                $disponibles[] = $turno;
            }
        }

        return $disponibles;
    }

    public function esHorarioDisponible(
        \DateTimeImmutable $fechaHora,
        ?Cita $citaIgnorada = null,
        ?\DateTimeImmutable $ahora = null,
    ): bool {
        foreach ($this->obtenerHorariosDisponibles($fechaHora, $citaIgnorada, $ahora) as $disponible) {
            if ($disponible->getTimestamp() === $fechaHora->getTimestamp()) {
                return true;
            }
        }

        return false;
    }

    /** @return list<\DateTimeImmutable> */
    public function sugerirFechasDisponibles(
        \DateTimeImmutable $desde,
        ?Cita $citaIgnorada = null,
        ?\DateTimeImmutable $ahora = null,
    ): array {
        $sugerencias = [];
        $fecha = $this->crearFechaHora($desde->format('Y-m-d'), '00:00');

        for ($i = 0; $i < 21 && count($sugerencias) < 3; ++$i) {
            $fecha = $fecha->modify('+1 day');
            if ($this->obtenerHorariosDisponibles($fecha, $citaIgnorada, $ahora) !== []) {
                $sugerencias[] = $fecha;
            }
        }

        return $sugerencias;
    }

    public function crearFechaHora(string $fecha, string $hora): \DateTimeImmutable
    {
        $fechaHora = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', sprintf('%s %s', $fecha, $hora), $this->zonaHoraria);
        $errores = \DateTimeImmutable::getLastErrors();

        if ($fechaHora === false || ($errores !== false && ($errores['warning_count'] > 0 || $errores['error_count'] > 0))) {
            throw new \DomainException('La fecha u hora seleccionada no es valida.');
        }

        return $fechaHora;
    }

    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->zonaHoraria);
    }

    private function esDiaLaborable(\DateTimeImmutable $fecha): bool
    {
        return (int) $fecha->format('N') <= 5;
    }
}
