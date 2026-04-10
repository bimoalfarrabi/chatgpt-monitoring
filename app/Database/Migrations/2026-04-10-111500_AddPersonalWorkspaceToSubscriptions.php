<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddPersonalWorkspaceToSubscriptions extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('personal_workspace_name', 'subscriptions')) {
            $this->forge->addColumn('subscriptions', [
                'personal_workspace_name' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 120,
                    'null'       => true,
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('personal_workspace_name', 'subscriptions')) {
            $this->forge->dropColumn('subscriptions', 'personal_workspace_name');
        }
    }
}
