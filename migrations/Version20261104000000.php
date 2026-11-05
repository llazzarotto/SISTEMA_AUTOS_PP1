<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Agrega fecha de nacimiento y los roles necesarios para CU1 y CU8.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('usuario')->hasColumn('fecha_nacimiento')) {
            $this->addSql('ALTER TABLE usuario ADD fecha_nacimiento DATE DEFAULT NULL');
        }
        $this->addSql("INSERT INTO rol (nombre) SELECT 'Cliente' WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'Cliente')");
        $this->addSql("INSERT INTO rol (nombre) SELECT 'Administrador' WHERE NOT EXISTS (SELECT 1 FROM rol WHERE nombre = 'Administrador')");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE usuario DROP fecha_nacimiento');
        // Los roles se conservan porque pueden estar asignados a usuarios.
    }
}
