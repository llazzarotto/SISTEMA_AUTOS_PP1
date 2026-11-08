<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108142839 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Permite consultas generales sin vehiculo obligatorio y normaliza estados de autos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consulta DROP FOREIGN KEY FK_A6FE3FDE1D55B925');
        $this->addSql('ALTER TABLE consulta CHANGE auto_id auto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE consulta ADD CONSTRAINT FK_A6FE3FDE1D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id) ON DELETE SET NULL');
        $this->addSql('UPDATE auto SET estado = LOWER(TRIM(estado))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE consulta DROP FOREIGN KEY FK_A6FE3FDE1D55B925');
        $this->addSql('ALTER TABLE consulta CHANGE auto_id auto_id INT NOT NULL');
        $this->addSql('ALTER TABLE consulta ADD CONSTRAINT FK_A6FE3FDE1D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
    }
}
