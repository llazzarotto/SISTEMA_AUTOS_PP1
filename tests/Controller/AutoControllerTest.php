<?php

namespace App\Tests\Controller;

use App\Entity\Auto;
use App\Entity\Marca;
use App\Entity\Modelo;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AutoControllerTest extends WebTestCase
{
    public function testInicioSigueDisponibleConElKernelDeSymfony(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Encontrá tu próximo');
    }

    public function testCatalogoVacioSeInformaAlCliente(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auto');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Explorá nuestro inventario');
        self::assertSelectorTextContains('.empty-state h2', 'No hay vehículos disponibles');
    }

    public function testFiltrosSinCoincidenciasSePuedenEliminar(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auto?q=marca-inexistente&precioMin=1000&orden=precio_asc');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state h2', 'No se encontraron vehículos');
        self::assertSelectorExists('a[href="/auto"]');
    }

    public function testComparadorVacioInvitaAExplorarElCatalogo(): void
    {
        $client = static::createClient();
        $client->request('GET', '/auto/comparar');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state h2', 'Todavía no seleccionaste vehículos');
    }

    public function testCatalogoCombinaFiltrosYOcultaUnidadesNoDisponibles(): void
    {
        $client = static::createClient();
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $marca = (new Marca())->setNombre('Marca Prueba CU002');
        $modelo = (new Modelo())->setNombre('Modelo Filtrable')->setMarca($marca);
        $disponible = (new Auto())
            ->setModelo($modelo)
            ->setAnio(2024)
            ->setKilometraje(15000)
            ->setPrecio(18000)
            ->setEstado('disponible');
        $vendido = (new Auto())
            ->setModelo($modelo)
            ->setAnio(2024)
            ->setKilometraje(12000)
            ->setPrecio(17000)
            ->setEstado('vendido');

        $entityManager->persist($marca);
        $entityManager->persist($modelo);
        $entityManager->persist($disponible);
        $entityManager->persist($vendido);
        $entityManager->flush();

        try {
            $client->request('GET', sprintf(
                '/auto?q=Prueba%%20CU002&precioMin=10000&precioMax=20000&kilometrajeMax=20000&anioDesde=2023&anioHasta=2025&marca=%d',
                $marca->getId(),
            ));

            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, '.vehicle-card');
            self::assertSelectorTextContains('.vehicle-card', 'Modelo Filtrable');
            self::assertSelectorTextContains('.catalog-heading', '1 vehículo disponible');
        } finally {
            $entityManager->remove($disponible);
            $entityManager->remove($vendido);
            $entityManager->remove($modelo);
            $entityManager->remove($marca);
            $entityManager->flush();
        }
    }

    public function testFavoritosRequiereAutenticacion(): void
    {
        $client = static::createClient();
        $client->request('GET', '/favoritos');

        self::assertResponseRedirects('/login?redirect=%2Ffavoritos');
    }
}
