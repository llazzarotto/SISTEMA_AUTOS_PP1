<?php

namespace App\Controller;

use App\Entity\Cita;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/citas')]
#[IsGranted('ROLE_ADMIN')]
class CitaController extends AbstractController
{
    #[Route('', name: 'citas_admin', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $estado = $request->query->get('estado');

        if ($estado !== null && !in_array($estado, Cita::ESTADOS, true)) {
            $estado = null;
        }

        $criterios = [];

        if ($estado !== null && $estado !== '') {
            $criterios['estado'] = $estado;
        }

        $citas = $em->getRepository(Cita::class)->findBy(
            $criterios,
            ['fechaHora' => 'ASC']
        );

        return $this->render('citas/index.html.twig', [
            'citas' => $citas,
            'estadoSeleccionado' => $estado,
            'estados' => Cita::ESTADOS,
        ]);
    }

    #[Route('/{id}/estado', name: 'citas_cambiar_estado', methods: ['POST'])]
    public function cambiarEstado(
        Cita $cita,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid(
            'cambiar_estado_cita_' . $cita->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        $nuevoEstado = $request->request->get('estado');

        if (!in_array($nuevoEstado, Cita::ESTADOS, true)) {
            $this->addFlash('danger', 'El estado seleccionado no es válido.');

            return $this->redirectToRoute('citas_admin');
        }

        $cita->setEstado($nuevoEstado);
        $em->flush();

        $this->addFlash('success', 'El estado de la cita se actualizó correctamente.');

        return $this->redirectToRoute('citas_admin');
    }
}
