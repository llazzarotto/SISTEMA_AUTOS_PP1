<?php

namespace App\Entity;

use App\Repository\RolRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RolRepository::class)]
#[ORM\Table(name: 'rol')]
class Rol
{
    public const NOMBRE_CLIENTE = 'Cliente';
    public const NOMBRE_ADMIN = 'Administrador';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $nombre = null;

    /** @var Collection<int, Usuario> */
    #[ORM\OneToMany(targetEntity: Usuario::class, mappedBy: 'rol')]
    private Collection $usuarios;

    public function __construct()
    {
        $this->usuarios = new ArrayCollection();
    }

    public function getId(): ?int { return $this->id; }
    public function getNombre(): ?string { return $this->nombre; }
    public function setNombre(string $nombre): static { $this->nombre = $nombre; return $this; }

    public function getNivel(): int
    {
        // Los niveles sirven para comparar permisos: Cliente = 1, Administrador = 2.
        $nombre = strtolower($this->nombre ?? '');
        if ($nombre === 'administrador' || $nombre === 'admin' || $nombre === 'role_admin') {
            return 2;
        }
        if ($nombre === 'cliente' || $nombre === 'role_cliente' || $nombre === 'role_user') {
            return 1;
        }

        return 0;
    }

    /** @return Collection<int, Usuario> */
    public function getUsuarios(): Collection { return $this->usuarios; }

    public function addUsuario(Usuario $usuario): static
    {
        if (!$this->usuarios->contains($usuario)) {
            $this->usuarios->add($usuario);
            $usuario->setRol($this);
        }
        return $this;
    }

    public function removeUsuario(Usuario $usuario): static
    {
        if ($this->usuarios->removeElement($usuario) && $usuario->getRol() === $this) {
            $usuario->setRol(null);
        }
        return $this;
    }
}
