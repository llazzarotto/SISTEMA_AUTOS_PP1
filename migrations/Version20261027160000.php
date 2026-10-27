<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261027160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Completa Cita para CU05 con servicio, auto opcional y bloqueo unico de horarios activos';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE cita ADD tipo_servicio VARCHAR(50) DEFAULT 'Test Drive' NOT NULL, ADD fecha_hora_bloqueada DATETIME DEFAULT NULL");
        $this->addSql("UPDATE cita SET fecha_hora_bloqueada = fecha_hora WHERE estado IN ('pendiente', 'confirmada')");
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CITA_FECHA_HORA_BLOQUEADA ON cita (fecha_hora_bloqueada)');
        $this->addSql('ALTER TABLE cita DROP FOREIGN KEY FK_3E379A621D55B925');
        $this->addSql('ALTER TABLE cita CHANGE auto_id auto_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE cita ADD CONSTRAINT FK_3E379A621D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE cita DROP FOREIGN KEY FK_3E379A621D55B925');
        $this->addSql('ALTER TABLE cita CHANGE auto_id auto_id INT NOT NULL');
        $this->addSql('ALTER TABLE cita ADD CONSTRAINT FK_3E379A621D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
        $this->addSql('DROP INDEX UNIQ_CITA_FECHA_HORA_BLOQUEADA ON cita');
        $this->addSql('ALTER TABLE cita DROP tipo_servicio, DROP fecha_hora_bloqueada');
    }
}
