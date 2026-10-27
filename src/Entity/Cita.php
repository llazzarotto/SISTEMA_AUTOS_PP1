<?php

namespace App\Entity;

use App\Repository\CitaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CitaRepository::class)]
#[ORM\Table(name: 'cita')]
class Cita
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $fechaHora = null;

    #[ORM\Column(length: 30)]
    private ?string $estado = 'pendiente';

    #[ORM\Column(length: 1000, nullable: true)]
    private ?string $observaciones = null;

    #[ORM\ManyToOne(inversedBy: 'citas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'citas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Auto $auto = null;

    public function getId(): ?int { return $this->id; }
    public function getFechaHora(): ?\DateTimeImmutable { return $this->fechaHora; }
    public function setFechaHora(\DateTimeImmutable $fechaHora): static { $this->fechaHora = $fechaHora; return $this; }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(string $estado): static { $this->estado = $estado; return $this; }
    public function getObservaciones(): ?string { return $this->observaciones; }
    public function setObservaciones(?string $observaciones): static { $this->observaciones = $observaciones; return $this; }
    public function getUsuario(): ?Usuario { return $this->usuario; }
    public function setUsuario(?Usuario $usuario): static { $this->usuario = $usuario; return $this; }
    public function getAuto(): ?Auto { return $this->auto; }
    public function setAuto(?Auto $auto): static { $this->auto = $auto; return $this; }
}
