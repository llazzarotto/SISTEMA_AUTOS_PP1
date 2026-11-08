<?php

namespace App\Manager;

use App\Entity\Auto;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class FinanciamientoManager
{
    public const SIMULACION_EXITOSA = 'EXITO';
    private const CLAVE_SIMULACIONES = 'simulaciones_financiamiento';

    /**
     * @return array{
     *     cuotasDisponibles: list<array{cantidadCuotas: int, tasaInteres: float}>
     * }
     */
    public function obtenerParametros(): array
    {
        return [
            'cuotasDisponibles' => [
                ['cantidadCuotas' => 12, 'tasaInteres' => 15.0],
                ['cantidadCuotas' => 24, 'tasaInteres' => 30.0],
                ['cantidadCuotas' => 36, 'tasaInteres' => 45.0],
                ['cantidadCuotas' => 48, 'tasaInteres' => 60.0],
                ['cantidadCuotas' => 60, 'tasaInteres' => 75.0],
            ],
        ];
    }

    public function obtenerTasaPorCuotas(int $cantidadCuotas): ?float
    {
        foreach ($this->obtenerParametros()['cuotasDisponibles'] as $cuota) {
            if ($cuota['cantidadCuotas'] === $cantidadCuotas) {
                return $cuota['tasaInteres'];
            }
        }

        return null;
    }

    /**
     * @return array{
     *     valido: bool,
     *     errores: array<string, string>,
     *     capitalFinanciar?: float,
     *     valorCuota?: float,
     *     tasaInteres?: float
     * }
     */
    public function calcularSimulacion(Auto $auto, float $montoEntrega, int $cantidadCuotas): array
    {
        $errores = [];
        $precioAuto = (float) $auto->getPrecio();

        if ($montoEntrega < 0) {
            $errores['montoEntrega'] = 'El monto de entrega no puede ser negativo.';
        } elseif ($montoEntrega > $precioAuto) {
            $errores['montoEntrega'] = 'La entrega inicial no puede superar el precio del vehículo.';
        }

        $tasaInteres = $this->obtenerTasaPorCuotas($cantidadCuotas);
        if ($tasaInteres === null) {
            $errores['cantidadCuotas'] = 'Debe seleccionar una cantidad de cuotas válida.';
        }

        if ($errores !== []) {
            return [
                'valido' => false,
                'errores' => $errores,
            ];
        }

        $capitalFinanciar = $precioAuto - $montoEntrega;
        $valorCuota = ($capitalFinanciar + ($capitalFinanciar * ($tasaInteres / 100))) / $cantidadCuotas;

        return [
            'valido' => true,
            'errores' => [],
            'capitalFinanciar' => round($capitalFinanciar, 2),
            'valorCuota' => round($valorCuota, 2),
            'tasaInteres' => $tasaInteres,
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     autoId: int,
     *     montoEntrega: float,
     *     cantidadCuotas: int,
     *     tasaInteres: float,
     *     capitalFinanciar: float,
     *     valorCuota: float
     * }
     */
    public function guardarSimulacion(
        SessionInterface $session,
        Auto $auto,
        float $montoEntrega,
        int $cantidadCuotas,
        float $capitalFinanciar,
        float $valorCuota,
        float $tasaInteres,
    ): array {
        $simulacionId = bin2hex(random_bytes(5));
        $simulacion = [
            'id' => $simulacionId,
            'autoId' => (int) $auto->getId(),
            'montoEntrega' => $montoEntrega,
            'cantidadCuotas' => $cantidadCuotas,
            'tasaInteres' => $tasaInteres,
            'capitalFinanciar' => $capitalFinanciar,
            'valorCuota' => $valorCuota,
        ];

        $simulaciones = $session->get(self::CLAVE_SIMULACIONES, []);
        if (!is_array($simulaciones)) {
            $simulaciones = [];
        }
        $simulaciones[$simulacionId] = $simulacion;
        $session->set(self::CLAVE_SIMULACIONES, $simulaciones);

        return $simulacion;
    }

    /**
     * @return array{
     *     id: string,
     *     autoId: int,
     *     montoEntrega: float,
     *     cantidadCuotas: int,
     *     tasaInteres: float,
     *     capitalFinanciar: float,
     *     valorCuota: float
     * }|null
     */
    public function obtenerSimulacion(SessionInterface $session, string $simulacionId): ?array
    {
        $simulaciones = $session->get(self::CLAVE_SIMULACIONES, []);
        if (!is_array($simulaciones) || !isset($simulaciones[$simulacionId]) || !is_array($simulaciones[$simulacionId])) {
            return null;
        }

        return $simulaciones[$simulacionId];
    }
}

