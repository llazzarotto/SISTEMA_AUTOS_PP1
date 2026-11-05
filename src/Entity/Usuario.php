<?php

namespace App\Entity;

use App\Repository\UsuarioRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UsuarioRepository::class)]
#[ORM\Table(name: 'usuario')]
class Usuario implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nombre = null;

    #[ORM\Column(length: 100)]
    private ?string $apellido = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $email = null;

    // Contiene el hash generado por Symfony, nunca la contraseña original.
    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $telefono = null;

    #[ORM\ManyToOne(inversedBy: 'usuarios')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Rol $rol = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $fechaNacimiento = null;

    /** @var Collection<int, Favorito> */
    #[ORM\OneToMany(targetEntity: Favorito::class, mappedBy: 'usuario', orphanRemoval: true)]
    private Collection $favoritos;

    /** @var Collection<int, Consulta> */
    #[ORM\OneToMany(targetEntity: Consulta::class, mappedBy: 'usuario')]
    private Collection $consultas;

    /** @var Collection<int, Cita> */
    #[ORM\OneToMany(targetEntity: Cita::class, mappedBy: 'usuario')]
    private Collection $citas;

    public function __construct()
    {
        $this->favoritos = new ArrayCollection();
        $this->consultas = new ArrayCollection();
        $this->citas = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }
    public function getApellido(): ?string { return $this->apellido; }
    public function setApellido(string $apellido): static { $this->apellido = $apellido; return $this; }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = strtolower(trim($email)); return $this; }
    public function getPassword(): ?string { return $this->password; }
    public function setPassword(string $password): static { $this->password = $password; return $this; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function setTelefono(?string $telefono): static { $this->telefono = $telefono; return $this; }
    public function getRol(): ?Rol { return $this->rol; }
    public function setRol(?Rol $rol): static { $this->rol = $rol; return $this; }
    public function getFechaNacimiento(): ?\DateTimeImmutable { return $this->fechaNacimiento; }
    public function setFechaNacimiento(?\DateTimeImmutable $fechaNacimiento): static { $this->fechaNacimiento = $fechaNacimiento; return $this; }

    public function getUserIdentifier(): string
    {
        return $this->email ?? '';
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];
        if ($this->rol !== null && $this->rol->getNivel() >= 2) {
            $roles[] = 'ROLE_ADMIN';
        }

        return $roles;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
    }

    public function __serialize(): array
    {
        // PHP usa este método al guardar el usuario en la sesión, sin sus relaciones.
        // crc32c crea una huella del hash para la sesión; no cambia el hash de la base.
        return [
            'id' => $this->id,
            'email' => $this->email,
            'password' => $this->password === null ? null : hash('crc32c', $this->password),
        ];
    }

    public function __unserialize(array $datos): void
    {
        // Recupera los datos de sesión; Symfony vuelve a cargar el usuario desde la base.
        $this->__construct();
        $this->id = $datos['id'];
        $this->email = $datos['email'];
        $this->password = $datos['password'];
    }

    /** @return Collection<int, Favorito> */
    public function getFavoritos(): Collection { return $this->favoritos; }
    public function addFavorito(Favorito $favorito): static
    {
        if (!$this->favoritos->contains($favorito)) {
            $this->favoritos->add($favorito);
            $favorito->setUsuario($this);
        }
        return $this;
    }
    public function removeFavorito(Favorito $favorito): static
    {
        if ($this->favoritos->removeElement($favorito) && $favorito->getUsuario() === $this) {
            $favorito->setUsuario(null);
        }
        return $this;
    }

    /** @return Collection<int, Consulta> */
    public function getConsultas(): Collection { return $this->consultas; }
    public function addConsulta(Consulta $consulta): static
    {
        if (!$this->consultas->contains($consulta)) {
            $this->consultas->add($consulta);
            $consulta->setUsuario($this);
        }
        return $this;
    }
    public function removeConsulta(Consulta $consulta): static
    {
        if ($this->consultas->removeElement($consulta) && $consulta->getUsuario() === $this) {
            $consulta->setUsuario(null);
        }
        return $this;
    }

    /** @return Collection<int, Cita> */
    public function getCitas(): Collection { return $this->citas; }
    public function addCita(Cita $cita): static
    {
        if (!$this->citas->contains($cita)) {
            $this->citas->add($cita);
            $cita->setUsuario($this);
        }
        return $this;
    }
    public function removeCita(Cita $cita): static
    {
        if ($this->citas->removeElement($cita) && $cita->getUsuario() === $this) {
            $cita->setUsuario(null);
        }
        return $this;
    }
}
