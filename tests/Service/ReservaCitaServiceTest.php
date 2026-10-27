<?php

namespace App\Tests\Service;

use App\Entity\Cita;
use App\Entity\Usuario;
use App\Repository\CitaRepository;
use App\Service\DisponibilidadCitaService;
use App\Service\ReservaCitaService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class ReservaCitaServiceTest extends TestCase
{
    public function testRegistraUnaCitaPendienteParaElUsuario(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $repositorio->method('contarPendientesDelCliente')->willReturn(0);
        $disponibilidad = $this->createStub(DisponibilidadCitaService::class);
        $disponibilidad->method('esHorarioDisponible')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(Cita::class));
        $entityManager->expects(self::once())->method('flush');
        $servicio = new ReservaCitaService($repositorio, $disponibilidad, $entityManager);
        $usuario = (new Usuario())->setEmail('cliente@ejemplo.com');
        $fechaHora = new \DateTimeImmutable('2026-11-03 08:30:00');

        $cita = $servicio->reservar($usuario, Cita::SERVICIO_ASESOR_FINANCIERO, null, $fechaHora);

        self::assertSame($usuario, $cita->getUsuario());
        self::assertSame(Cita::ESTADO_PENDIENTE, $cita->getEstado());
        self::assertSame(Cita::SERVICIO_ASESOR_FINANCIERO, $cita->getTipoServicio());
        self::assertSame($fechaHora, $cita->getFechaHoraBloqueada());
    }

    public function testImpideUnaTerceraCitaPendiente(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $repositorio->method('contarPendientesDelCliente')->willReturn(2);
        $disponibilidad = $this->createStub(DisponibilidadCitaService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $servicio = new ReservaCitaService($repositorio, $disponibilidad, $entityManager);

        $this->expectException(\DomainException::class);
        $servicio->reservar(new Usuario(), Cita::SERVICIO_TEST_DRIVE, null, new \DateTimeImmutable('+3 days'));
    }

    public function testCancelarLiberaElHorario(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $disponibilidad = $this->createStub(DisponibilidadCitaService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $servicio = new ReservaCitaService($repositorio, $disponibilidad, $entityManager);
        $cita = (new Cita())
            ->setTipoServicio(Cita::SERVICIO_TEST_DRIVE)
            ->setFechaHora(new \DateTimeImmutable('2026-11-05 08:30:00'));

        $servicio->cancelar($cita, new \DateTimeImmutable('2026-11-03 08:00:00'));

        self::assertSame(Cita::ESTADO_CANCELADA, $cita->getEstado());
        self::assertNull($cita->getFechaHoraBloqueada());
    }

    public function testImpideCancelarConMenosDeVeinticuatroHoras(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $disponibilidad = $this->createStub(DisponibilidadCitaService::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $servicio = new ReservaCitaService($repositorio, $disponibilidad, $entityManager);
        $cita = (new Cita())
            ->setTipoServicio(Cita::SERVICIO_TEST_DRIVE)
            ->setFechaHora(new \DateTimeImmutable('2026-11-03 08:30:00'));

        $this->expectException(\DomainException::class);
        $servicio->cancelar($cita, new \DateTimeImmutable('2026-11-02 12:00:00'));
    }

    public function testReprogramaUnaCitaDisponible(): void
    {
        $repositorio = $this->createStub(CitaRepository::class);
        $disponibilidad = $this->createStub(DisponibilidadCitaService::class);
        $disponibilidad->method('esHorarioDisponible')->willReturn(true);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');
        $servicio = new ReservaCitaService($repositorio, $disponibilidad, $entityManager);
        $cita = (new Cita())
            ->setTipoServicio(Cita::SERVICIO_ASESORAMIENTO_SALON)
            ->setFechaHora(new \DateTimeImmutable('2026-11-05 08:30:00'));
        $nuevoTurno = new \DateTimeImmutable('2026-11-06 09:30:00');

        $servicio->reprogramar($cita, $nuevoTurno, new \DateTimeImmutable('2026-11-03 08:00:00'));

        self::assertSame($nuevoTurno, $cita->getFechaHora());
        self::assertSame($nuevoTurno, $cita->getFechaHoraBloqueada());
    }
}
