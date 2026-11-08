<?php

namespace App\Service;

use App\Entity\Cita;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class NotificacionCitaService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
        private readonly string $remitente,
    ) {
    }

    public function enviar(Cita $cita, string $accion, ?\DateTimeImmutable $fechaAnterior = null): void
    {
        $usuario = $cita->getUsuario();
        if ($usuario?->getEmail() === null) {
            return;
        }

        $fecha = $cita->getFechaHora()?->format('d/m/Y H:i');
        $detalle = match ($accion) {
            'reservada' => "Registramos tu solicitud de cita para el {$fecha}. Queda pendiente de confirmación.",
            'reprogramada' => sprintf(
                "Reprogramamos tu cita del %s para el %s.",
                $fechaAnterior?->format('d/m/Y H:i') ?? 'horario anterior',
                $fecha,
            ),
            'cancelada' => "Cancelamos tu cita del {$fecha}.",
            default => throw new \InvalidArgumentException('Acción de notificación desconocida.'),
        };

        $email = (new Email())
            ->from($this->remitente)
            ->to($usuario->getEmail())
            ->subject('Actualización de tu cita')
            ->text(sprintf(
                "Hola %s,\n\n%s\n\nServicio: %s\nEstado: %s\n\nConcesionario",
                $usuario->getNombre() ?: 'cliente',
                $detalle,
                $cita->getTipoServicio(),
                $cita->getEstado(),
            ));

        try {
            $this->mailer->send($email);
        } catch (\Throwable $error) {
            // La cita ya fue guardada. Un fallo del proveedor de correo no debe
            // hacer que la interfaz informe que la operación principal falló.
            $this->logger->error('No se pudo enviar la notificación de una cita.', [
                'cita_id' => $cita->getId(),
                'accion' => $accion,
                'exception' => $error,
            ]);
        }
    }
}
