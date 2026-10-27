<?php

namespace App\Controller;

use App\Entity\Cita;
use App\Entity\Usuario;
use App\Repository\AutoRepository;
use App\Repository\CitaRepository;
use App\Service\DisponibilidadCitaService;
use App\Service\ReservaCitaService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/reservas', name: 'app_reservas_')]
final class ReservaController extends AbstractController
{
    private const BORRADOR_SESSION = 'cu05_reserva_borrador';

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(CitaRepository $citas, DisponibilidadCitaService $disponibilidad): Response
    {
        $usuario = $this->requireCliente();
        $ahora = $disponibilidad->ahora();

        return $this->render('reserva/index.html.twig', [
            'proximas' => $citas->buscarProximasDelCliente($usuario, $ahora),
            'historial' => $citas->buscarHistorialDelCliente($usuario, $ahora),
        ]);
    }

    #[Route('/nueva', name: 'nueva', methods: ['GET', 'POST'])]
    public function nueva(
        Request $request,
        AutoRepository $autos,
        DisponibilidadCitaService $disponibilidad,
    ): Response {
        $this->requireCliente();
        $borrador = $request->getSession()->get(self::BORRADOR_SESSION, []);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reserva_datos', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('El formulario vencio. Volve a intentarlo.');
            }

            $servicio = trim((string) $request->request->get('servicio'));
            $fecha = trim((string) $request->request->get('fecha'));
            $autoId = $request->request->get('auto');
            $observaciones = trim((string) $request->request->get('observaciones'));
            $errores = [];

            if (!in_array($servicio, Cita::SERVICIOS, true)) {
                $errores[] = 'Selecciona un tipo de servicio valido.';
            }

            try {
                $disponibilidad->crearFechaHora($fecha, DisponibilidadCitaService::HORA_APERTURA);
            } catch (\DomainException) {
                $errores[] = 'Selecciona una fecha valida.';
            }

            $auto = null;
            if ($autoId !== null && $autoId !== '') {
                $auto = ctype_digit((string) $autoId) ? $autos->find((int) $autoId) : null;
                if ($auto === null) {
                    $errores[] = 'El vehiculo seleccionado no existe.';
                }
            }

            $borrador = [
                'servicio' => $servicio,
                'fecha' => $fecha,
                'autoId' => $auto?->getId(),
                'observaciones' => mb_substr($observaciones, 0, 1000),
            ];

            if ($errores === []) {
                $request->getSession()->set(self::BORRADOR_SESSION, $borrador);

                return $this->redirectToRoute('app_reservas_horario');
            }

