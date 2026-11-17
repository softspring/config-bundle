<?php

declare(strict_types=1);

namespace Softspring\Bundle\ConfigBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261117150000 extends AbstractMigration
{
    public function isTransactional(): bool
    {
        return false;
    }

    public function getDescription(): string
    {
        return 'Create administrative configuration overrides.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('sfs_config_value');
        $table->addColumn('setting_key', 'string', ['length' => 190]);
        $table->addColumn('setting_value', 'json', ['notnull' => false]);
        $table->addColumn('updated_at', 'datetime_immutable');
        $table->setPrimaryKey(['setting_key']);
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('sfs_config_value');
    }
}
