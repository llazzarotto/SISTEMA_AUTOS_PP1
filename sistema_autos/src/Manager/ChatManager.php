<?php

namespace App\Manager;

use App\Entity\Auto;
use App\Entity\Consulta;
use App\Entity\Usuario;
use App\Repository\AutoRepository;
use App\Repository\ConsultaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class ChatManager
{
    public const ROL_CLIENTE = 'ROL_CLIENTE';
    public const ROL_ADMIN = 'ROL_ADMIN';
    public const ESTADO_CONECTADO = 'CONECTADO';
    private const CLAVE_MENSAJES_SESION = 'chat_mensajes_sesion';
    private const SEPARADOR_RESPUESTA_ADMIN = "\n[ADMIN]: ";

    public function __construct(
        private AutoRepository $autoRepository,
        private ConsultaRepository $consultaRepository,
        private FinanciamientoManager $financiamientoManager,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     auto: Auto|null,
     *     simulacion: array<string, mixed>|null
     * }|null
     */
    public function obtenerContexto(Request $request, ?string $simulacionId = null): ?array
    {
        $session = $request->getSession();
        $idSimulacion = $simulacionId ?? ($request->query->getString('simulacion') ?: null);
        $simulacion = null;

        if ($idSimulacion !== null && $idSimulacion !== '') {
            $simulacion = $this->financiamientoManager->obtenerSimulacion($session, $idSimulacion);
        }

        if ($simulacion === null && $request->query->has('cuotas') && $request->query->has('capital')) {
            $cuotas = $request->query->getInt('cuotas', 0);
            $capital = (float) $request->query->get('capital', 0);
            if ($cuotas > 0 && $capital >= 0) {
                $simulacion = [
                    'id' => $idSimulacion ?? 'temp',
                    'autoId' => $request->query->getInt('auto', 0),
                    'cantidadCuotas' => $cuotas,
                    'capitalFinanciar' => $capital,
                    'valorCuota' => (float) $request->query->get('cuota', 0),
                ];
            }
        }

        $auto = null;
        $autoId = $request->query->getInt('auto', 0);
        if ($autoId <= 0 && is_array($simulacion) && isset($simulacion['autoId'])) {
            $autoId = (int) $simulacion['autoId'];
        }

        if ($autoId > 0) {
            $autoCandidato = $this->autoRepository->find($autoId);
            if ($autoCandidato instanceof Auto && $autoCandidato->estaDisponible()) {
                $auto = $autoCandidato;
            }
        }

        if ($auto === null && $simulacion === null) {
            return null;
        }

        return [
            'auto' => $auto,
            'simulacion' => $simulacion,
        ];
    }

    /**
     * @return list<array{texto: string, rol: string, estado: string}>
     */
    public function obtenerHistorialMensajes(SessionInterface $session, Usuario $usuario, ?Auto $auto = null): array
    {
        $criterios = ['usuario' => $usuario];
        if ($auto instanceof Auto) {
            $criterios['auto'] = $auto;
        }

        $consultas = $this->consultaRepository->findBy($criterios, ['fechaCreacion' => 'ASC', 'id' => 'ASC']);
        $mensajes = [];

        foreach ($consultas as $consulta) {
            $partes = explode(self::SEPARADOR_RESPUESTA_ADMIN, (string) $consulta->getMensaje());
            $mensajeCliente = trim((string) array_shift($partes));
            if ($mensajeCliente !== '') {
                $mensajes[] = [
                    'texto' => $mensajeCliente,
                    'rol' => self::ROL_CLIENTE,
                    'estado' => (string) $consulta->getEstado(),
                ];
            }

            foreach ($partes as $respuestaAdmin) {
                $respuestaLimpia = trim($respuestaAdmin);
                if ($respuestaLimpia !== '') {
                    $mensajes[] = [
                        'texto' => $respuestaLimpia,
                        'rol' => self::ROL_ADMIN,
                        'estado' => 'respondida',
                    ];
                }
            }
        }

        $enSesion = $session->get(self::CLAVE_MENSAJES_SESION, []);
        if (is_array($enSesion)) {
            $usuarioId = (int) $usuario->getId();
            foreach ($enSesion as $item) {
                if (is_array($item) && ($item['usuarioId'] ?? null) === $usuarioId) {
                    $mensajes[] = [
                        'texto' => (string) ($item['texto'] ?? ''),
                        'rol' => (string) ($item['rol'] ?? self::ROL_CLIENTE),
                        'estado' => (string) ($item['estado'] ?? 'pendiente'),
                    ];
                }
            }
        }

        return $mensajes;
    }

    /**
     * @return list<Consulta>
     */
    public function obtenerConsultasParaAdmin(): array
    {
        return $this->consultaRepository->createQueryBuilder('c')
            ->addSelect('usuario', 'auto', 'modelo', 'marca')
            ->innerJoin('c.usuario', 'usuario')
            ->leftJoin('c.auto', 'auto')
            ->leftJoin('auto.modelo', 'modelo')
            ->leftJoin('modelo.marca', 'marca')
            ->orderBy('c.fechaCreacion', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function responderConsulta(Consulta $consulta, string $respuesta): void
    {
        $respuestaLimpia = trim($respuesta);
        if ($respuestaLimpia === '') {
            throw new \InvalidArgumentException('La respuesta no puede estar vacía.');
        }

        $consulta->setMensaje(
            (string) $consulta->getMensaje() . self::SEPARADOR_RESPUESTA_ADMIN . $respuestaLimpia
        );
        $consulta->setEstado('respondida');

        $this->entityManager->flush();
    }

    public function registrarMensaje(
        SessionInterface $session,
        Usuario $usuario,
        string $texto,
        ?Auto $auto = null,
        bool $esOffline = false,
    ): ?Consulta {
        $textoLimpio = trim($texto);
        if ($textoLimpio === '') {
            throw new \InvalidArgumentException('El mensaje de la consulta no puede estar vacío.');
        }

        $consulta = (new Consulta())
            ->setUsuario($usuario)
            ->setAuto($auto)
            ->setMensaje($textoLimpio)
            ->setEstado($esOffline ? 'offline' : 'pendiente');

        $this->entityManager->persist($consulta);
        $this->entityManager->flush();

        return $consulta;
    }
}
