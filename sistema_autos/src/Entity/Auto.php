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
    public const ESTADO_DISPONIBLE = 'disponible';
    public const ESTADO_RESERVADO = 'reservado';
    public const ESTADO_VENDIDO = 'vendido';
    public const ESTADO_INACTIVO = 'inactivo';

    public const ESTADOS = [
        self::ESTADO_DISPONIBLE,
        self::ESTADO_RESERVADO,
        self::ESTADO_VENDIDO,
        self::ESTADO_INACTIVO,
    ];

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
    private ?string $estado = self::ESTADO_DISPONIBLE;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $descripcion = null;

    /** @var Collection<int, ImagenAuto> */
    #[ORM\OneToMany(targetEntity: ImagenAuto::class, mappedBy: 'auto', cascade: ['persist', 'remove'], orphanRemoval: true)]
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
    public function getEstado(): ?string
    {
        return $this->estado !== null ? strtolower(trim($this->estado)) : null;
    }

    public function setEstado(string $estado): static
    {
        $estadoNormalizado = strtolower(trim($estado));
        if (!in_array($estadoNormalizado, self::ESTADOS, true)) {
            throw new \InvalidArgumentException('El estado del vehículo no es válido.');
        }

        $this->estado = $estadoNormalizado;

        return $this;
    }

    public function estaDisponible(): bool
    {
        return $this->getEstado() === self::ESTADO_DISPONIBLE;
    }
    public function getDescripcion(): ?string { return $this->descripcion; }
    public function setDescripcion(?string $descripcion): static { $this->descripcion = $descripcion; return $this; }

    /** @return Collection<int, ImagenAuto> */
    public function getImagenes(): Collection { return $this->imagenes; }

    /**
     * Devuelve hasta 5 rutas de imagen combinando las registradas en base de datos
     * y los archivos presentes en public/assets/img/ (auto{id}.{ext} o auto{id}_{1..5}.{ext}).
     *
     * @return list<string>
     */
    public function obtenerRutasImagenes(): array
    {
        $rutas = [];
        foreach ($this->imagenes as $imagen) {
            $ruta = $imagen->getRuta();
            if ($ruta !== null && $ruta !== '') {
                $rutas[] = $ruta;
            }
        }

        if ($this->id !== null && count($rutas) < 5) {
            $dirPublico = dirname(__DIR__, 2) . '/public/';
            $extensiones = ['jpg', 'jpeg', 'png', 'webp'];
            $patrones = [
                sprintf('assets/img/auto%d', $this->id),
                sprintf('assets/img/auto%d_1', $this->id),
                sprintf('assets/img/auto%d_2', $this->id),
                sprintf('assets/img/auto%d_3', $this->id),
                sprintf('assets/img/auto%d_4', $this->id),
                sprintf('assets/img/auto%d_5', $this->id),
            ];

            foreach ($patrones as $base) {
                foreach ($extensiones as $ext) {
                    $candidata = $base . '.' . $ext;
                    if (!in_array($candidata, $rutas, true) && is_file($dirPublico . $candidata)) {
                        $rutas[] = $candidata;
                        if (count($rutas) >= 5) {
                            break 2;
                        }
                    }
                }
            }
        }

        return array_slice($rutas, 0, 5);
    }
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
