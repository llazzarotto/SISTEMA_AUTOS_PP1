<?php

namespace App\DataFixtures;

use App\Entity\Auto;
use App\Entity\Marca;
use App\Entity\Modelo;
use App\Entity\Rol;
use App\Entity\Usuario;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private UserPasswordHasherInterface $hasher;

    public function __construct(UserPasswordHasherInterface $hasher)
    {
        $this->hasher = $hasher;
    }

    public function load(ObjectManager $manager): void
    {
        // ---------------------------------------------------------------------
        // 1. ROLES
        // ---------------------------------------------------------------------
        $rolAdmin = new Rol();
        $rolAdmin->setNombre(Rol::NOMBRE_ADMIN);
        $manager->persist($rolAdmin);

        $rolCliente = new Rol();
        $rolCliente->setNombre(Rol::NOMBRE_CLIENTE);
        $manager->persist($rolCliente);

        // ---------------------------------------------------------------------
        // 2. USUARIOS (Admin y Cliente)
        // ---------------------------------------------------------------------
        $admin = new Usuario();
        $admin->setNombre('Admin');
        $admin->setApellido('Principal');
        $admin->setEmail('admin@luxurycars.com');
        $admin->setTelefono('+54 342 1234567');
        $admin->setFechaNacimiento(new \DateTimeImmutable('1990-05-15'));
        $admin->setRol($rolAdmin);
        $admin->setPassword($this->hasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);

        $cliente = new Usuario();
        $cliente->setNombre('Lucas');
        $cliente->setApellido('Lazzarotto');
        $cliente->setEmail('cliente@gmail.com');
        $cliente->setTelefono('+54 342 7654321');
        $cliente->setFechaNacimiento(new \DateTimeImmutable('1998-10-20'));
        $cliente->setRol($rolCliente);
        $cliente->setPassword($this->hasher->hashPassword($cliente, 'cliente123'));
        $manager->persist($cliente);

        // ---------------------------------------------------------------------
        // 3. MARCAS
        // ---------------------------------------------------------------------
        $marcasNombres = ['BMW', 'Mercedes-Benz', 'Audi', 'Porsche', 'Volkswagen', 'Toyota'];
        $marcas = [];

        foreach ($marcasNombres as $nombreMarca) {
            $marca = new Marca();
            $marca->setNombre($nombreMarca);
            $manager->persist($marca);
            $marcas[$nombreMarca] = $marca;
        }

        // ---------------------------------------------------------------------
        // 4. MODELOS
        // ---------------------------------------------------------------------
        $modelosData = [
            'M4 Competition' => $marcas['BMW'],
            'Serie 3 330i'   => $marcas['BMW'],
            'AMG GT'         => $marcas['Mercedes-Benz'],
            'Clase C 300'    => $marcas['Mercedes-Benz'],
            'RS6 Avant'      => $marcas['Audi'],
            'A4 2.0 TFSI'    => $marcas['Audi'],
            '911 Carrera S'   => $marcas['Porsche'],
            'Cayenne GTS'    => $marcas['Porsche'],
            'Golf GTI'       => $marcas['Volkswagen'],
            'Corolla GR'     => $marcas['Toyota'],
        ];

        $modelos = [];
        foreach ($modelosData as $nombreModelo => $entidadMarca) {
            $modelo = new Modelo();
            $modelo->setNombre($nombreModelo);
            $modelo->setMarca($entidadMarca);
            $manager->persist($modelo);
            $modelos[$nombreModelo] = $modelo;
        }

        // ---------------------------------------------------------------------
        // 5. 10 AUTOS DE PRUEBA
        // ---------------------------------------------------------------------
        $autosList = [
            [
                'modelo'      => $modelos['M4 Competition'],
                'anio'        => 2023,
                'kilometraje' => 12500,
                'precio'      => 125000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Impecable estado, paquete de fibra de carbono y escapes deportivos M.'
            ],
            [
                'modelo'      => $modelos['Serie 3 330i'],
                'anio'        => 2021,
                'kilometraje' => 45000,
                'precio'      => 48000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Único dueño, mantenimientos al día en concesionario oficial.'
            ],
            [
                'modelo'      => $modelos['AMG GT'],
                'anio'        => 2024,
                'kilometraje' => 5000,
                'precio'      => 160000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Color mate de fábrica, llantas forjadas de 20 pulgadas y frenos cerámicos.'
            ],
            [
                'modelo'      => $modelos['Clase C 300'],
                'anio'        => 2022,
                'kilometraje' => 32000,
                'precio'      => 54000,
                'estado'      => Auto::ESTADO_RESERVADO,
                'descripcion' => 'Paquete interior AMG Line con techo panorámico corredizo.'
            ],
            [
                'modelo'      => $modelos['RS6 Avant'],
                'anio'        => 2022,
                'kilometraje' => 28000,
                'precio'      => 140000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Tracción Quattro integral, suspensión adaptativa y sonido Bang & Olufsen.'
            ],
            [
                'modelo'      => $modelos['A4 2.0 TFSI'],
                'anio'        => 2020,
                'kilometraje' => 62000,
                'precio'      => 36000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Transmisión S-Tronic de 7 marchas, tapizados en cuero y faros Matrix LED.'
            ],
            [
                'modelo'      => $modelos['911 Carrera S'],
                'anio'        => 2023,
                'kilometraje' => 8900,
                'precio'      => 175000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Paquete Sport Chrono, escape deportivo activo y cubiertas nuevas.'
            ],
            [
                'modelo'      => $modelos['Cayenne GTS'],
                'anio'        => 2021,
                'kilometraje' => 51000,
                'precio'      => 115000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Motor V8 biturbo, interior en cuero y alcántara con costuras rojas.'
            ],
            [
                'modelo'      => $modelos['Golf GTI'],
                'anio'        => 2023,
                'kilometraje' => 19500,
                'precio'      => 42000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Versión MK8 en excelente estado, volante deportivo multifunción con levas.'
            ],
            [
                'modelo'      => $modelos['Corolla GR'],
                'anio'        => 2024,
                'kilometraje' => 3200,
                'precio'      => 58000,
                'estado'      => Auto::ESTADO_DISPONIBLE,
                'descripcion' => 'Tracción total GR-FOUR seleccionable, motor 1.6 turbo y caja manual de 6ta.'
            ],
        ];

        foreach ($autosList as $item) {
            $auto = new Auto();
            $auto->setModelo($item['modelo']);
            $auto->setAnio($item['anio']);
            $auto->setKilometraje($item['kilometraje']);
            $auto->setPrecio($item['precio']);
            $auto->setEstado($item['estado']);
            $auto->setDescripcion($item['descripcion']);

            $manager->persist($auto);
        }

        // ---------------------------------------------------------------------
        // 6. PERSISTIR TODO
        // ---------------------------------------------------------------------
        $manager->flush();
    }
}
