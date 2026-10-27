<?php

namespace App\Entity;

use App\Repository\CitaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CitaRepository::class)]
#[ORM\Table(name: 'cita')]
#[ORM\HasLifecycleCallbacks]
class Cita
{
    public const SERVICIO_TEST_DRIVE = 'Test Drive';
    public const SERVICIO_ASESORAMIENTO_SALON = 'Asesoramiento en salon';
    public const SERVICIO_ASESOR_FINANCIERO = 'Asesor financiero';

    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_CANCELADA = 'cancelada';
    public const ESTADO_COMPLETADA = 'completada';

    public const SERVICIOS = [
        self::SERVICIO_TEST_DRIVE,
        self::SERVICIO_ASESORAMIENTO_SALON,
        self::SERVICIO_ASESOR_FINANCIERO,
    ];

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_COMPLETADA,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fechaHora = null;

    /**
     * Copia la fecha/hora solamente mientras la cita ocupa el turno. La restriccion
     * UNIQUE evita reservas simultaneas; al cancelar o completar queda en null y
     * el horario vuelve a estar disponible.
     */
    #[ORM\Column(nullable: true, unique: true)]
    private ?\DateTimeImmutable $fechaHoraBloqueada = null;

    #[ORM\Column(length: 50)]
    private ?string $tipoServicio = null;

    #[ORM\Column(length: 30)]
    private ?string $estado = self::ESTADO_PENDIENTE;

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $observaciones = null;

    #[ORM\ManyToOne(inversedBy: 'citas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'citas')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Auto $auto = null;

    public function getId(): ?int { return $this->id; }
    public function getFechaHora(): ?\DateTimeImmutable { return $this->fechaHora; }
    public function setFechaHora(\DateTimeImmutable $fechaHora): static
    {
        $this->fechaHora = $fechaHora;
        $this->sincronizarBloqueoHorario();

        return $this;
    }
    public function getFechaHoraBloqueada(): ?\DateTimeImmutable { return $this->fechaHoraBloqueada; }
    public function getTipoServicio(): ?string { return $this->tipoServicio; }
    public function setTipoServicio(string $tipoServicio): static
    {
        if (!in_array($tipoServicio, self::SERVICIOS, true)) {
            throw new \InvalidArgumentException('El tipo de servicio seleccionado no es valido.');
        }

        $this->tipoServicio = $tipoServicio;

        return $this;
    }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(string $estado): static
    {
        if (!in_array($estado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException('El estado de la cita no es valido.');
        }

        $this->estado = $estado;
        $this->sincronizarBloqueoHorario();

        return $this;
    }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): static { $this->observaciones = $observaciones; return $this; }
    public function getUsuario(): ?Usuario { return $this->usuario; }
    public function setUsuario(?Usuario $usuario): static { $this->usuario = $usuario; return $this; }
    public function getAuto(): ?Auto { return $this->auto; }
    public function setAuto(?Auto $auto): static { $this->auto = $auto; return $this; }

    public function estaActiva(): bool
    {
        return in_array($this->estado, [self::ESTADO_PENDIENTE, self::ESTADO_CONFIRMADA], true);
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function sincronizarBloqueoHorario(): void
    {
        $this->fechaHoraBloqueada = $this->estaActiva() ? $this->fechaHora : null;
    }
}
