<?php

namespace App\Manager;

use App\Entity\Rol;
use App\Entity\Usuario;
use App\Repository\RolRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;

final class SecurityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private RolRepository $roles,
        private UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function registrar(string $email, string $password): void
    {
        $rol = $this->roles->findOneBy(['nombre' => 'Cliente']);
        if ($rol === null) {
            $rol = (new Rol())->setNombre('Cliente');
            $this->entityManager->persist($rol);
        }

        // El registro público solo solicita email y contraseña y siempre crea un cliente.
        $usuario = (new Usuario())
            ->setNombre('')
            ->setApellido('')
            ->setEmail($email)
            ->setRol($rol);

        // Se guarda únicamente el hash, como en el ejemplo de Seguridad de clase.
        $hash = $this->passwordHasher->hashPassword($usuario, $password);
        $usuario->setPassword($hash);

        $this->entityManager->persist($usuario);
        $this->entityManager->flush();
    }

    public function mensajeError(?AuthenticationException $error): ?string
    {
        if ($error === null) {
            return null;
        }
        if ($error instanceof UserNotFoundException) {
            return 'El email no pertenece a una cuenta registrada.';
        }
        if ($error instanceof BadCredentialsException) {
            return 'La contraseña es incorrecta para ese email.';
        }
        if ($error instanceof InvalidCsrfTokenException) {
            return 'El formulario venció. Volvé a intentar.';
        }

        return 'No se pudo iniciar sesión. Revisá los datos e intentá nuevamente.';
    }
}
