<?php

namespace App\Entity;

use App\Repository\ImagenAutoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ImagenAutoRepository::class)]
#[ORM\Table(name: 'imagen_auto')]
class ImagenAuto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500)]
    private ?string $ruta = null;

    #[ORM\ManyToOne(inversedBy: 'imagenes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Auto $auto = null;

    public function getId(): ?int { return $this->id; }
    public function getRuta(): ?string { return $this->ruta; }
    public function setRuta(string $ruta): static { $this->ruta = $ruta; return $this; }
    public function getAuto(): ?Auto { return $this->auto; }
    public function setAuto(?Auto $auto): static { $this->auto = $auto; return $this; }
}
