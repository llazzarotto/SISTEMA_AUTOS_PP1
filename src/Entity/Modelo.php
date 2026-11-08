<?php

namespace App\Entity;

use App\Repository\ModeloRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ModeloRepository::class)]
#[ORM\Table(name: 'modelo')]
class Modelo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\ManyToOne(inversedBy: 'modelos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Marca $marca = null;

    /** @var Collection<int, Auto> */
    #[ORM\OneToMany(targetEntity: Auto::class, mappedBy: 'modelo')]
    private Collection $autos;

    public function __construct()
    {
        $this->autos = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }
    public function getMarca(): ?Marca { return $this->marca; }
    public function setMarca(?Marca $marca): static { $this->marca = $marca; return $this; }

    /** @return Collection<int, Auto> */
    public function getAutos(): Collection { return $this->autos; }
    public function addAuto(Auto $auto): static
    {
        if (!$this->autos->contains($auto)) {
            $this->autos->add($auto);
            $auto->setModelo($this);
        }
        return $this;
    }
    public function removeAuto(Auto $auto): static
    {
        if ($this->autos->removeElement($auto) && $auto->getModelo() === $this) {
            $auto->setModelo(null);
        }
        return $this;
    }
}
