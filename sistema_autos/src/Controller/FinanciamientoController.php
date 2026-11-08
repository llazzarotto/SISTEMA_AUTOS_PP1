<?php

namespace App\Controller;

use App\Entity\Auto;
use App\Manager\FinanciamientoManager;
use App\Repository\AutoRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FinanciamientoController extends AbstractController
{
    public function __construct(
        private AutoRepository $autoRepository,
        private FinanciamientoManager $financiamientoManager,
    ) {
    }

    #[Route('/financiacion', name: 'app_financiacion', methods: ['GET'])]
    #[Route('/financiamiento', name: 'app_financiamiento_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $autosDisponibles = $this->autoRepository->obtenerDisponiblesConModeloYMarca('ASC');
        $autoId = $request->query->getInt('auto', 0);

        $auto = null;
        if ($autoId > 0) {
            $auto = $this->buscarAutoPorId($autoId, $autosDisponibles);
        } elseif ($autosDisponibles !== []) {
            $auto = $autosDisponibles[0];
        }

        return $this->renderSimulador($auto, $autosDisponibles, $autoId > 0);
    }

    #[Route('/financiamiento/{id}', name: 'app_financiamiento_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id): Response
    {
        $autosDisponibles = $this->autoRepository->obtenerDisponiblesConModeloYMarca('ASC');
        $auto = $this->buscarAutoPorId($id, $autosDisponibles);

        return $this->renderSimulador($auto, $autosDisponibles, true);
    }

    #[Route('/financiamiento/{id}/resultado', name: 'app_financiamiento_resultado', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resultado(int $id, Request $request): JsonResponse
    {
        $auto = $this->autoRepository->find($id);

        if (!$auto instanceof Auto || !$auto->estaDisponible()) {
            return $this->json([
                'estado' => 'ERROR',
                'mensaje' => 'El vehículo seleccionado no existe o ya no está disponible.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $datos = json_decode($request->getContent(), true);
        if (!is_array($datos)) {
            $datos = $request->request->all();
        }

        $montoEntrega = isset($datos['montoEntrega']) && $datos['montoEntrega'] !== ''
            ? (float) $datos['montoEntrega']
            : 0.0;
        $cantidadCuotas = isset($datos['cantidadCuotas'])
            ? (int) $datos['cantidadCuotas']
            : 0;

        $calculo = $this->financiamientoManager->calcularSimulacion($auto, $montoEntrega, $cantidadCuotas);
        if (!$calculo['valido']) {
            return $this->json([
                'estado' => 'ERROR',
                'errores' => $calculo['errores'],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $simulacion = $this->financiamientoManager->guardarSimulacion(
            $request->getSession(),
            $auto,
            $montoEntrega,
            $cantidadCuotas,
            $calculo['capitalFinanciar'],
            $calculo['valorCuota'],
            $calculo['tasaInteres'],
        );

        return $this->json([
            'estado' => FinanciamientoManager::SIMULACION_EXITOSA,
            'simulacionId' => $simulacion['id'],
            'simulacion' => $simulacion,
        ]);
    }

    /**
     * @param list<Auto> $autosDisponibles
     */
    private function buscarAutoPorId(int $id, array $autosDisponibles): ?Auto
    {
        foreach ($autosDisponibles as $item) {
            if ($item->getId() === $id) {
                return $item;
            }
        }

        return $this->autoRepository->find($id);
    }

    /**
     * @param list<Auto> $autosDisponibles
     */
    private function renderSimulador(?Auto $auto, array $autosDisponibles, bool $autoSolicitado): Response
    {
        $parametros = $this->financiamientoManager->obtenerParametros();
        $errorSistema = false;
        $mensajeError = null;

        if ($parametros['cuotasDisponibles'] === []) {
            $errorSistema = true;
            $mensajeError = 'El sistema no pudo obtener los parámetros de financiación.';
        } elseif (!$auto instanceof Auto || !$auto->estaDisponible()) {
            $errorSistema = true;
            $mensajeError = $autoSolicitado
                ? 'El vehículo seleccionado no existe o ya no está disponible para simular financiación.'
                : 'No hay vehículos disponibles en el catálogo para simular financiación.';
        }

        return $this->render('financiamiento/calculadora.html.twig', [
            'errorSistema' => $errorSistema,
            'mensajeError' => $mensajeError,
            'auto' => $auto,
            'autosDisponibles' => $autosDisponibles,
            'parametros' => $parametros,
        ]);
    }
}
