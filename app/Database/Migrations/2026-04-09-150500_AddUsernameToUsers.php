<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUsernameToUsers extends Migration
{
    public function up()
    {
        if (! $this->db->fieldExists('username', 'users')) {
            $this->forge->addColumn('users', [
                'username' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 50,
                    'null'       => true,
                    'after'      => 'name',
                ],
            ]);
        }

        $this->backfillMissingUsernames();

        // Unique index allows fast lookup by username while still allowing NULL for legacy rows.
        $this->db->query('CREATE UNIQUE INDEX users_username_unique ON users (username)');
    }

    public function down()
    {
        if ($this->db->fieldExists('username', 'users')) {
            $this->db->query('DROP INDEX users_username_unique ON users');
            $this->forge->dropColumn('users', 'username');
        }
    }

    private function backfillMissingUsernames(): void
    {
        $users = $this->db->table('users')
            ->select('id, email, username')
            ->get()
            ->getResultArray();

        foreach ($users as $user) {
            $currentUsername = strtolower(trim((string) ($user['username'] ?? '')));
            if ($currentUsername !== '') {
                continue;
            }

            $emailLocalPart = strtolower((string) strstr((string) ($user['email'] ?? ''), '@', true));
            $base = preg_replace('/[^a-z0-9_-]/', '', $emailLocalPart) ?? '';
            if ($base === '' || strlen($base) < 3) {
                $base = 'user' . $user['id'];
            }

            $candidate = $base;
            $counter = 1;
            while ($this->usernameExists($candidate, (int) $user['id'])) {
                $candidate = $base . $counter;
                $counter++;
            }

            $this->db->table('users')
                ->where('id', (int) $user['id'])
                ->update(['username' => $candidate]);
        }
    }

    private function usernameExists(string $username, int $excludeId): bool
    {
        $count = $this->db->table('users')
            ->where('username', $username)
            ->where('id !=', $excludeId)
            ->countAllResults();

        return $count > 0;
    }
}
