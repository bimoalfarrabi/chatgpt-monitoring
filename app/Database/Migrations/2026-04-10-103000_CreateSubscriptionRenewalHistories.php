<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSubscriptionRenewalHistories extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('subscription_renewal_histories')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'subscription_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'old_expired_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'new_expired_at' => [
                'type' => 'DATETIME',
            ],
            'renewed_at' => [
                'type' => 'DATETIME',
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
        $this->forge->addKey('subscription_id');
        $this->forge->addForeignKey('subscription_id', 'subscriptions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('subscription_renewal_histories', true);
    }

    public function down()
    {
        $this->forge->dropTable('subscription_renewal_histories', true);
    }
}
