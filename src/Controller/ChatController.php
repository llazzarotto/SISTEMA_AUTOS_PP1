<?php

namespace App\Controller;

use App\Entity\Auto;
use App\Entity\Usuario;
use App\Manager\ChatManager;
use App\Repository\AutoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ChatController extends AbstractController
{
    public function __construct(
        private ChatManager $chatManager,
        private AutoRepository $autoRepository,
    ) {
    }

    #[Route('/chat', name: 'app_chat', methods: ['GET'])]
    #[Route('/contacto', name: 'app_contacto', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->renderChat($request, null);
    }

    #[Route('/chat/enviar', name: 'app_chat_enviar', methods: ['POST'])]
    public function enviar(Request $request): Response
    {
        $esJson = str_contains($request->headers->get('Content-Type', ''), 'application/json')
            || str_contains($request->headers->get('Accept', ''), 'application/json')
            || $request->isXmlHttpRequest();

        $usuario = $this->usuarioActual();
        if ($usuario === null) {
            if ($esJson) {
                return $this->json([
                    'estado' => 'ERROR',
                    'mensaje' => 'Debe iniciar sesión para enviar una consulta.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            return $this->redirectToRoute('app_login');
        }

        $datos = json_decode($request->getContent(), true);
        if (!is_array($datos)) {
            $datos = $request->request->all();
        }

        $texto = trim((string) ($datos['mensaje'] ?? $datos['mensajeOffline'] ?? ''));
        $autoId = isset($datos['autoId']) ? (int) $datos['autoId'] : $request->query->getInt('auto', 0);
        $simulacionId = isset($datos['simulacionId']) && $datos['simulacionId'] !== ''
            ? (string) $datos['simulacionId']
            : null;
        $esOffline = !empty($datos['offline']);

        if ($texto === '') {
            if ($esJson) {
                return $this->json([
                    'estado' => 'ERROR',
                    'mensaje' => 'El mensaje no puede estar vacío.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $this->addFlash('error', 'El mensaje de la consulta no puede estar vacío.');

            return $this->redirectToRoute('app_chat');
        }

        $auto = $autoId > 0 ? $this->autoRepository->find($autoId) : null;
        if (!$auto instanceof Auto && $simulacionId !== null) {
            $contexto = $this->chatManager->obtenerContexto($request, $simulacionId);
            $auto = $contexto['auto'] ?? null;
        }

        $consulta = $this->chatManager->registrarMensaje(
            $request->getSession(),
            $usuario,
            $texto,
            $auto instanceof Auto ? $auto : null,
            $esOffline,
        );

        $respuestaTexto = $esOffline
            ? 'Su consulta offline fue registrada correctamente. Un administrador le responderá a la brevedad por correo.'
            : 'Hemos recibido su mensaje. El administrador está revisando el contexto de su vehículo o simulación.';

        if ($esJson) {
            return $this->json([
                'estado' => 'EXITO',
                'consultaId' => $consulta?->getId(),
                'respuesta' => $respuestaTexto,
            ]);
        }

        $this->addFlash('success', $respuestaTexto);

        if ($simulacionId !== null) {
            return $this->redirectToRoute('app_chat_simulacion', [
                'simulacionId' => $simulacionId,
                'offline' => $esOffline ? 1 : null,
            ]);
        }

        return $this->redirectToRoute('app_chat', array_filter([
            'auto' => $auto?->getId(),
            'offline' => $esOffline ? 1 : null,
        ]));
    }

    #[Route('/chat/{simulacionId}', name: 'app_chat_simulacion', methods: ['GET'])]
    public function chatConSimulacion(string $simulacionId, Request $request): Response
    {
        return $this->renderChat($request, $simulacionId);
    }

    private function renderChat(Request $request, ?string $simulacionId): Response
    {
        $usuario = $this->usuarioActual();
        $asesoresDisponibles = !$request->query->getBoolean('offline', false);
        $contexto = null;
        $consultas = [];

        if ($usuario !== null) {
            $contexto = $this->chatManager->obtenerContexto($request, $simulacionId);
            $autoContexto = $contexto['auto'] ?? null;
            $consultas = $this->chatManager->obtenerHistorialMensajes(
                $request->getSession(),
                $usuario,
                $autoContexto instanceof Auto ? $autoContexto : null,
            );
        }

        return $this->render('chat/chat.html.twig', [
            'usuario' => $usuario,
            'asesoresDisponibles' => $asesoresDisponibles,
            'contexto' => $contexto,
            'consultas' => $consultas,
            'simulacionId' => $simulacionId,
        ]);
    }

    private function usuarioActual(): ?Usuario
    {
        $usuario = $this->getUser();

        return $usuario instanceof Usuario ? $usuario : null;
    }
}

