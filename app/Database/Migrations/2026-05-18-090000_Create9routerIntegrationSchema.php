<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Create9routerIntegrationSchema extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('ai_router_accounts')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'account_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'subscription_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'provider' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => '9router',
                ],
                'router_account_ref' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 191,
                ],
                'email' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 160,
                    'null'       => true,
                ],
                'account_plan' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'unknown',
                ],
                'plan_started_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'plan_expires_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'renewal_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'unknown',
                ],
                'mapping_status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 20,
                    'default'    => 'unmapped',
                ],
                'notes' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'last_seen_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey(['provider', 'router_account_ref'], false, true, 'ai_router_accounts_provider_ref_unique');
            $this->forge->addKey('account_id');
            $this->forge->addKey('subscription_id');
            $this->forge->addKey('user_id');
            $this->forge->addKey('status');
            $this->forge->addKey('mapping_status');
            $this->forge->addForeignKey('account_id', 'accounts', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', 'SET NULL', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('ai_router_accounts', true);
        }

        if (! $this->db->tableExists('ai_router_usage_events')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'BIGINT',
                    'constraint'     => 20,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'router_account_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                ],
                'provider' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'default'    => '9router',
                ],
                'model' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 150,
                    'default'    => 'unknown',
                ],
                'router_account_ref' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 191,
                    'null'       => true,
                ],
                'input_tokens' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'output_tokens' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'cache_read_tokens' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'reasoning_tokens' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'duration_ms' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'status' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 30,
                    'default'    => 'success',
                ],
                'event_hash' => [
                    'type'       => 'CHAR',
                    'constraint' => 64,
                ],
                'event_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'raw_log' => [
                    'type' => 'TEXT',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('event_hash', false, true, 'ai_router_usage_events_event_hash_unique');
            $this->forge->addKey('router_account_id');
            $this->forge->addKey('router_account_ref');
            $this->forge->addKey('provider');
            $this->forge->addKey('model');
            $this->forge->addKey('event_at');
            $this->forge->addForeignKey('router_account_id', 'ai_router_accounts', 'id', 'SET NULL', 'CASCADE');
            $this->forge->createTable('ai_router_usage_events', true);
        }

        if (! $this->db->tableExists('ai_router_collector_states')) {
            $this->forge->addField([
                'id' => [
                    'type'           => 'INT',
                    'constraint'     => 11,
                    'unsigned'       => true,
                    'auto_increment' => true,
                ],
                'source_key' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 191,
                ],
                'source_path' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                ],
                'last_offset' => [
                    'type'       => 'BIGINT',
                    'constraint' => 20,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'last_line_number' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'default'    => 0,
                ],
                'last_collected_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'created_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
                'updated_at' => [
                    'type' => 'DATETIME',
                    'null' => true,
                ],
            ]);

            $this->forge->addKey('id', true);
            $this->forge->addKey('source_key', false, true, 'ai_router_collector_states_source_key_unique');
            $this->forge->createTable('ai_router_collector_states', true);
        }
    }

    public function down()
    {
        $this->forge->dropTable('ai_router_collector_states', true);
        $this->forge->dropTable('ai_router_usage_events', true);
        $this->forge->dropTable('ai_router_accounts', true);
    }
}
