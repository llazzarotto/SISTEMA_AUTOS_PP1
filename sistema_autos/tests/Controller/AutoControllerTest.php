<?php

namespace App\Tests\Controller;

use App\Entity\Auto;
use App\Entity\Marca;
use App\Entity\Modelo;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AutoControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databasePath;
    private mixed $databaseServerAnterior;
    private mixed $databaseEnvAnterior;
    private string|false $databaseProcesoAnterior;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->databasePath = dirname(__DIR__, 2).'/var/cu2-test-'.bin2hex(random_bytes(6)).'.db';
        $this->databaseServerAnterior = $_SERVER['DATABASE_URL'] ?? null;
        $this->databaseEnvAnterior = $_ENV['DATABASE_URL'] ?? null;
        $this->databaseProcesoAnterior = getenv('DATABASE_URL');
        $databaseUrl = 'sqlite:///'.str_replace('\\', '/', $this->databasePath);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        putenv('DATABASE_URL='.$databaseUrl);

        $this->client = static::createClient(['environment' => 'test']);
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            static::getContainer()->get(EntityManagerInterface::class)->getConnection()->close();
        }
        parent::tearDown();
        if (isset($this->databasePath) && is_file($this->databasePath)) {
            unlink($this->databasePath);
        }
        if ($this->databaseServerAnterior === null) {
            unset($_SERVER['DATABASE_URL']);
        } else {
            $_SERVER['DATABASE_URL'] = $this->databaseServerAnterior;
        }
        if ($this->databaseEnvAnterior === null) {
            unset($_ENV['DATABASE_URL']);
        } else {
            $_ENV['DATABASE_URL'] = $this->databaseEnvAnterior;
        }
        putenv($this->databaseProcesoAnterior === false ? 'DATABASE_URL' : 'DATABASE_URL='.$this->databaseProcesoAnterior);
    }

    public function testInicioSigueDisponibleConElKernelDeSymfony(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Encontrá tu próximo');
    }

    public function testCatalogoVacioSeInformaAlCliente(): void
    {
        $this->client->request('GET', '/auto');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Explorá nuestro inventario');
        self::assertSelectorTextContains('.empty-state h2', 'No hay vehículos disponibles');
    }

    public function testFiltrosSinCoincidenciasSePuedenEliminar(): void
    {
        $this->client->request('GET', '/auto?q=marca-inexistente&precioMin=1000&orden=precio_asc');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state h2', 'No se encontraron vehículos');
        self::assertSelectorExists('a[href="/auto"]');
    }

    public function testComparadorVacioInvitaAExplorarElCatalogo(): void
    {
        $this->client->request('GET', '/auto/comparar');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty-state h2', 'Todavía no seleccionaste vehículos');
    }

    public function testCompararDesdeElCatalogoAgregaElAutoSinAbrirSuDetalle(): void
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $sufijo = uniqid('', true);
        $marca = (new Marca())->setNombre('Marca Comparador CU002 '.$sufijo);
        $modelo = (new Modelo())->setNombre('Modelo Comparador '.$sufijo)->setMarca($marca);
        $auto = (new Auto())
            ->setModelo($modelo)
            ->setAnio(2024)
            ->setKilometraje(10000)
            ->setPrecio(20000)
            ->setEstado('disponible');

        $entityManager->persist($marca);
        $entityManager->persist($modelo);
        $entityManager->persist($auto);
        $entityManager->flush();

        $crawler = $this->client->request('GET', '/auto?q=Modelo%20Comparador');
        $form = $crawler->filter('.vehicle-card form')->form();
        $this->client->submit($form);

        self::assertResponseRedirects('/auto');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.catalog-heading .btn', 'Comparar (1/2)');
    }

    public function testCatalogoCombinaFiltrosYOcultaUnidadesNoDisponibles(): void
    {
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

        $this->client->request('GET', sprintf(
            '/auto?q=Prueba%%20CU002&precioMin=10000&precioMax=20000&kilometrajeMax=20000&anioDesde=2023&anioHasta=2025&marca=%d',
            $marca->getId(),
        ));

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.vehicle-card');
        self::assertSelectorTextContains('.vehicle-card', 'Modelo Filtrable');
        self::assertSelectorTextContains('.catalog-heading', '1 vehículo disponible');
    }

    public function testFavoritosRequiereAutenticacion(): void
    {
        $this->client->request('GET', '/favoritos');

        self::assertResponseRedirects('/login?redirect=%2Ffavoritos');
    }
}
