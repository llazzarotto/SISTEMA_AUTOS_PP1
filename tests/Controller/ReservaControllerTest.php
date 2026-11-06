<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReservaControllerTest extends WebTestCase
{
    public function testLasReservasRequierenUnUsuarioAutenticado(): void
    {
        $cliente = static::createClient();
        $cliente->request('GET', '/reservas');

        self::assertResponseRedirects('/login');
    }

    public function testLaCreacionDeReservaTambienQuedaProtegida(): void
    {
        $cliente = static::createClient();
        $cliente->request('GET', '/reservas/nueva');

        self::assertResponseRedirects('/login');
    }
}
