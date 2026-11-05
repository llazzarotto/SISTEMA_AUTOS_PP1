<?php

namespace App\Tests\Controller;

use App\Entity\Auto;
use App\Entity\Cita;
use App\Entity\Consulta;
use App\Entity\Favorito;
use App\Entity\Marca;
use App\Entity\Modelo;
use App\Entity\Rol;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UsuarioControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databasePath;
    private mixed $databaseServerAnterior;
    private mixed $databaseEnvAnterior;
    private string|false $databaseProcesoAnterior;
    private int $administradorId;
    private int $clienteId;
    private int $rolClienteId;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->databasePath = dirname(__DIR__, 2).'/var/cu8-test-'.bin2hex(random_bytes(8)).'.db';
        $this->databaseServerAnterior = $_SERVER['DATABASE_URL'] ?? null;
        $this->databaseEnvAnterior = $_ENV['DATABASE_URL'] ?? null;
        $this->databaseProcesoAnterior = getenv('DATABASE_URL');
        $databaseUrl = 'sqlite:///'.str_replace('\\', '/', $this->databasePath);
        $_SERVER['DATABASE_URL'] = $_ENV['DATABASE_URL'] = $databaseUrl;
        putenv('DATABASE_URL='.$databaseUrl);

        $this->client = self::createClient(['environment' => 'test']);
        $entityManager = $this->entityManager();
        self::assertSame('pdo_sqlite', $entityManager->getConnection()->getParams()['driver']);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        $rolCliente = (new Rol())->setNombre('Cliente');
        $rolAdministrador = (new Rol())->setNombre('Administrador');
        $entityManager->persist($rolCliente);
        $entityManager->persist($rolAdministrador);
        $administrador = $this->nuevoUsuario('Administrador', 'admin@example.com', $rolAdministrador);
        $cliente = $this->nuevoUsuario('Cliente Ejemplo', 'cliente@example.com', $rolCliente);
        $entityManager->persist($administrador);
        $entityManager->persist($cliente);
        $entityManager->flush();
        $this->administradorId = $administrador->getId();
        $this->clienteId = $cliente->getId();
        $this->rolClienteId = $rolCliente->getId();
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

    public function testLasRutasDeGestionExigenAutenticacion(): void
    {
        foreach (['/usuarios', '/usuarios/crear', '/usuarios/'.$this->clienteId, '/usuarios/'.$this->clienteId.'/editar', '/usuarios/'.$this->clienteId.'/eliminar'] as $ruta) {
            $this->client->request('GET', $ruta);
            self::assertResponseRedirects('/login');
        }
    }

    public function testElClienteNoPuedeConsultarCrearModificarNiEliminarUsuarios(): void
    {
        $this->ingresarComo($this->clienteId);
        foreach (['/usuarios', '/usuarios/crear', '/usuarios/'.$this->administradorId, '/usuarios/'.$this->administradorId.'/editar', '/usuarios/'.$this->administradorId.'/eliminar'] as $ruta) {
            $this->client->request('GET', $ruta);
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('POST', '/usuarios/'.$this->administradorId.'/eliminar');
        self::assertResponseStatusCodeSame(403);
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->administradorId));
    }

    public function testElAdministradorVeElListadoYCreaUnaCuentaConContrasenaHasheada(): void
    {
        $this->ingresarComo($this->administradorId);
        $this->client->request('GET', '/usuarios');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table', 'cliente@example.com');
        self::assertSelectorTextContains('table', 'Administrador');
        self::assertSelectorCount(2, 'tbody tr');

        $crawler = $this->client->request('GET', '/usuarios/crear');
        $this->client->submit($crawler->selectButton('Crear usuario')->form($this->datosUsuario()));
        self::assertResponseRedirects('/usuarios');
        $usuario = $this->entityManager()->getRepository(Usuario::class)->findOneBy(['email' => 'nuevo@example.com']);
        self::assertNotNull($usuario);
        self::assertSame('Nuevo Usuario', $usuario->getNombre());
        self::assertSame('1990-04-12', $usuario->getFechaNacimiento()->format('Y-m-d'));
        self::assertSame('Cliente', $usuario->getRol()->getNombre());
        self::assertNotSame('Segura!123', $usuario->getPassword());
        self::assertTrue(self::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($usuario, 'Segura!123'));

        $this->client->request('GET', '/usuarios/'.$usuario->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Nuevo Usuario');
        self::assertSelectorTextContains('main', '12/04/1990');
        self::assertStringNotContainsString($usuario->getPassword(), $this->client->getResponse()->getContent());
    }

    #[DataProvider('datosInvalidos')]
    public function testLosDatosInvalidosNoCreanUsuarios(array $cambios, string $mensaje): void
    {
        $this->ingresarComo($this->administradorId);
        $crawler = $this->client->request('GET', '/usuarios/crear');
        $form = $crawler->selectButton('Crear usuario')->form();
        $valores = $form->getPhpValues();
        $valores['usuario'] = array_replace($valores['usuario'], [
            'nombre' => 'Nuevo Usuario',
            'email' => 'nuevo@example.com',
            'rol' => (string) $this->rolClienteId,
            'fechaNacimiento' => '1990-04-12',
            'password' => 'Segura!123',
        ], $cambios);
        $this->client->request('POST', '/usuarios/crear', $valores);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', $mensaje);
        self::assertSame(2, $this->entityManager()->getRepository(Usuario::class)->count([]));
    }

    public static function datosInvalidos(): iterable
    {
        yield 'nombre obligatorio' => [['nombre' => ''], 'Ingresá el nombre.'];
        yield 'email inválido' => [['email' => 'correo-invalido'], 'Ingresá un email válido.'];
        yield 'rol obligatorio' => [['rol' => ''], 'Seleccioná el rol.'];
        yield 'fecha obligatoria' => [['fechaNacimiento' => ''], 'Ingresá la fecha de nacimiento.'];
        yield 'fecha futura' => [['fechaNacimiento' => '2999-01-01'], 'La fecha de nacimiento no puede ser futura.'];
        yield 'contraseña débil' => [['password' => 'abc'], 'La contraseña debe tener al menos 6 caracteres.'];
    }

    public function testElEmailDuplicadoSeRechazaSinPerderLosDatosDelFormulario(): void
    {
        $this->ingresarComo($this->administradorId);
        $crawler = $this->client->request('GET', '/usuarios/crear');
        $this->client->submit($crawler->selectButton('Crear usuario')->form(array_replace(
            $this->datosUsuario(),
            ['usuario[email]' => 'cliente@example.com'],
        )));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('main', 'Este email ya está registrado.');
        self::assertInputValueSame('usuario[nombre]', 'Nuevo Usuario');
        self::assertInputValueSame('usuario[fechaNacimiento]', '1990-04-12');
        self::assertSame(2, $this->entityManager()->getRepository(Usuario::class)->count([]));
    }

    public function testCancelarUnaEdicionNoGuardaCambiosYConfirmarlaSiLosGuarda(): void
    {
        $this->ingresarComo($this->administradorId);
        $ruta = '/usuarios/'.$this->clienteId.'/editar';
        $crawler = $this->client->request('GET', $ruta);
        $this->client->submit($crawler->selectButton('Confirmar cambios')->form([
            'usuario[nombre]' => 'Nombre sin guardar',
            'usuario[email]' => 'admin@example.com',
        ]));
        self::assertSelectorTextContains('main', 'Este email ya está registrado.');
        $this->client->clickLink('Cancelar');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Cliente Ejemplo');
        $usuario = $this->entityManager()->find(Usuario::class, $this->clienteId);
        self::assertSame('Cliente Ejemplo', $usuario->getNombre());
        self::assertSame('cliente@example.com', $usuario->getEmail());

        $crawler = $this->client->request('GET', $ruta);
        $this->client->submit($crawler->selectButton('Confirmar cambios')->form([
            'usuario[nombre]' => 'Nombre actualizado',
            'usuario[email]' => 'actualizado@example.com',
            'usuario[fechaNacimiento]' => '1992-03-15',
        ]));
        self::assertResponseRedirects('/usuarios');
        $usuario = $this->entityManager()->find(Usuario::class, $this->clienteId);
        self::assertSame('Nombre actualizado', $usuario->getNombre());
        self::assertSame('actualizado@example.com', $usuario->getEmail());
        self::assertSame('1992-03-15', $usuario->getFechaNacimiento()->format('Y-m-d'));
    }

    public function testLaEliminacionSePuedeCancelarYExigeRazonConfirmacionYCsrf(): void
    {
        $this->ingresarComo($this->administradorId);
        $ruta = '/usuarios/'.$this->clienteId.'/eliminar';
        $this->client->request('GET', $ruta);
        $this->client->clickLink('Cancelar');
        self::assertResponseIsSuccessful();
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->clienteId));

        $crawler = $this->client->request('GET', $ruta);
        $this->client->submit($crawler->selectButton('Eliminar usuario')->form(['form[razon]' => '', 'form[confirmar]' => true]));
        self::assertSelectorTextContains('main', 'Ingresá la razón de la eliminación.');
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->clienteId));

        $crawler = $this->client->request('GET', $ruta);
        $this->client->submit($crawler->selectButton('Eliminar usuario')->form(['form[razon]' => 'Solicitud del usuario', 'form[confirmar]' => false]));
        self::assertSelectorTextContains('main', 'Confirmá la eliminación para continuar.');
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->clienteId));

        $this->client->request('POST', $ruta, ['form' => ['razon' => 'Solicitud del usuario', 'confirmar' => '1', '_token' => 'token-invalido']]);
        self::assertResponseStatusCodeSame(422);
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->clienteId));
    }

    public function testLaEliminacionBorraSoloElUsuarioYSusRelaciones(): void
    {
        $entityManager = $this->entityManager();
        $marca = (new Marca())->setNombre('Marca de prueba');
        $modelo = (new Modelo())->setNombre('Modelo de prueba')->setMarca($marca);
        $auto = (new Auto())->setModelo($modelo)->setAnio(2020)->setKilometraje(10000)->setPrecio(12000000);
        foreach ([$marca, $modelo, $auto] as $entidad) {
            $entityManager->persist($entidad);
        }
        foreach ([$this->clienteId, $this->administradorId] as $usuarioId) {
            $usuario = $entityManager->find(Usuario::class, $usuarioId);
            $cita = (new Cita())->setUsuario($usuario)->setAuto($auto)->setFechaHora(new \DateTimeImmutable('2030-05-10 10:00:00'));
            $consulta = (new Consulta())->setUsuario($usuario)->setAuto($auto)->setMensaje('Consulta de prueba');
            $favorito = (new Favorito())->setUsuario($usuario)->setAuto($auto);
            foreach ([$cita, $consulta, $favorito] as $relacion) {
                $entityManager->persist($relacion);
            }
        }
        $entityManager->flush();
        $autoId = $auto->getId();

        $this->ingresarComo($this->administradorId);
        $crawler = $this->client->request('GET', '/usuarios/'.$this->clienteId.'/eliminar');
        $this->client->submit($crawler->selectButton('Eliminar usuario')->form([
            'form[razon]' => 'Solicitud del usuario',
            'form[confirmar]' => true,
        ]));
        self::assertResponseRedirects('/usuarios');
        self::assertNull($this->entityManager()->find(Usuario::class, $this->clienteId));
        self::assertNotNull($this->entityManager()->find(Usuario::class, $this->administradorId));
        self::assertNotNull($this->entityManager()->find(Auto::class, $autoId));
        foreach ([Cita::class, Consulta::class, Favorito::class] as $clase) {
            $repositorio = $this->entityManager()->getRepository($clase);
            self::assertSame(0, $repositorio->count(['usuario' => $this->clienteId]));
            self::assertSame(1, $repositorio->count(['usuario' => $this->administradorId]));
        }
    }

    private function datosUsuario(): array
    {
        return [
            'usuario[nombre]' => 'Nuevo Usuario',
            'usuario[email]' => 'nuevo@example.com',
            'usuario[rol]' => (string) $this->rolClienteId,
            'usuario[fechaNacimiento]' => '1990-04-12',
            'usuario[password]' => 'Segura!123',
        ];
    }

    private function ingresarComo(int $usuarioId): void
    {
        $this->client->loginUser($this->entityManager()->find(Usuario::class, $usuarioId));
    }

    private function nuevoUsuario(string $nombre, string $email, Rol $rol): Usuario
    {
        $usuario = (new Usuario())
            ->setNombre($nombre)
            ->setApellido('')
            ->setEmail($email)
            ->setRol($rol)
            ->setFechaNacimiento(new \DateTimeImmutable('1985-01-02'));
        $usuario->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($usuario, 'Segura!123'));

        return $usuario;
    }

    private function entityManager(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
