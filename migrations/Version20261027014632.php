<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261027014632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE auto (id INT AUTO_INCREMENT NOT NULL, anio INT NOT NULL, kilometraje INT NOT NULL, precio INT NOT NULL, estado VARCHAR(30) NOT NULL, descripcion LONGTEXT DEFAULT NULL, modelo_id INT NOT NULL, INDEX IDX_66BA25FAC3A9576E (modelo_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE cita (id INT AUTO_INCREMENT NOT NULL, fecha_hora DATETIME NOT NULL, estado VARCHAR(30) NOT NULL, observaciones VARCHAR(1000) DEFAULT NULL, usuario_id INT NOT NULL, auto_id INT NOT NULL, INDEX IDX_3E379A62DB38439E (usuario_id), INDEX IDX_3E379A621D55B925 (auto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consulta (id INT AUTO_INCREMENT NOT NULL, mensaje LONGTEXT NOT NULL, estado VARCHAR(30) NOT NULL, fecha_creacion DATETIME NOT NULL, usuario_id INT NOT NULL, auto_id INT NOT NULL, INDEX IDX_A6FE3FDEDB38439E (usuario_id), INDEX IDX_A6FE3FDE1D55B925 (auto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE favorito (id INT AUTO_INCREMENT NOT NULL, fecha_creacion DATETIME NOT NULL, usuario_id INT NOT NULL, auto_id INT NOT NULL, UNIQUE INDEX uniq_favorito_usuario_auto (usuario_id, auto_id), INDEX IDX_881067C7DB38439E (usuario_id), INDEX IDX_881067C71D55B925 (auto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE imagen_auto (id INT AUTO_INCREMENT NOT NULL, ruta VARCHAR(500) NOT NULL, auto_id INT NOT NULL, INDEX IDX_E3FC05401D55B925 (auto_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE marca (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_70A01133A909126 (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE modelo (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(100) NOT NULL, marca_id INT NOT NULL, INDEX IDX_F0D76C4681EF0041 (marca_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE rol (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(50) NOT NULL, UNIQUE INDEX UNIQ_E553F373A909126 (nombre), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE usuario (id INT AUTO_INCREMENT NOT NULL, nombre VARCHAR(100) NOT NULL, apellido VARCHAR(100) NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, telefono VARCHAR(30) DEFAULT NULL, rol_id INT NOT NULL, UNIQUE INDEX UNIQ_2265B05DE7927C74 (email), INDEX IDX_2265B05D4BAB96C (rol_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE auto ADD CONSTRAINT FK_66BA25FAC3A9576E FOREIGN KEY (modelo_id) REFERENCES modelo (id)');
        $this->addSql('ALTER TABLE cita ADD CONSTRAINT FK_3E379A62DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE cita ADD CONSTRAINT FK_3E379A621D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
        $this->addSql('ALTER TABLE consulta ADD CONSTRAINT FK_A6FE3FDEDB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE consulta ADD CONSTRAINT FK_A6FE3FDE1D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
        $this->addSql('ALTER TABLE favorito ADD CONSTRAINT FK_881067C7DB38439E FOREIGN KEY (usuario_id) REFERENCES usuario (id)');
        $this->addSql('ALTER TABLE favorito ADD CONSTRAINT FK_881067C71D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
        $this->addSql('ALTER TABLE imagen_auto ADD CONSTRAINT FK_E3FC05401D55B925 FOREIGN KEY (auto_id) REFERENCES auto (id)');
        $this->addSql('ALTER TABLE modelo ADD CONSTRAINT FK_F0D76C4681EF0041 FOREIGN KEY (marca_id) REFERENCES marca (id)');
        $this->addSql('ALTER TABLE usuario ADD CONSTRAINT FK_2265B05D4BAB96C FOREIGN KEY (rol_id) REFERENCES rol (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE auto DROP FOREIGN KEY FK_66BA25FAC3A9576E');
        $this->addSql('ALTER TABLE cita DROP FOREIGN KEY FK_3E379A62DB38439E');
        $this->addSql('ALTER TABLE cita DROP FOREIGN KEY FK_3E379A621D55B925');
        $this->addSql('ALTER TABLE consulta DROP FOREIGN KEY FK_A6FE3FDEDB38439E');
        $this->addSql('ALTER TABLE consulta DROP FOREIGN KEY FK_A6FE3FDE1D55B925');
        $this->addSql('ALTER TABLE favorito DROP FOREIGN KEY FK_881067C7DB38439E');
        $this->addSql('ALTER TABLE favorito DROP FOREIGN KEY FK_881067C71D55B925');
        $this->addSql('ALTER TABLE imagen_auto DROP FOREIGN KEY FK_E3FC05401D55B925');
        $this->addSql('ALTER TABLE modelo DROP FOREIGN KEY FK_F0D76C4681EF0041');
        $this->addSql('ALTER TABLE usuario DROP FOREIGN KEY FK_2265B05D4BAB96C');
        $this->addSql('DROP TABLE auto');
        $this->addSql('DROP TABLE cita');
        $this->addSql('DROP TABLE consulta');
        $this->addSql('DROP TABLE favorito');
        $this->addSql('DROP TABLE imagen_auto');
        $this->addSql('DROP TABLE marca');
        $this->addSql('DROP TABLE modelo');
        $this->addSql('DROP TABLE rol');
        $this->addSql('DROP TABLE usuario');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
