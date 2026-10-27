<?php

namespace App\Controller;

use App\Entity\Auto;
use App\Entity\Favorito;
use App\Entity\Usuario;
use App\Repository\AutoRepository;
use App\Repository\FavoritoRepository;
use App\Repository\MarcaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AutoController extends AbstractController
{
    private const AUTOS_POR_PAGINA = 9;
    private const MAX_AUTOS_COMPARADOR = 2;
    private const CLAVE_COMPARADOR = 'comparador_autos';

    #[Route('/auto', name: 'app_auto_index', methods: ['GET'])]
    public function index(
        Request $request,
        AutoRepository $autoRepository,
        MarcaRepository $marcaRepository,
    ): Response {
        $filtros = [
            'q' => trim($request->query->getString('q')),
            'precioMin' => $this->enteroPositivoONulo($request->query->get('precioMin')),
            'precioMax' => $this->enteroPositivoONulo($request->query->get('precioMax')),
            'kilometrajeMax' => $this->enteroPositivoONulo($request->query->get('kilometrajeMax')),
            'anioDesde' => $this->anioONulo($request->query->get('anioDesde')),
            'anioHasta' => $this->anioONulo($request->query->get('anioHasta')),
            'marca' => $this->enteroPositivoONulo($request->query->get('marca')),
            'orden' => $request->query->getString('orden', 'recientes'),
        ];

        if ($filtros['precioMin'] !== null && $filtros['precioMax'] !== null && $filtros['precioMin'] > $filtros['precioMax']) {
            [$filtros['precioMin'], $filtros['precioMax']] = [$filtros['precioMax'], $filtros['precioMin']];
        }
        if ($filtros['anioDesde'] !== null && $filtros['anioHasta'] !== null && $filtros['anioDesde'] > $filtros['anioHasta']) {
            [$filtros['anioDesde'], $filtros['anioHasta']] = [$filtros['anioHasta'], $filtros['anioDesde']];
        }

        $paginaSolicitada = max(1, $request->query->getInt('pagina', 1));
        $resultado = $autoRepository->buscarDisponibles($filtros, $paginaSolicitada, self::AUTOS_POR_PAGINA);
        $total = count($resultado);
        $totalPaginas = max(1, (int) ceil($total / self::AUTOS_POR_PAGINA));
        $pagina = min($paginaSolicitada, $totalPaginas);

        if ($pagina !== $paginaSolicitada) {
            $resultado = $autoRepository->buscarDisponibles($filtros, $pagina, self::AUTOS_POR_PAGINA);
        }

        return $this->render('auto/index.html.twig', [
            'autos' => $resultado,
            'total' => $total,
            'pagina' => $pagina,
            'totalPaginas' => $totalPaginas,
            'filtros' => $filtros,
            'marcas' => $marcaRepository->findBy([], ['nombre' => 'ASC']),
            'comparadorIds' => $this->idsComparador($request),
        ]);
    }

    #[Route('/auto/comparar', name: 'app_auto_compare', methods: ['GET'])]
    public function comparar(Request $request, AutoRepository $autoRepository): Response
    {
        $ids = $this->idsComparador($request);
        $autos = $autoRepository->buscarDisponiblesPorIds($ids);

        if (count($autos) !== count($ids)) {
            $idsDisponibles = array_map(static fn (Auto $auto): int => (int) $auto->getId(), $autos);
            $request->getSession()->set(self::CLAVE_COMPARADOR, $idsDisponibles);
            $this->addFlash('error', 'Uno de los vehículos seleccionados ya no está disponible.');
        }

        return $this->render('auto/compare.html.twig', ['autos' => $autos]);
    }

    #[Route('/auto/{id}/comparar', name: 'app_auto_compare_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function alternarComparador(Request $request, Auto $auto): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('comparar_'.$auto->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('El token de seguridad no es válido.');
        }

        if ($auto->getEstado() !== 'disponible') {
            $this->addFlash('error', 'El vehículo seleccionado ya no está disponible.');

            return $this->redirectToRoute('app_auto_index');
        }

        $ids = $this->idsComparador($request);
        $id = (int) $auto->getId();
        if (in_array($id, $ids, true)) {
            $ids = array_values(array_diff($ids, [$id]));
            $this->addFlash('success', 'Vehículo quitado de la comparación.');
        } elseif (count($ids) >= self::MAX_AUTOS_COMPARADOR) {
            $this->addFlash('error', 'Sólo podés comparar hasta dos vehículos.');
        } else {
            $ids[] = $id;
            $this->addFlash('success', 'Vehículo agregado a la comparación.');
        }

        $request->getSession()->set(self::CLAVE_COMPARADOR, $ids);
        $destino = $request->request->getString('destino');

        return $destino === 'comparador'
            ? $this->redirectToRoute('app_auto_compare')
            : $this->redirectToRoute('app_auto_show', ['id' => $id]);
    }

    #[Route('/auto/comparar/limpiar', name: 'app_auto_compare_clear', methods: ['POST'])]
    public function limpiarComparador(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('limpiar_comparador', $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('El token de seguridad no es válido.');
        }

        $request->getSession()->remove(self::CLAVE_COMPARADOR);
        $this->addFlash('success', 'Se limpió la comparación.');

        return $this->redirectToRoute('app_auto_compare');
    }

    #[Route('/auto/{id}', name: 'app_auto_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Auto $auto, FavoritoRepository $favoritoRepository): Response
    {
        if ($auto->getEstado() !== 'disponible') {
            $this->addFlash('error', 'El vehículo seleccionado ya no está disponible.');

            return $this->redirectToRoute('app_auto_index');
        }

        $usuario = $this->usuarioActual();
        $esFavorito = $usuario !== null && $favoritoRepository->findOneBy([
            'usuario' => $usuario,
            'auto' => $auto,
        ]) !== null;

        return $this->render('auto/show.html.twig', [
            'auto' => $auto,
            'esFavorito' => $esFavorito,
        ]);
    }

    #[Route('/auto/{id}/favorito', name: 'app_auto_favorite_toggle', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function alternarFavorito(
        Request $request,
        Auto $auto,
        FavoritoRepository $favoritoRepository,
        EntityManagerInterface $entityManager,
    ): RedirectResponse {
        if (!$this->isCsrfTokenValid('favorito_'.$auto->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('El token de seguridad no es válido.');
        }

        $usuario = $this->usuarioActual();
        if ($usuario === null) {
            $this->addFlash('error', 'Debés iniciar sesión para guardar favoritos.');

            return $this->redirect($request->getBasePath().'/login?redirect='.urlencode($this->generateUrl('app_auto_show', ['id' => $auto->getId()])));
        }

        if ($auto->getEstado() !== 'disponible') {
            $this->addFlash('error', 'El vehículo seleccionado ya no está disponible.');

            return $this->redirectToRoute('app_auto_index');
        }

        $favorito = $favoritoRepository->findOneBy(['usuario' => $usuario, 'auto' => $auto]);
        if ($favorito instanceof Favorito) {
            $entityManager->remove($favorito);
            $mensaje = 'Vehículo quitado de favoritos.';
        } else {
            $favorito = (new Favorito())->setUsuario($usuario)->setAuto($auto);
            $entityManager->persist($favorito);
            $mensaje = 'Vehículo agregado a favoritos.';
        }
        $entityManager->flush();
        $this->addFlash('success', $mensaje);

        return $this->redirectToRoute('app_auto_show', ['id' => $auto->getId()]);
    }

    #[Route('/favoritos', name: 'app_favorite_index', methods: ['GET'])]
    public function favoritos(Request $request, FavoritoRepository $favoritoRepository): Response
    {
        $usuario = $this->usuarioActual();
        if ($usuario === null) {
            $this->addFlash('error', 'Debés iniciar sesión para ver tus favoritos.');

            return $this->redirect($request->getBasePath().'/login?redirect='.urlencode($this->generateUrl('app_favorite_index')));
        }

        return $this->render('auto/favorites.html.twig', [
            'favoritos' => $favoritoRepository->buscarPorUsuario($usuario),
        ]);
    }

    /** @return list<int> */
    private function idsComparador(Request $request): array
    {
        $ids = $request->getSession()->get(self::CLAVE_COMPARADOR, []);
        if (!is_array($ids)) {
            return [];
        }

        return array_slice(array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        ))), 0, self::MAX_AUTOS_COMPARADOR);
    }

    private function usuarioActual(): ?Usuario
    {
        $usuario = $this->getUser();

        return $usuario instanceof Usuario ? $usuario : null;
    }

    private function enteroPositivoONulo(mixed $valor): ?int
    {
        if ($valor === null || $valor === '' || filter_var($valor, FILTER_VALIDATE_INT) === false) {
            return null;
        }

        $entero = (int) $valor;

        return $entero >= 0 ? $entero : null;
    }

    private function anioONulo(mixed $valor): ?int
    {
        $anio = $this->enteroPositivoONulo($valor);

        return $anio !== null && $anio >= 1886 && $anio <= ((int) date('Y') + 1) ? $anio : null;
    }
}
