<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAiRouterAccountSessionsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('ai_router_account_sessions')) {
            return;
        }

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
            'router_account_ref' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 160,
                'null'       => true,
            ],
            'first_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'last_seen_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'total_requests' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_input_tokens' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_output_tokens' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_cache_read_tokens' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_reasoning_tokens' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'total_duration_ms' => [
                'type'       => 'BIGINT',
                'constraint' => 20,
                'unsigned'   => true,
                'default'    => 0,
            ],
            'last_status' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'success',
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
        $this->forge->addKey(['provider', 'router_account_ref'], false, true, 'ai_router_account_sessions_provider_ref_unique');
        $this->forge->addKey('router_account_id');
        $this->forge->addKey('email');
        $this->forge->addKey('last_seen_at');
        $this->forge->addForeignKey('router_account_id', 'ai_router_accounts', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('ai_router_account_sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('ai_router_account_sessions', true);
    }
}
