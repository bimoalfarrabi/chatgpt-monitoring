<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserIdToTelegramSettings extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('user_id', 'telegram_settings')) {
            $this->forge->addColumn('telegram_settings', [
                'user_id' => [
                    'type'       => 'INT',
                    'constraint' => 11,
                    'unsigned'   => true,
                    'null'       => true,
                    'after'      => 'id',
                ],
            ]);
        }

        $firstUser = $this->db->table('users')
            ->select('id')
            ->orderBy('id', 'ASC')
            ->get()
            ->getFirstRow('array');

        if (is_array($firstUser) && isset($firstUser['id'])) {
            $this->db->table('telegram_settings')
                ->where('user_id', null)
                ->update(['user_id' => (int) $firstUser['id']]);
        }

        $duplicates = $this->db->query(
            'SELECT user_id, MAX(id) AS keep_id FROM telegram_settings WHERE user_id IS NOT NULL GROUP BY user_id HAVING COUNT(*) > 1'
        )->getResultArray();

        foreach ($duplicates as $dup) {
            $this->db->table('telegram_settings')
                ->where('user_id', (int) $dup['user_id'])
                ->where('id !=', (int) $dup['keep_id'])
                ->delete();
        }

        if (! $this->indexExists('telegram_settings', 'telegram_settings_user_id_unique')) {
            $this->db->query('ALTER TABLE telegram_settings ADD UNIQUE INDEX telegram_settings_user_id_unique (user_id)');
        }
    }

    public function down()
    {
        if ($this->indexExists('telegram_settings', 'telegram_settings_user_id_unique')) {
            $this->db->query('ALTER TABLE telegram_settings DROP INDEX telegram_settings_user_id_unique');
        }

        if ($this->db->fieldExists('user_id', 'telegram_settings')) {
            $this->forge->dropColumn('telegram_settings', 'user_id');
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $rows = $this->db->query('SHOW INDEX FROM ' . $table . ' WHERE Key_name = ' . $this->db->escape($indexName))->getResultArray();

        return $rows !== [];
    }
}
