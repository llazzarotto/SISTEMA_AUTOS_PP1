<?php

namespace App\Controller;

use App\Form\RegistroType;
use App\Manager\SecurityManager;
use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authentication, SecurityManager $manager): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'email' => $authentication->getLastUsername(),
            'error' => $manager->mensajeError($authentication->getLastAuthenticationError()),
        ]);
    }

    #[Route('/registro', name: 'app_registro', methods: ['GET', 'POST'])]
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function registro(Request $request, SecurityManager $manager, UsuarioRepository $usuarios): Response
    {
        if ($this->getUser() !== null) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RegistroType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $datos = $form->getData();
            $email = strtolower(trim($datos['email']));
            if ($usuarios->findOneBy(['email' => $email]) !== null) {
                $form->get('email')->addError(new FormError('Este email ya está registrado.'));
            } else {
                try {
                    $manager->registrar($email, $datos['password']);
                    $this->addFlash('success', 'La cuenta fue creada. Ya podés iniciar sesión.');
                    return $this->redirectToRoute('app_login');
                } catch (UniqueConstraintViolationException) {
                    $form->get('email')->addError(new FormError('Este email ya está registrado.'));
                }
            }
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['POST'])]
    public function logout(): void
    {
        // Symfony intercepta esta ruta y cierra la sesión.
        throw new \LogicException('Esta ruta es administrada por Symfony Security.');
    }
}
