<?php

namespace App\Entity;

use App\Repository\FavoritoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FavoritoRepository::class)]
#[ORM\Table(name: 'favorito')]
#[ORM\UniqueConstraint(name: 'uniq_favorito_usuario_auto', columns: ['usuario_id', 'auto_id'])]
class Favorito
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'favoritos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Usuario $usuario = null;

    #[ORM\ManyToOne(inversedBy: 'favoritos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Auto $auto = null;

    #[ORM\Column]
    private \DateTimeImmutable $fechaCreacion;

    public function __construct()
    {
        $this->fechaCreacion = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUsuario(): ?Usuario { return $this->usuario; }
    public function setUsuario(?Usuario $usuario): static { $this->usuario = $usuario; return $this; }
    public function getAuto(): ?Auto { return $this->auto; }
    public function setAuto(?Auto $auto): static { $this->auto = $auto; return $this; }
    public function getFechaCreacion(): \DateTimeImmutable { return $this->fechaCreacion; }
    public function setFechaCreacion(\DateTimeImmutable $fechaCreacion): static { $this->fechaCreacion = $fechaCreacion; return $this; }
}
