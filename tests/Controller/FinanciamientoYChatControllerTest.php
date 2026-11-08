<?php

namespace App\Tests\Controller;

use App\Entity\Auto;
use App\Entity\Consulta;
use App\Entity\Marca;
use App\Entity\Modelo;
use App\Entity\Rol;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class FinanciamientoYChatControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databasePath;
    private mixed $databaseServerAnterior;
    private mixed $databaseEnvAnterior;
    private string|false $databaseProcesoAnterior;
    private int $clienteId;
    private int $autoDisponibleId;
    private int $autoVendidoId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->databasePath = dirname(__DIR__, 2).'/var/cu3-cu4-test-'.bin2hex(random_bytes(6)).'.db';
        $this->databaseServerAnterior = $_SERVER['DATABASE_URL'] ?? null;
        $this->databaseEnvAnterior = $_ENV['DATABASE_URL'] ?? null;
        $this->databaseProcesoAnterior = getenv('DATABASE_URL');
        $databaseUrl = 'sqlite:///'.str_replace('\\', '/', $this->databasePath);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        putenv('DATABASE_URL='.$databaseUrl);

        $this->client = self::createClient(['environment' => 'test']);
        $entityManager = $this->entityManager();
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $rolCliente = (new Rol())->setNombre('Cliente');
        $rolAdmin = (new Rol())->setNombre('Administrador');
        $entityManager->persist($rolCliente);
        $entityManager->persist($rolAdmin);

        $cliente = (new Usuario())
            ->setNombre('Juan Pérez')
            ->setApellido('Pérez')
            ->setEmail('juan@example.com')
            ->setRol($rolCliente);
        $cliente->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($cliente, 'Clave!123'));
        $entityManager->persist($cliente);

        $marca = (new Marca())->setNombre('Toyota');
        $modelo = (new Modelo())->setNombre('Corolla')->setMarca($marca);
        $autoDisponible = (new Auto())
            ->setModelo($modelo)
            ->setAnio(2023)
            ->setKilometraje(15000)
            ->setPrecio(28500000)
            ->setEstado('disponible');
        $autoVendido = (new Auto())
            ->setModelo($modelo)
            ->setAnio(2020)
            ->setKilometraje(50000)
            ->setPrecio(20000000)
            ->setEstado('vendido');

        foreach ([$marca, $modelo, $autoDisponible, $autoVendido] as $entidad) {
            $entityManager->persist($entidad);
        }
        $entityManager->flush();

        $this->clienteId = (int) $cliente->getId();
        $this->autoDisponibleId = (int) $autoDisponible->getId();
        $this->autoVendidoId = (int) $autoVendido->getId();
    }

    protected function tearDown(): void
    {
        if (self::$kernel !== null) {
            $this->entityManager()->getConnection()->close();
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

    public function testSimuladorMuestraDatosDelAutoDisponible(): void
    {
        $this->client->request('GET', '/financiamiento/'.$this->autoDisponibleId);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.calculadora-container h2', 'Simulador de Financiación');
        self::assertSelectorTextContains('.vehiculo-info', 'Toyota Corolla');
        self::assertSelectorTextContains('#precio-vehiculo', '28.500.000');
    }

    public function testSimuladorInformaErrorParaAutoNoDisponible(): void
    {
        $this->client->request('GET', '/financiamiento/'.$this->autoVendidoId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-danger');
        self::assertSelectorTextContains('.alert-danger', 'no existe o ya no está disponible');
    }

    public function testGuardarResultadoDeSimulacionYVincularloAlChat(): void
    {
        $this->ingresarComoCliente();

        $this->client->request(
            'POST',
            '/financiamiento/'.$this->autoDisponibleId.'/resultado',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['montoEntrega' => 8500000, 'cantidadCuotas' => 12]),
        );

        self::assertResponseIsSuccessful();
        $datos = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame('EXITO', $datos['estado']);
        self::assertNotEmpty($datos['simulacionId']);

        $this->client->request('GET', '/chat/'.$datos['simulacionId']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.chat-context', 'Toyota Corolla');
        self::assertSelectorTextContains('.chat-context', '12 cuotas');
        self::assertSelectorTextContains('.chat-context', '20.000.000');
    }

    public function testChatSinAutenticarMuestraAvisoDeIngreso(): void
    {
        $this->client->request('GET', '/chat?auto='.$this->autoDisponibleId);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.alert-warning');
        self::assertSelectorTextContains('.alert-warning', 'Para iniciar una sesión de chat, debe ingresar al sistema.');
    }

    public function testClienteAutenticadoEnviaConsultaYSePersisteEnBaseDeDatos(): void
    {
        $this->ingresarComoCliente();

        $this->client->request('GET', '/chat?auto='.$this->autoDisponibleId);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.chat-user-info', 'Juan Pérez');
        self::assertSelectorTextContains('.chat-context', 'Toyota Corolla');

        $this->client->request(
            'POST',
            '/chat/enviar',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode([
                'mensaje' => 'Hola, quiero consultar por la financiación del Corolla.',
                'autoId' => $this->autoDisponibleId,
            ]),
        );

        self::assertResponseIsSuccessful();
        $consultas = $this->entityManager()->getRepository(Consulta::class)->findBy(['usuario' => $this->clienteId]);
        self::assertCount(1, $consultas);
        self::assertSame('Hola, quiero consultar por la financiación del Corolla.', $consultas[0]->getMensaje());
        self::assertSame($this->autoDisponibleId, $consultas[0]->getAuto()?->getId());
    }

    private function ingresarComoCliente(): void
    {
        $usuario = $this->entityManager()->find(Usuario::class, $this->clienteId);
        self::assertInstanceOf(Usuario::class, $usuario);
        $this->client->loginUser($usuario);
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}

