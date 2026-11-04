<?php

namespace App\Tests\Controller;

use App\Entity\Rol;
use App\Entity\Usuario;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private string $databasePath;
    private mixed $databaseServerAnterior;
    private mixed $databaseEnvAnterior;
    private string|false $databaseProcesoAnterior;

    protected function setUp(): void
    {
        // Una base SQLite nueva por prueba: nunca se usa la base MySQL del proyecto.
        $this->databaseServerAnterior = $_SERVER['DATABASE_URL'] ?? null;
        $this->databaseEnvAnterior = $_ENV['DATABASE_URL'] ?? null;
        $this->databaseProcesoAnterior = getenv('DATABASE_URL');
        $this->databasePath = dirname(__DIR__, 2).'/var/cu1-'.bin2hex(random_bytes(6)).'.db';
        $url = 'sqlite:///'.str_replace('\\', '/', $this->databasePath);
        $_ENV['DATABASE_URL'] = $_SERVER['DATABASE_URL'] = $url;
        putenv('DATABASE_URL='.$url);
        $this->client = static::createClient();
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $entityManager->getConnection()->getParams()['driver']);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $entityManager->persist((new Rol())->setNombre('Cliente'));
        $entityManager->persist((new Rol())->setNombre('Administrador'));
        $entityManager->flush();
    }

    protected function tearDown(): void
    {
        if (static::$kernel !== null) {
            static::getContainer()->get(EntityManagerInterface::class)->getConnection()->close();
        }
        parent::tearDown();
        if (is_file($this->databasePath)) {
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

    public function testRegistroCreaClienteConContrasenaHasheada(): void
    {
        $this->client->request('GET', '/registro');
        $this->client->submitForm('Registrarse', [
            'registro[email]' => 'Cliente@ejemplo.com',
            'registro[password]' => 'Clave!123',
        ]);
        self::assertResponseRedirects('/login');

        $usuario = $this->usuarios()->findOneBy(['email' => 'cliente@ejemplo.com']);
        self::assertNotNull($usuario);
        self::assertSame('Cliente', $usuario->getRol()->getNombre());
        self::assertNotSame('Clave!123', $usuario->getPassword());
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($usuario, 'Clave!123'));

        $guardado = static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchAssociative(
            'SELECT email, password FROM usuario WHERE id = ?',
            [$usuario->getId()],
        );
        self::assertIsArray($guardado);
        self::assertSame('cliente@ejemplo.com', $guardado['email']);
        self::assertSame($usuario->getPassword(), $guardado['password']);
        self::assertNotSame('Clave!123', $guardado['password']);
    }

    public function testCuentaRecienRegistradaValidaSuContrasenaAlIniciarSesion(): void
    {
        $this->client->request('GET', '/registro');
        $this->client->submitForm('Registrarse', [
            'registro[email]' => 'cliente@ejemplo.com',
            'registro[password]' => 'Clave!123',
        ]);
        self::assertResponseRedirects('/login');

        $hash = $this->usuarios()->findOneBy(['email' => 'cliente@ejemplo.com'])->getPassword();
        foreach (['Incorrecta!1', $hash] as $passwordIncorrecta) {
            $this->ingresar('cliente@ejemplo.com', $passwordIncorrecta);
            self::assertResponseRedirects('/login');
            $this->client->followRedirect();
            self::assertSelectorTextContains('[role="alert"]', 'La contraseña es incorrecta para ese email.');
            self::assertSelectorNotExists('form[action="/logout"]');
        }

        $this->ingresar('cliente@ejemplo.com', 'Clave!123');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('form[action="/logout"]');
    }

    public function testMismaContrasenaGeneraHashesDistintosEnCadaRegistro(): void
    {
        $hashes = [];
        foreach (['primero@ejemplo.com', 'segundo@ejemplo.com'] as $email) {
            $this->client->request('GET', '/registro');
            $this->client->submitForm('Registrarse', [
                'registro[email]' => $email,
                'registro[password]' => 'Clave!123',
            ]);
            self::assertResponseRedirects('/login');

            $usuario = $this->usuarios()->findOneBy(['email' => $email]);
            self::assertNotNull($usuario);
            self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($usuario, 'Clave!123'));
            $hashes[] = $usuario->getPassword();
        }

        self::assertNotSame($hashes[0], $hashes[1]);
    }

    public function testRegistroRechazaEmailDuplicadoYContrasenaSinSimbolo(): void
    {
        $this->crearUsuario('cliente@ejemplo.com');
        $this->client->request('GET', '/registro');
        $this->client->submitForm('Registrarse', [
            'registro[email]' => 'CLIENTE@ejemplo.com',
            'registro[password]' => 'Clave!123',
        ]);
        self::assertSelectorTextContains('.cuentas-form', 'Este email ya está registrado.');
        self::assertSame(1, $this->usuarios()->count([]));

        $this->client->submitForm('Registrarse', [
            'registro[email]' => 'nuevo@ejemplo.com',
            'registro[password]' => 'Clave123',
        ]);
        self::assertSelectorTextContains('.cuentas-form', 'Incluí un símbolo especial.');
        self::assertSame(1, $this->usuarios()->count([]));
    }

    public function testRegistroRechazaEmailInvalidoYDatosManipulados(): void
    {
        $this->client->request('GET', '/registro');
        $crawler = $this->client->getCrawler();
        $form = $crawler->selectButton('Registrarse')->form();
        $datos = $form->getPhpValues();
        $datos['registro']['email'] = 'correo-invalido';
        $datos['registro']['password'] = 'Clave!123';
        $this->client->request('POST', '/registro', $datos);
        self::assertSelectorTextContains('.cuentas-form', 'Ingresá un email con un dominio válido.');
        self::assertSame(0, $this->usuarios()->count([]));

        $datos['registro']['email'] = 'nuevo@ejemplo.com';
        $datos['registro']['rol'] = 'Administrador';
        $this->client->request('POST', '/registro', $datos);
        self::assertSame(0, $this->usuarios()->count([]));
    }

    public function testLoginDistingueEmailInexistenteYContrasenaIncorrecta(): void
    {
        $this->crearUsuario('cliente@ejemplo.com');
        $this->ingresar('noexiste@ejemplo.com', 'Clave!123');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'El email no pertenece a una cuenta registrada.');

        $this->ingresar('cliente@ejemplo.com', 'Incorrecta!1');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'La contraseña es incorrecta para ese email.');
    }

    public function testLoginMantieneSesionYLogoutLaCierra(): void
    {
        $this->crearUsuario('admin@ejemplo.com', 'Administrador');
        $this->ingresar('admin@ejemplo.com', 'Clave!123');
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorExists('a[href="/usuarios"]');
        $this->client->request('GET', '/usuarios');
        self::assertResponseIsSuccessful();

        $this->client->submitForm('Cerrar sesión');
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/usuarios');
        self::assertResponseRedirects('http://localhost/login');
    }

    public function testLoginSinCsrfNoAbreSesion(): void
    {
        $this->crearUsuario('cliente@ejemplo.com');
        $this->client->request('POST', '/login', [
            '_username' => 'cliente@ejemplo.com',
            '_password' => 'Clave!123',
            '_csrf_token' => 'invalido',
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'El formulario venció.');
        self::assertSelectorExists('a[href="/login"]');
    }

    public function testSesionVenceTrasDiezMinutosDeInactividad(): void
    {
        $this->crearUsuario('admin@ejemplo.com', 'Administrador');
        $this->ingresar('admin@ejemplo.com', 'Clave!123');
        $this->client->followRedirect();

        $session = static::getContainer()->get('session.factory')->createSession();
        $session->setId($this->client->getCookieJar()->get($session->getName())->getValue());
        $session->start();
        $session->set('ultima_actividad', time() - 601);
        $session->save();

        $this->client->request('GET', '/usuarios');
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"]', 'La sesión venció');
        $this->client->request('GET', '/usuarios');
        self::assertResponseRedirects('http://localhost/login');
    }

    private function ingresar(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Ingresar', ['_username' => $email, '_password' => $password]);
    }

    private function crearUsuario(string $email, string $rol = 'Cliente'): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $usuario = (new Usuario())->setNombre('Prueba')->setApellido('')->setEmail($email)
            ->setRol($entityManager->getRepository(Rol::class)->findOneBy(['nombre' => $rol]));
        $usuario->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($usuario, 'Clave!123'));
        $entityManager->persist($usuario);
        $entityManager->flush();
    }

    private function usuarios(): \Doctrine\ORM\EntityRepository
    {
        return static::getContainer()->get(EntityManagerInterface::class)->getRepository(Usuario::class);
    }
}
