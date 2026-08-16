<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260320205926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE changelog (id INT AUTO_INCREMENT NOT NULL, entity_type VARCHAR(100) NOT NULL, entity_id INT DEFAULT NULL, change_type VARCHAR(50) NOT NULL, diff JSON DEFAULT NULL, created_at DATETIME NOT NULL, company_id INT DEFAULT NULL, changed_by INT DEFAULT NULL, INDEX IDX_C842260110BC6D9F (changed_by), INDEX idx_changelog_company (company_id), INDEX idx_changelog_entity (entity_type, entity_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE companies (id INT AUTO_INCREMENT NOT NULL, name LONGTEXT NOT NULL, settings JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE employees (id INT AUTO_INCREMENT NOT NULL, external_id VARCHAR(100) DEFAULT NULL, position VARCHAR(255) DEFAULT NULL, contract_minutes_per_week INT DEFAULT NULL, hired_at DATE DEFAULT NULL, terminated_at DATE DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_BA82C300979B1AD6 (company_id), INDEX IDX_BA82C300A76ED395 (user_id), UNIQUE INDEX ux_employees_company_external (company_id, external_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messages (id INT AUTO_INCREMENT NOT NULL, subject VARCHAR(255) DEFAULT NULL, body LONGTEXT DEFAULT NULL, related_entity_type VARCHAR(100) DEFAULT NULL, related_entity_id INT DEFAULT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, company_id INT NOT NULL, sender_id INT DEFAULT NULL, recipient_id INT DEFAULT NULL, INDEX IDX_DB021E96F624B39D (sender_id), INDEX idx_messages_company (company_id), INDEX idx_messages_recipient (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE notifications (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(100) DEFAULT NULL, payload JSON DEFAULT NULL, is_read TINYINT NOT NULL, sent_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, company_id INT DEFAULT NULL, user_id INT NOT NULL, INDEX IDX_6000B0D3979B1AD6 (company_id), INDEX IDX_6000B0D3A76ED395 (user_id), INDEX idx_notifications_user_unread (user_id, is_read), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE overtime_records (id INT AUTO_INCREMENT NOT NULL, period_start DATE NOT NULL, period_end DATE NOT NULL, overtime_minutes INT NOT NULL, deficit_minutes INT NOT NULL, computed_at DATETIME NOT NULL, company_id INT NOT NULL, employee_id INT NOT NULL, INDEX IDX_1EAFB872979B1AD6 (company_id), INDEX IDX_1EAFB8728C03F15C (employee_id), UNIQUE INDEX ux_overtime_employee_period (employee_id, period_start, period_end), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE shifts (id INT AUTO_INCREMENT NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, type VARCHAR(50) NOT NULL, note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INT NOT NULL, employee_id INT NOT NULL, created_by INT DEFAULT NULL, INDEX IDX_1D1D712F979B1AD6 (company_id), INDEX IDX_1D1D712F8C03F15C (employee_id), INDEX IDX_1D1D712FDE12AB56 (created_by), INDEX idx_shifts_employee_time (employee_id, start_time, end_time), INDEX idx_shifts_company_time (company_id, start_time), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE time_entries (id INT AUTO_INCREMENT NOT NULL, start_time DATETIME NOT NULL, end_time DATETIME DEFAULT NULL, break_minutes INT NOT NULL, source VARCHAR(20) NOT NULL, meta JSON DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INT NOT NULL, employee_id INT NOT NULL, linked_shift_id INT DEFAULT NULL, INDEX IDX_797F12A3979B1AD6 (company_id), INDEX IDX_797F12A38C03F15C (employee_id), INDEX IDX_797F12A3D570F2D1 (linked_shift_id), INDEX idx_time_entries_employee_time (employee_id, start_time), INDEX idx_time_entries_company_time (company_id, start_time), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE users (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, password_hash LONGTEXT NOT NULL, role VARCHAR(50) NOT NULL, full_name VARCHAR(255) DEFAULT NULL, is_active TINYINT NOT NULL, last_login DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, company_id INT DEFAULT NULL, INDEX IDX_1483A5E9979B1AD6 (company_id), UNIQUE INDEX ux_users_company_email (company_id, email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE changelog ADD CONSTRAINT FK_C8422601979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE changelog ADD CONSTRAINT FK_C842260110BC6D9F FOREIGN KEY (changed_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE employees ADD CONSTRAINT FK_BA82C300979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE employees ADD CONSTRAINT FK_BA82C300A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE messages ADD CONSTRAINT FK_DB021E96E92F8F78 FOREIGN KEY (recipient_id) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notifications ADD CONSTRAINT FK_6000B0D3A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE overtime_records ADD CONSTRAINT FK_1EAFB872979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE overtime_records ADD CONSTRAINT FK_1EAFB8728C03F15C FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shifts ADD CONSTRAINT FK_1D1D712F979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shifts ADD CONSTRAINT FK_1D1D712F8C03F15C FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shifts ADD CONSTRAINT FK_1D1D712FDE12AB56 FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE time_entries ADD CONSTRAINT FK_797F12A3979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entries ADD CONSTRAINT FK_797F12A38C03F15C FOREIGN KEY (employee_id) REFERENCES employees (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE time_entries ADD CONSTRAINT FK_797F12A3D570F2D1 FOREIGN KEY (linked_shift_id) REFERENCES shifts (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE users ADD CONSTRAINT FK_1483A5E9979B1AD6 FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE changelog DROP FOREIGN KEY FK_C8422601979B1AD6');
        $this->addSql('ALTER TABLE changelog DROP FOREIGN KEY FK_C842260110BC6D9F');
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300979B1AD6');
        $this->addSql('ALTER TABLE employees DROP FOREIGN KEY FK_BA82C300A76ED395');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96979B1AD6');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96F624B39D');
        $this->addSql('ALTER TABLE messages DROP FOREIGN KEY FK_DB021E96E92F8F78');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3979B1AD6');
        $this->addSql('ALTER TABLE notifications DROP FOREIGN KEY FK_6000B0D3A76ED395');
        $this->addSql('ALTER TABLE overtime_records DROP FOREIGN KEY FK_1EAFB872979B1AD6');
        $this->addSql('ALTER TABLE overtime_records DROP FOREIGN KEY FK_1EAFB8728C03F15C');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712F979B1AD6');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712F8C03F15C');
        $this->addSql('ALTER TABLE shifts DROP FOREIGN KEY FK_1D1D712FDE12AB56');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A3979B1AD6');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A38C03F15C');
        $this->addSql('ALTER TABLE time_entries DROP FOREIGN KEY FK_797F12A3D570F2D1');
        $this->addSql('ALTER TABLE users DROP FOREIGN KEY FK_1483A5E9979B1AD6');
        $this->addSql('DROP TABLE changelog');
        $this->addSql('DROP TABLE companies');
        $this->addSql('DROP TABLE employees');
        $this->addSql('DROP TABLE messages');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE overtime_records');
        $this->addSql('DROP TABLE shifts');
        $this->addSql('DROP TABLE time_entries');
        $this->addSql('DROP TABLE users');
    }
}
