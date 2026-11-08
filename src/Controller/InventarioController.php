<?php

namespace App\Controller;

use App\Entity\Auto;
use App\Entity\ImagenAuto;
use App\Entity\Modelo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/inventario')]
#[IsGranted('ROLE_ADMIN')]
class InventarioController extends AbstractController
{
    public const MAX_IMAGENES = 5;
    private const EXTENSIONES_PERMITIDAS = ['jpg', 'jpeg', 'png', 'webp'];

    #[Route('', name: 'inventario_index', methods: ['GET'])]
    public function index(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $busqueda = $request->query->get('q');
        $estado = $request->query->get('estado');

        if ($estado !== null && !in_array($estado, Auto::ESTADOS, true)) {
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

                if ($this->procesarImagenes($request, $auto, $em)) {
                    $em->flush();
                    $this->addFlash('success', 'El vehículo se registró correctamente.');

                    return $this->redirectToRoute('inventario_index');
                }
            }
        }

        return $this->render('inventario/nuevo.html.twig', [
            'auto' => $auto,
            'modelos' => $em->getRepository(Modelo::class)->findBy([], ['nombre' => 'ASC']),
            'estados' => Auto::ESTADOS,
        ]);
    }

    #[Route('/{id}/editar', name: 'inventario_editar', methods: ['GET', 'POST'])]
    public function editar(
        Auto $auto,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        if ($request->isMethod('POST')) {
            if ($this->cargarDatosAuto($request, $auto, $em) && $this->procesarImagenes($request, $auto, $em)) {
                $em->flush();

                $this->addFlash('success', 'El vehículo se actualizó correctamente.');

                return $this->redirectToRoute('inventario_index');
            }
        }

        return $this->render('inventario/editar.html.twig', [
            'auto' => $auto,
            'modelos' => $em->getRepository(Modelo::class)->findBy([], ['nombre' => 'ASC']),
            'estados' => Auto::ESTADOS,
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

        $auto->setEstado(Auto::ESTADO_INACTIVO);
        $em->flush();

        $this->addFlash('success', 'El vehículo fue dado de baja del inventario.');

        return $this->redirectToRoute('inventario_index');
    }

    private function cargarDatosAuto(
        Request $request,
        Auto $auto,
        EntityManagerInterface $em
    ): bool {
        if ($request->request->has('_token') && !$this->isCsrfTokenValid('inventario_auto', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token inválido.');
        }

        $modeloId = $request->request->get('modelo');
        $anio = $request->request->get('anio');
        $kilometraje = $request->request->get('kilometraje');
        $precio = $request->request->get('precio');
        $estado = strtolower(trim((string) $request->request->get('estado')));
        $descripcion = $request->request->get('descripcion');

        if (
            !is_numeric($modeloId) ||
            !is_numeric($anio) ||
            !is_numeric($kilometraje) ||
            !is_numeric($precio) ||
            !in_array($estado, Auto::ESTADOS, true)
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

    private function procesarImagenes(
        Request $request,
        Auto $auto,
        EntityManagerInterface $em
    ): bool {
        // 1. Eliminar imágenes marcadas para quitar
        $idsEliminar = $request->request->all('eliminar_imagenes');
        if ($idsEliminar !== []) {
            $idsEliminarInt = array_map('intval', $idsEliminar);
            foreach ($auto->getImagenes()->toArray() as $imagenExistente) {
                if (in_array($imagenExistente->getId(), $idsEliminarInt, true)) {
                    $auto->removeImagen($imagenExistente);
                    $em->remove($imagenExistente);
                }
            }
        }

        // 2. Procesar nuevos archivos subidos (máximo 5 en total)
        /** @var list<UploadedFile|null> $archivos */
        $archivos = array_values(array_filter(
            (array) $request->files->get('imagenes', []),
            static fn (mixed $archivo): bool => $archivo instanceof UploadedFile && $archivo->getError() !== \UPLOAD_ERR_NO_FILE
        ));

        if ($archivos === []) {
            return true;
        }

        $totalFinal = $auto->getImagenes()->count() + count($archivos);
        if ($totalFinal > self::MAX_IMAGENES) {
            $this->addFlash('error', sprintf('Podés cargar hasta un máximo de %d imágenes por vehículo.', self::MAX_IMAGENES));
            return false;
        }

        $directorioDestino = $this->getParameter('kernel.project_dir') . '/public/assets/img';
        if (!is_dir($directorioDestino)) {
            mkdir($directorioDestino, 0775, true);
        }

        $indice = $auto->getImagenes()->count() + 1;
        foreach ($archivos as $archivo) {
            if (!$archivo->isValid()) {
                $this->addFlash('error', 'Uno de los archivos de imagen no se pudo subir correctamente.');
                return false;
            }

            $extension = strtolower($archivo->getClientOriginalExtension() ?: ($archivo->guessExtension() ?? 'jpg'));
            if (!in_array($extension, self::EXTENSIONES_PERMITIDAS, true)) {
                $this->addFlash('error', 'Formato de imagen no válido. Usá JPG, JPEG, PNG o WEBP.');
                return false;
            }

            $nombreArchivo = sprintf(
                'auto%d_%d_%s.%s',
                $auto->getId() ?? 0,
                $indice,
                bin2hex(random_bytes(3)),
                $extension
            );

            $archivo->move($directorioDestino, $nombreArchivo);

            $imagenAuto = (new ImagenAuto())
                ->setRuta('assets/img/' . $nombreArchivo)
                ->setAuto($auto);

            $auto->addImagen($imagenAuto);
            $em->persist($imagenAuto);
            ++$indice;
        }

        return true;
    }
}
