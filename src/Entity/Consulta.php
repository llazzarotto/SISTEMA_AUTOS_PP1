<?php

namespace App\Entity;

use App\Repository\ConsultaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ConsultaRepository::class)]
#[ORM\Table(name: 'consulta')]
class Consulta
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private ?string $mensaje = null;

    #[ORM\Column(length: 30)]
    private ?string $estado = 'pendiente';

    #[ORM\Column]
    private \DateTimeImmutable $fechaCreacion;

    #[ORM\ManyToOne(inversedBy: 'consultas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'consultas')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Auto $auto = null;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getMensaje(): ?string { return $this->mensaje; }
    public function setMensaje(string $mensaje): static { $this->mensaje = $mensaje; return $this; }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(string $estado): static { $this->estado = $estado; return $this; }
    public function getFechaCreacion(): \DateTimeImmutable { return $this->fechaCreacion; }
    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): static { $this->fechaCreacion = $fechaCreacion; return $this; }
    public function getUsuario(): ?Usuario { return $this->usuario; }
    public function setUsuario(?Usuario $usuario): static { $this->usuario = $usuario; return $this; }
    public function getAuto(): ?Auto { return $this->auto; }
    public function setAuto(?Auto $auto): static { $this->auto = $auto; return $this; }
}