            foreach ($errores as $error) {
                $this->addFlash('error', $error);
            }
        } else {
            if ($request->query->has('auto') && ctype_digit((string) $request->query->get('auto'))) {
                $borrador['autoId'] = (int) $request->query->get('auto');
            }
            if ($request->query->has('fecha')) {
                $borrador['fecha'] = (string) $request->query->get('fecha');
            }
        }

        return $this->render('reserva/nueva.html.twig', [
            'servicios' => Cita::SERVICIOS,
            'autos' => $autos->findBy(['estado' => 'disponible'], ['id' => 'DESC']),
            'borrador' => $borrador,
            'fechaMinima' => $disponibilidad->ahora()->modify('+24 hours')->format('Y-m-d'),
        ]);
    }

    #[Route('/nueva/horario', name: 'horario', methods: ['GET', 'POST'])]
    public function horario(Request $request, DisponibilidadCitaService $disponibilidad): Response
    {
        $this->requireCliente();
        $borrador = $this->obtenerBorrador($request);
        if ($borrador instanceof RedirectResponse) {
            return $borrador;
        }

        $fecha = $disponibilidad->crearFechaHora($borrador['fecha'], '00:00');
        $horarios = $disponibilidad->obtenerHorariosDisponibles($fecha);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reserva_horario', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('El formulario vencio. Volve a intentarlo.');
            }

            $hora = trim((string) $request->request->get('hora'));
            try {
                $fechaHora = $disponibilidad->crearFechaHora($borrador['fecha'], $hora);
            } catch (\DomainException) {
                $fechaHora = null;
            }

            if ($fechaHora === null || !$disponibilidad->esHorarioDisponible($fechaHora)) {
                $this->addFlash('error', 'Ese turno acaba de dejar de estar disponible. Selecciona otro.');
            } else {
                $borrador['hora'] = $hora;
                $request->getSession()->set(self::BORRADOR_SESSION, $borrador);

                return $this->redirectToRoute('app_reservas_confirmar');
            }
        }

        return $this->render('reserva/horario.html.twig', [
            'fecha' => $fecha,
            'horarios' => $horarios,
            'sugerencias' => $horarios === [] ? $disponibilidad->sugerirFechasDisponibles($fecha) : [],
        ]);
    }

    #[Route('/nueva/confirmar', name: 'confirmar', methods: ['GET', 'POST'])]
    public function confirmar(
        Request $request,
        AutoRepository $autos,
        DisponibilidadCitaService $disponibilidad,
        ReservaCitaService $reservas,
    ): Response {
        $usuario = $this->requireCliente();
        $borrador = $this->obtenerBorrador($request, true);
        if ($borrador instanceof RedirectResponse) {
            return $borrador;
        }

        $auto = isset($borrador['autoId']) ? $autos->find($borrador['autoId']) : null;
        $fechaHora = $disponibilidad->crearFechaHora($borrador['fecha'], $borrador['hora']);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('confirmar_reserva', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('El formulario vencio. Volve a intentarlo.');
            }

            try {
                $cita = $reservas->reservar(
                    $usuario,
                    $borrador['servicio'],
                    $auto,
                    $fechaHora,
                    $borrador['observaciones'] ?: null,
                );
                $request->getSession()->remove(self::BORRADOR_SESSION);

                return $this->redirectToRoute('app_reservas_detalle', ['id' => $cita->getId(), 'registrada' => 1]);
            } catch (UniqueConstraintViolationException|\DomainException $error) {
                $this->addFlash('error', $error instanceof UniqueConstraintViolationException
                    ? 'Ese horario acaba de ser reservado por otra persona. Selecciona otro.'
                    : $error->getMessage());

                return $this->redirectToRoute('app_reservas_horario');
            }
        }

        return $this->render('reserva/confirmar.html.twig', [
            'borrador' => $borrador,
            'auto' => $auto,
            'fechaHora' => $fechaHora,
            'usuario' => $usuario,
        ]);
    }

    #[Route('/horarios', name: 'horarios', methods: ['GET'])]
    public function horarios(
        Request $request,
        CitaRepository $citas,
        DisponibilidadCitaService $disponibilidad,
    ): JsonResponse {
        $usuario = $this->requireCliente();

        try {
            $fecha = $disponibilidad->crearFechaHora((string) $request->query->get('fecha'), '00:00');
            $citaIgnorada = null;
            if (ctype_digit((string) $request->query->get('cita'))) {
                $citaIgnorada = $citas->find((int) $request->query->get('cita'));
                if (!$citaIgnorada instanceof Cita || $citaIgnorada->getUsuario() !== $usuario) {
                    throw $this->createNotFoundException();
                }
            }

            $horarios = $disponibilidad->obtenerHorariosDisponibles($fecha, $citaIgnorada);

            return $this->json([
                'fecha' => $fecha->format('Y-m-d'),
                'horarios' => array_map(
                    static fn (\DateTimeImmutable $turno): array => [
                        'valor' => $turno->format('H:i'),
                        'etiqueta' => $turno->format('H:i') . ' hs',
                    ],
                    $horarios,
                ),
            ]);
        } catch (\DomainException) {
            return $this->json(['error' => 'Fecha invalida.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/{id}/reprogramar', name: 'reprogramar', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function reprogramar(
        Cita $cita,
        Request $request,
        DisponibilidadCitaService $disponibilidad,
        ReservaCitaService $reservas,
    ): Response {
        $this->comprobarPropietario($cita);

        if (!$reservas->puedeModificarse($cita)) {
            $this->addFlash('error', 'La cita solo puede reprogramarse con al menos 24 horas de anticipacion.');

            return $this->redirectToRoute('app_reservas_detalle', ['id' => $cita->getId()]);
        }

        $fechaSeleccionada = (string) ($request->request->get('fecha') ?: $request->query->get('fecha', $cita->getFechaHora()?->format('Y-m-d')));

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('reprogramar_'.$cita->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('El formulario vencio. Volve a intentarlo.');
            }

            try {
                $nuevaFechaHora = $disponibilidad->crearFechaHora($fechaSeleccionada, (string) $request->request->get('hora'));
                $reservas->reprogramar($cita, $nuevaFechaHora);
                $this->addFlash('success', sprintf('La cita fue reprogramada. La actualizacion se mostraria para %s.', $cita->getUsuario()?->getEmail()));

                return $this->redirectToRoute('app_reservas_detalle', ['id' => $cita->getId()]);
            } catch (UniqueConstraintViolationException|\DomainException $error) {
                $this->addFlash('error', $error instanceof UniqueConstraintViolationException
                    ? 'Ese horario acaba de ser reservado por otra persona.'
                    : $error->getMessage());
            }
        }

        try {
            $fecha = $disponibilidad->crearFechaHora($fechaSeleccionada, '00:00');
        } catch (\DomainException) {
            $fecha = $cita->getFechaHora()->setTime(0, 0);
        }

        return $this->render('reserva/reprogramar.html.twig', [
            'cita' => $cita,
            'fecha' => $fecha,
            'horarios' => $disponibilidad->obtenerHorariosDisponibles($fecha, $cita),
            'fechaMinima' => $disponibilidad->ahora()->modify('+24 hours')->format('Y-m-d'),
        ]);
    }

    #[Route('/{id}/cancelar', name: 'cancelar', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    public function cancelar(Cita $cita, Request $request, ReservaCitaService $reservas): Response
    {
        $this->comprobarPropietario($cita);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('cancelar_'.$cita->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('El formulario vencio. Volve a intentarlo.');
            }

            try {
                $reservas->cancelar($cita);
                $this->addFlash('success', sprintf('La cita fue cancelada. La confirmacion se mostraria para %s.', $cita->getUsuario()?->getEmail()));

                return $this->redirectToRoute('app_reservas_detalle', ['id' => $cita->getId()]);
            } catch (\DomainException $error) {
                $this->addFlash('error', $error->getMessage());
            }
        }

        return $this->render('reserva/cancelar.html.twig', ['cita' => $cita]);
    }

    #[Route('/{id}', name: 'detalle', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function detalle(Cita $cita, Request $request, ReservaCitaService $reservas): Response
    {
        $this->comprobarPropietario($cita);

        return $this->render('reserva/detalle.html.twig', [
            'cita' => $cita,
            'registrada' => $request->query->getBoolean('registrada'),
            'puedeModificar' => $reservas->puedeModificarse($cita),
        ]);
    }

    private function requireCliente(): Usuario
    {
        $usuario = $this->getUser();
        if (!$usuario instanceof Usuario) {
            throw $this->createAccessDeniedException(
                'CU05 requiere un Usuario autenticado. Quedara conectado automaticamente cuando CU01 integre Usuario con Symfony Security.',
            );
        }

        return $usuario;
    }

    private function comprobarPropietario(Cita $cita): void
    {
        if ($cita->getUsuario() !== $this->requireCliente()) {
            throw $this->createNotFoundException();
        }
    }

    /** @return array<string, mixed>|RedirectResponse */
    private function obtenerBorrador(Request $request, bool $requiereHorario = false): array|RedirectResponse
    {
        $borrador = $request->getSession()->get(self::BORRADOR_SESSION);
        if (!is_array($borrador) || !isset($borrador['servicio'], $borrador['fecha'])) {
            $this->addFlash('error', 'Comenza seleccionando el servicio y la fecha.');

            return $this->redirectToRoute('app_reservas_nueva');
        }

        if ($requiereHorario && !isset($borrador['hora'])) {
            return $this->redirectToRoute('app_reservas_horario');
        }

        return $borrador;
    }
}
