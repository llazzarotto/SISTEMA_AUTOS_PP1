<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ReservaControllerTest extends WebTestCase
{
    public function testLasReservasRequierenUnUsuarioAutenticado(): void
    {
        $cliente = static::createClient();
        $cliente->request('GET', '/reservas');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testLaCreacionDeReservaTambienQuedaProtegida(): void
    {
        $cliente = static::createClient();
        $cliente->request('GET', '/reservas/nueva');

        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }
}
