<?php

namespace App\Manager;

use App\Entity\Usuario;
use App\Repository\RolRepository;
use App\Repository\UsuarioRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class UsuarioManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UsuarioRepository $usuarioRepository,
        private RolRepository $rolRepository,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function obtenerUsuarios(): array
    {
        return $this->usuarioRepository->findBy([], ['nombre' => 'ASC', 'email' => 'ASC']);
    }

    public function obtenerRolesPermitidos(Usuario $administrador): array
    {
        $nivelAdministrador = $administrador->getRol()?->getNivel() ?? 0;
        $rolesPermitidos = [];

        foreach ($this->rolRepository->findBy([], ['id' => 'ASC']) as $rol) {
            $nivelRol = $rol->getNivel();
            if ($nivelRol > 0 && $nivelRol <= $nivelAdministrador) {
                $rolesPermitidos[] = $rol;
            }
        }

        return $rolesPermitidos;
    }

    public function emailDisponible(string $email, ?int $usuarioId = null): bool
    {
        $existente = $this->usuarioRepository->findOneBy(['email' => mb_strtolower(trim($email))]);

        return $existente === null || ($usuarioId !== null && $existente->getId() === $usuarioId);
    }

    public function puedeGestionar(Usuario $usuario, Usuario $administrador): bool
    {
        return ($usuario->getRol()?->getNivel() ?? 0) <= ($administrador->getRol()?->getNivel() ?? 0);
    }

    public function crear(Usuario $usuario, Usuario $administrador, string $password): void
    {
        $this->comprobarJerarquia($usuario, $administrador);
        $usuario->setApellido($usuario->getApellido() ?? '');
        $usuario->setPassword($this->passwordHasher->hashPassword($usuario, $password));
        $this->entityManager->persist($usuario);
        $this->entityManager->flush();
    }

    public function modificar(Usuario $usuario, Usuario $administrador): void
    {
        $this->comprobarJerarquia($usuario, $administrador);

        $this->entityManager->flush();
    }

    public function eliminar(Usuario $usuario, Usuario $administrador, string $razon): void
    {
        $this->comprobarJerarquia($usuario, $administrador);
        if (trim($razon) === '') {
            throw new \InvalidArgumentException('Ingresá la razón de la eliminación.');
        }

        // Primero se marcan los registros relacionados y después el usuario.
        foreach ($usuario->getCitas() as $cita) {
            $this->entityManager->remove($cita);
        }
        foreach ($usuario->getConsultas() as $consulta) {
            $this->entityManager->remove($consulta);
        }
        foreach ($usuario->getFavoritos() as $favorito) {
            $this->entityManager->remove($favorito);
        }
        $this->entityManager->remove($usuario);

        // Un solo flush guarda todas las eliminaciones en una transacción de Doctrine.
        $this->entityManager->flush();
    }

    private function comprobarJerarquia(Usuario $usuario, Usuario $administrador): void
    {
        if (!$this->puedeGestionar($usuario, $administrador)) {
            throw new AccessDeniedException('No podés gestionar un usuario con un rol superior al tuyo.');
        }
    }
}
