<?php

namespace App\Controller;

use App\Entity\Usuario;
use App\Form\UsuarioType;
use App\Manager\UsuarioManager;
use App\Repository\UsuarioRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;

#[IsGranted('ROLE_ADMIN')]
#[Route('/usuarios')]
final class UsuarioController extends AbstractController
{
    public function __construct(
        private UsuarioManager $usuarioManager,
        private UsuarioRepository $usuarioRepository,
    ) {
    }

    #[Route('', name: 'app_usuarios', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('usuario/index.html.twig', [
            'usuarios' => $this->usuarioManager->obtenerUsuarios(),
        ]);
    }

    #[Route('/crear', name: 'app_usuario_crear', methods: ['GET', 'POST'])]
    public function crear(Request $request): Response
    {
        $usuario = new Usuario();
        $form = $this->crearFormulario($usuario, true);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->validarEmail($form, $usuario)) {
            try {
                $this->usuarioManager->crear($usuario, $this->getUser(), $form->get('password')->getData());
                $this->addFlash('success', 'Usuario creado correctamente.');

                return $this->redirectToRoute('app_usuarios');
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('Este email ya está registrado.'));
            }
        }

        return $this->render('usuario/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_usuario_ver', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function ver(int $id): Response
    {
        $usuario = $this->obtenerUsuario($id);

        return $this->render('usuario/show.html.twig', [
            'usuarioDetalle' => $usuario,
            'puedeGestionar' => $this->usuarioManager->puedeGestionar($usuario, $this->getUser()),
        ]);
    }

    #[Route('/{id}/editar', name: 'app_usuario_editar', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function editar(int $id, Request $request): Response
    {
        $usuario = $this->obtenerUsuario($id);
        $this->comprobarPermiso($usuario);
        $form = $this->crearFormulario($usuario);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->validarEmail($form, $usuario)) {
            try {
                $this->usuarioManager->modificar($usuario, $this->getUser());
                $this->addFlash('success', 'Los datos fueron modificados correctamente.');

                return $this->redirectToRoute('app_usuarios');
            } catch (UniqueConstraintViolationException) {
                $form->get('email')->addError(new FormError('Este email ya está registrado.'));
            }
        }

        return $this->render('usuario/edit.html.twig', ['form' => $form, 'usuarioDetalle' => $usuario]);
    }

    #[Route('/{id}/eliminar', name: 'app_usuario_eliminar', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function eliminar(int $id, Request $request): Response
    {
        $usuario = $this->obtenerUsuario($id);
        $this->comprobarPermiso($usuario);
        $form = $this->createFormBuilder(null, ['csrf_token_id' => 'usuario_eliminar_'.$id])
            ->add('razon', TextareaType::class, [
                'label' => 'Razón de la eliminación',
                'constraints' => [new Assert\NotBlank(message: 'Ingresá la razón de la eliminación.')],
            ])
            ->add('confirmar', CheckboxType::class, [
                'label' => 'Confirmo que deseo eliminar este usuario y sus citas, consultas y favoritos.',
                'constraints' => [new Assert\IsTrue(message: 'Confirmá la eliminación para continuar.')],
            ])
            ->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->usuarioManager->eliminar($usuario, $this->getUser(), $form->get('razon')->getData());
            $this->addFlash('success', 'El usuario y sus registros relacionados fueron eliminados.');

            return $this->redirectToRoute('app_usuarios');
        }

        return $this->render('usuario/delete.html.twig', ['form' => $form, 'usuarioDetalle' => $usuario]);
    }

    private function crearFormulario(Usuario $usuario, bool $esNuevo = false): FormInterface
    {
        return $this->createForm(UsuarioType::class, $usuario, [
            'roles_permitidos' => $this->usuarioManager->obtenerRolesPermitidos($this->getUser()),
            'es_nuevo' => $esNuevo,
        ]);
    }

    private function validarEmail(FormInterface $form, Usuario $usuario): bool
    {
        if (!$this->usuarioManager->emailDisponible($usuario->getEmail(), $usuario->getId())) {
            $form->get('email')->addError(new FormError('Este email ya está registrado.'));

            return false;
        }

        return true;
    }

    private function obtenerUsuario(int $id): Usuario
    {
        $usuario = $this->usuarioRepository->find($id);
        if ($usuario === null) {
            throw $this->createNotFoundException('El usuario no existe.');
        }

        return $usuario;
    }

    private function comprobarPermiso(Usuario $usuario): void
    {
        if (!$this->usuarioManager->puedeGestionar($usuario, $this->getUser())) {
            throw $this->createAccessDeniedException('No podés gestionar un usuario con un rol superior al tuyo.');
        }
    }
}
