<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use DateInterval;
use DateTime;
use Throwable;

class AddWorkspaceFieldsToSubscriptions extends Migration
{
    public function up()
    {
        $columns = [];

        if (! $this->db->fieldExists('account_type', 'subscriptions')) {
            $columns['account_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'default'    => 'free',
            ];
        }

        if (! $this->db->fieldExists('pro_account_type', 'subscriptions')) {
            $columns['pro_account_type'] = [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ];
        }

        if (! $this->db->fieldExists('workspace_name', 'subscriptions')) {
            $columns['workspace_name'] = [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
            ];
        }

        if (! $this->db->fieldExists('is_workspace_deactivated', 'subscriptions')) {
            $columns['is_workspace_deactivated'] = [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ];
        }

        if (! $this->db->fieldExists('subscribed_at', 'subscriptions')) {
            $columns['subscribed_at'] = [
                'type' => 'DATETIME',
                'null' => true,
            ];
        }

        if (! $this->db->fieldExists('is_one_month_duration', 'subscriptions')) {
            $columns['is_one_month_duration'] = [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => true,
            ];
        }

        if ($columns !== []) {
            $this->forge->addColumn('subscriptions', $columns);
        }

        $this->forge->modifyColumn('subscriptions', [
            'expired_at' => [
                'name' => 'expired_at',
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $rows = $this->db->table('subscriptions')
            ->select('id, expired_at')
            ->get()
            ->getResultArray();

        $builder = $this->db->table('subscriptions');

        foreach ($rows as $row) {
            $expiredAt = $row['expired_at'] ?? null;
            $subscribedAt = null;
            $isOneMonth = 0;

            if ($expiredAt !== null && trim((string) $expiredAt) !== '') {
                $isOneMonth = 1;
                $subscribedAt = $expiredAt;

                try {
                    $start = new DateTime($expiredAt);
                    $subscribedAt = $start->sub(new DateInterval('P1M'))->format('Y-m-d H:i:s');
                } catch (Throwable) {
                    $subscribedAt = $expiredAt;
                }
            }

            $builder->where('id', $row['id'])->update([
                'account_type'           => 'pro',
                'subscribed_at'          => $subscribedAt,
                'is_one_month_duration'  => $isOneMonth,
            ]);
        }
    }

    public function down()
    {
        $this->db->table('subscriptions')
            ->where('expired_at', null)
            ->set('expired_at', date('Y-m-d H:i:s'))
            ->update();

        $this->forge->modifyColumn('subscriptions', [
            'expired_at' => [
                'name' => 'expired_at',
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        foreach ([
            'is_one_month_duration',
            'subscribed_at',
            'is_workspace_deactivated',
            'workspace_name',
            'pro_account_type',
            'account_type',
        ] as $column) {
            if ($this->db->fieldExists($column, 'subscriptions')) {
                $this->forge->dropColumn('subscriptions', $column);
            }
        }
    }
}
