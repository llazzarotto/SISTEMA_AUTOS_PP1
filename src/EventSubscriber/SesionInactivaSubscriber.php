<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class SesionInactivaSubscriber implements EventSubscriberInterface
{
    private const SEGUNDOS_INACTIVIDAD = 600; // 10 minutos.

    public function __construct(private TokenStorageInterface $tokens, private UrlGeneratorInterface $urls)
    {
    }

    public static function getSubscribedEvents(): array
    {
        // Symfony ejecuta comprobarSesion() al recibir cada petición.
        return [KernelEvents::REQUEST => ['comprobarSesion', 0]];
    }

    public function comprobarSesion(RequestEvent $event): void
    {
        $request = $event->getRequest();
        if (!$event->isMainRequest() || !$request->hasSession() || str_starts_with($request->getPathInfo(), '/_')) {
            return;
        }

        if ($this->tokens->getToken()?->getUser() === null) {
            return;
        }

        $session = $request->getSession();
        $ahora = time();
        $ultimaActividad = $session->get('ultima_actividad', $ahora);
        if ($ahora - $ultimaActividad >= self::SEGUNDOS_INACTIVIDAD) {
            $this->tokens->setToken(null);
            $session->invalidate();
            $session->getFlashBag()->add('error', 'La sesión venció después de 10 minutos de inactividad. Volvé a ingresar.');
            $event->setResponse(new RedirectResponse($this->urls->generate('app_login')));
            return;
        }

        $session->set('ultima_actividad', $ahora);
    }
}
