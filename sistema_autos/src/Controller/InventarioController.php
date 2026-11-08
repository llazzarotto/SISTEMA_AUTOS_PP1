<?php

namespace App\Controller;

use App\Entity\Auto;
use App\Entity\Modelo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/inventario')]
#[IsGranted('ROLE_ADMIN')]
class InventarioController extends AbstractController
{
    private const ESTADOS = [
        'disponible',
        'reservado',
        'vendido',
        'inactivo',
    ];

    #[Route('', name: 'inventario_index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $busqueda = $request->query->get('q');
        $estado = $request->query->get('estado');

        if ($estado !== null && !in_array($estado, self::ESTADOS, true)) {
            $estado = null;
        }

        $autos = $em->getRepository(Auto::class)
            ->buscarInventario($busqueda, $estado);

        return $this->render('inventario/index.html.twig', [
            'autos' => $autos,
            'busqueda' => $busqueda,
            'estadoSeleccionado' => $estado,
        ]);
    }

    #[Route('/nuevo', name: 'inventario_nuevo', methods: ['GET', 'POST'])]
    public function nuevo(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $auto = new Auto();

        if ($request->isMethod('POST')) {
            if ($this->cargarDatosAuto($request, $auto, $em)) {
                $em->persist($auto);
                $em->flush();

                $this->addFlash('success', 'El vehículo se registró correctamente.');

                return $this->redirectToRoute('inventario_index');
            }
        }

        return $this->render('inventario/nuevo.html.twig', [
            'auto' => $auto,
            'modelos' => $em->getRepository(Modelo::class)->findBy([], ['nombre' => 'ASC']),
            'estados' => self::ESTADOS,
        ]);
    }

    #[Route('/{id}/editar', name: 'inventario_editar', methods: ['GET', 'POST'])]
    public function editar(
        Auto $auto,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            if ($this->cargarDatosAuto($request, $auto, $em)) {
                $em->flush();

                $this->addFlash('success', 'El vehículo se actualizó correctamente.');

                return $this->redirectToRoute('inventario_index');
            }
        }

        return $this->render('inventario/editar.html.twig', [
            'auto' => $auto,
            'modelos' => $em->getRepository(Modelo::class)->findBy([], ['nombre' => 'ASC']),
            'estados' => self::ESTADOS,
        ]);
    }

    #[Route('/{id}/eliminar', name: 'inventario_eliminar', methods: ['POST'])]
    public function eliminar(
        Auto $auto,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid(
            'eliminar_auto_' . $auto->getId(),
            $request->request->get('_token')
        )) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        $auto->setEstado('inactivo');
        $em->flush();

        $this->addFlash('success', 'El vehículo fue dado de baja del inventario.');

        return $this->redirectToRoute('inventario_index');
    }

    private function cargarDatosAuto(
        Request $request,
        Auto $auto,
        EntityManagerInterface $em
    ): bool {
        $modeloId = $request->request->get('modelo');
        $anio = $request->request->get('anio');
        $kilometraje = $request->request->get('kilometraje');
        $precio = $request->request->get('precio');
        $estado = $request->request->get('estado');
        $descripcion = $request->request->get('descripcion');

        if (
            !is_numeric($modeloId) ||
            !is_numeric($anio) ||
            !is_numeric($kilometraje) ||
            !is_numeric($precio) ||
            !in_array($estado, self::ESTADOS, true)
        ) {
            $this->addFlash('danger', 'Completá correctamente todos los campos.');
            return false;
        }

        $modelo = $em->getRepository(Modelo::class)->find((int) $modeloId);

        if (!$modelo) {
            $this->addFlash('danger', 'El modelo seleccionado no existe.');
            return false;
        }

        if (
            (int) $anio < 1900 ||
            (int) $anio > ((int) date('Y') + 1) ||
            (int) $kilometraje < 0 ||
            (int) $precio <= 0
        ) {
            $this->addFlash('danger', 'Revisá el año, kilometraje y precio ingresados.');
            return false;
        }

        $auto->setModelo($modelo);
        $auto->setAnio((int) $anio);
        $auto->setKilometraje((int) $kilometraje);
        $auto->setPrecio((int) $precio);
        $auto->setEstado($estado);
        $auto->setDescripcion($descripcion !== null ? trim($descripcion) : null);

        return true;
    }
}
