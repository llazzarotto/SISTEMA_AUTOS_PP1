<?php

namespace App\Entity;

use App\Repository\MarcaRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MarcaRepository::class)]
#[ORM\Table(name: 'marca')]
class Marca
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $nombre = null;

    /** @var Collection<int, Modelo> */
    #[ORM\OneToMany(targetEntity: Modelo::class, mappedBy: 'marca')]
    private Collection $modelos;

    public function __construct()
    {
        $this->modelos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    /** @return Collection<int, Modelo> */
    public function getModelos(): Collection { return $this->modelos; }
    public function addModelo(Modelo $modelo): static
    {
        if (!$this->modelos->contains($modelo)) {
            $this->modelos->add($modelo);
            $modelo->setMarca($this);
        }
        return $this;
    }
    public function removeModelo(Modelo $modelo): static
    {
        if ($this->modelos->removeElement($modelo) && $modelo->getMarca() === $this) {
            $modelo->setMarca(null);
        }
        return $this;
    }
}
