<?php

namespace App\Entity;

use App\Repository\AutoRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AutoRepository::class)]
#[ORM\Table(name: 'auto')]
class Auto
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'autos')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Modelo $modelo = null;

    #[ORM\Column]
    private ?int $anio = null;

    #[ORM\Column]
    private ?int $kilometraje = null;

    #[ORM\Column]
    private ?int $precio = null;

    #[ORM\Column(length: 30)]
    private ?string $estado = 'disponible';

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    /** @var Collection<int, ImagenAuto> */
    #[ORM\OneToMany(targetEntity: ImagenAuto::class, mappedBy: 'auto', orphanRemoval: true)]
    private Collection $imagenes;

    /** @var Collection<int, Favorito> */
    #[ORM\OneToMany(targetEntity: Favorito::class, mappedBy: 'auto', orphanRemoval: true)]
    private Collection $favoritos;

    /** @var Collection<int, Consulta> */
    #[ORM\OneToMany(targetEntity: Consulta::class, mappedBy: 'auto')]
    private Collection $consultas;

    /** @var Collection<int, Cita> */
    #[ORM\OneToMany(targetEntity: Cita::class, mappedBy: 'auto')]
    private Collection $citas;

    public function __construct()
    {
        $this->imagenes = new ArrayCollection();
        $this->favoritos = new ArrayCollection();
        $this->consultas = new ArrayCollection();
        $this->citas = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getModelo(): ?Modelo { return $this->modelo; }
    public function setModelo(?Modelo $modelo): static { $this->modelo = $modelo; return $this; }
    public function getAnio(): ?int { return $this->anio; }
    public function setAnio(int $anio): static { $this->anio = $anio; return $this; }
    public function getKilometraje(): ?int { return $this->kilometraje; }
    public function setKilometraje(int $kilometraje): static { $this->kilometraje = $kilometraje; return $this; }
    public function getPrecio(): ?int { return $this->precio; }
    public function setPrecio(int $precio): static { $this->precio = $precio; return $this; }
    public function getEstado(): ?string { return $this->estado; }
    public function setEstado(string $estado): static { $this->estado = $estado; return $this; }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }

    /** @return Collection<int, ImagenAuto> */
    public function getImagenes(): Collection { return $this->imagenes; }
    public function addImagen(ImagenAuto $imagen): static
    {
        if (!$this->imagenes->contains($imagen)) {
            $this->imagenes->add($imagen);
            $imagen->setAuto($this);
        }
        return $this;
    }
    public function removeImagen(ImagenAuto $imagen): static
    {
        if ($this->imagenes->removeElement($imagen) && $imagen->getAuto() === $this) {
            $imagen->setAuto(null);
        }
        return $this;
    }

    /** @return Collection<int, Favorito> */
    public function getFavoritos(): Collection { return $this->favoritos; }
    public function addFavorito(Favorito $favorito): static
    {
        if (!$this->favoritos->contains($favorito)) {
            $this->favoritos->add($favorito);
            $favorito->setAuto($this);
        }
        return $this;
    }
    public function removeFavorito(Favorito $favorito): static
    {
        if ($this->favoritos->removeElement($favorito) && $favorito->getAuto() === $this) {
            $favorito->setAuto(null);
        }
        return $this;
    }

    /** @return Collection<int, Consulta> */
    public function getConsultas(): Collection { return $this->consultas; }
    public function addConsulta(Consulta $consulta): static
    {
        if (!$this->consultas->contains($consulta)) {
            $this->consultas->add($consulta);
            $consulta->setAuto($this);
        }
        return $this;
    }
    public function removeConsulta(Consulta $consulta): static
    {
        if ($this->consultas->removeElement($consulta) && $consulta->getAuto() === $this) {
            $consulta->setAuto(null);
        }
        return $this;
    }

    /** @return Collection<int, Cita> */
    public function getCitas(): Collection { return $this->citas; }
    public function addCita(Cita $cita): static
    {
        if (!$this->citas->contains($cita)) {
            $this->citas->add($cita);
            $cita->setAuto($this);
        }
        return $this;
    }
    public function removeCita(Cita $cita): static
    {
        if ($this->citas->removeElement($cita) && $cita->getAuto() === $this) {
            $cita->setAuto(null);
        }
        return $this;
    }
}
