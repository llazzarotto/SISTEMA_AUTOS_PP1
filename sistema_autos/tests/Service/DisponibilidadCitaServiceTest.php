<?php

namespace App\Tests\Service;

use App\Repository\CitaRepository;
use App\Service\DisponibilidadCitaService;
use PHPUnit\Framework\TestCase;

final class DisponibilidadCitaServiceTest extends TestCase
{
    public function testGeneraTurnosDeUnaHoraDentroDeLaAgenda(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $repositorio->method('buscarHorariosOcupados')->willReturn([]);
        $servicio = new DisponibilidadCitaService($repositorio);

        $turnos = $servicio->obtenerHorariosDisponibles(
            new \DateTimeImmutable('2026-11-03 00:00:00', new \DateTimeZone(DisponibilidadCitaService::ZONA_HORARIA)),
            null,
            new \DateTimeImmutable('2026-11-01 08:00:00', new \DateTimeZone(DisponibilidadCitaService::ZONA_HORARIA)),
        );

        self::assertCount(10, $turnos);
        self::assertSame('08:30', $turnos[0]->format('H:i'));
        self::assertSame('17:30', $turnos[9]->format('H:i'));
    }

    public function testNoOfreceTurnosDuranteElFinDeSemana(): void
    {
        $repositorio = $this->createMock(CitaRepository::class);
        $repositorio->expects(self::never())->method('buscarHorariosOcupados');
        $servicio = new DisponibilidadCitaService($repositorio);

        $turnos = $servicio->obtenerHorariosDisponibles(
            new \DateTimeImmutable('2026-11-07 00:00:00'),
            null,
            new \DateTimeImmutable('2026-11-01 08:00:00'),
        );

        self::assertSame([], $turnos);
    }

    public function testExcluyeTurnosOcupados(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $repositorio->method('buscarHorariosOcupados')->willReturn([
            new \DateTimeImmutable('2026-11-03 10:30:00', new \DateTimeZone(DisponibilidadCitaService::ZONA_HORARIA)),
        ]);
        $servicio = new DisponibilidadCitaService($repositorio);

        $turnos = $servicio->obtenerHorariosDisponibles(
            new \DateTimeImmutable('2026-11-03 00:00:00'),
            null,
            new \DateTimeImmutable('2026-11-01 08:00:00'),
        );

        self::assertCount(9, $turnos);
        self::assertNotContains('10:30', array_map(static fn (\DateTimeImmutable $turno): string => $turno->format('H:i'), $turnos));
    }

    public function testRespetaLasVeinticuatroHorasDeAnticipacion(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $repositorio->method('buscarHorariosOcupados')->willReturn([]);
        $servicio = new DisponibilidadCitaService($repositorio);

        $turnos = $servicio->obtenerHorariosDisponibles(
            new \DateTimeImmutable('2026-11-03 00:00:00', new \DateTimeZone(DisponibilidadCitaService::ZONA_HORARIA)),
            null,
            new \DateTimeImmutable('2026-11-02 10:00:00', new \DateTimeZone(DisponibilidadCitaService::ZONA_HORARIA)),
        );

        self::assertSame('10:30', $turnos[0]->format('H:i'));
    }
}
