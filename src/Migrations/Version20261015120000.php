<?php

/*
 * This file is part of the Sylius package.
 *
 * (c) Sylius Sp. z o.o.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Sylius\AiAssistantPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Sylius\Bundle\CoreBundle\Doctrine\Migrations\AbstractMigration;

final class Version20261015120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'AI assistant: conversations, messages and the agent actions awaiting approval';
    }

    public function up(Schema $schema): void
    {
        $this->skipIf($schema->hasTable('sylius_ai_assistant_conversation'), 'The AI assistant tables already exist.');

        $this->addSql('CREATE TABLE sylius_ai_assistant_conversation (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, admin_user_id INT NOT NULL, state VARCHAR(16) NOT NULL, pending_navigation VARCHAR(1024) DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_B332E252D17F50A6 (uuid), INDEX IDX_B332E2526352511C (admin_user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sylius_ai_assistant_message (id INT AUTO_INCREMENT NOT NULL, uuid BINARY(16) NOT NULL, conversation_id INT NOT NULL, role VARCHAR(16) NOT NULL, content LONGTEXT NOT NULL, tool_calls JSON DEFAULT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_8F367509D17F50A6 (uuid), INDEX IDX_8F3675099AC0396 (conversation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE sylius_ai_assistant_agent_action (id INT AUTO_INCREMENT NOT NULL, conversation_id INT NOT NULL, decided_by_id INT DEFAULT NULL, state VARCHAR(16) NOT NULL, tool_name VARCHAR(128) DEFAULT NULL, tool_arguments JSON DEFAULT NULL, http_method VARCHAR(8) NOT NULL, path VARCHAR(255) NOT NULL, body JSON DEFAULT NULL, snapshot_before LONGTEXT DEFAULT NULL, result LONGTEXT DEFAULT NULL, decided_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_20971F0F9AC0396 (conversation_id), INDEX IDX_20971F0FE26B496B (decided_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE sylius_ai_assistant_conversation ADD CONSTRAINT FK_B332E2526352511C FOREIGN KEY (admin_user_id) REFERENCES sylius_admin_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_ai_assistant_message ADD CONSTRAINT FK_8F3675099AC0396 FOREIGN KEY (conversation_id) REFERENCES sylius_ai_assistant_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_ai_assistant_agent_action ADD CONSTRAINT FK_20971F0F9AC0396 FOREIGN KEY (conversation_id) REFERENCES sylius_ai_assistant_conversation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE sylius_ai_assistant_agent_action ADD CONSTRAINT FK_20971F0FE26B496B FOREIGN KEY (decided_by_id) REFERENCES sylius_admin_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sylius_ai_assistant_agent_action DROP FOREIGN KEY FK_20971F0F9AC0396');
        $this->addSql('ALTER TABLE sylius_ai_assistant_agent_action DROP FOREIGN KEY FK_20971F0FE26B496B');
        $this->addSql('ALTER TABLE sylius_ai_assistant_message DROP FOREIGN KEY FK_8F3675099AC0396');
        $this->addSql('ALTER TABLE sylius_ai_assistant_conversation DROP FOREIGN KEY FK_B332E2526352511C');
        $this->addSql('DROP TABLE sylius_ai_assistant_agent_action');
        $this->addSql('DROP TABLE sylius_ai_assistant_message');
        $this->addSql('DROP TABLE sylius_ai_assistant_conversation');
    }
}
