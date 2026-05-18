<?php

namespace App\Commands;

use App\Services\RouterAccountMappingService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Sync9routerAccounts extends BaseCommand
{
    protected $group = '9router';
    protected $name = 'router:sync-accounts';
    protected $description = 'Sinkronisasi mapping ai_router_accounts dari ref yang ditemukan di ai_router_usage_events.';

    public function run(array $params)
    {
        $limitOption = CLI::getOption('limit') ?? $this->argvOption('limit');
        $limit = is_scalar($limitOption) && (int) $limitOption > 0
            ? (int) $limitOption
            : $this->extractLimit($params);

        try {
            $service = new RouterAccountMappingService();
            $result = $service->syncFromUsageEvents($limit);
        } catch (\Throwable $e) {
            CLI::error('Sync gagal: ' . $e->getMessage());
            return;
        }

        CLI::write('Refs checked: ' . $result['refs_checked']);
        CLI::write('Accounts created: ' . $result['accounts_created'], 'green');
        CLI::write('Events linked: ' . $result['events_linked']);
    }

    /**
     * @param array<int, string> $params
     */
    private function extractLimit(array $params): int
    {
        foreach ($params as $param) {
            $value = trim((string) $param);
            if (! str_starts_with($value, '--limit=')) {
                continue;
            }

            $limit = (int) trim(substr($value, 8));
            if ($limit > 0) {
                return $limit;
            }
        }

        return 2000;
    }

    private function argvOption(string $name): string|bool|null
    {
        $argv = $_SERVER['argv'] ?? [];
        if (! is_array($argv) || $argv === []) {
            return null;
        }

        $needle = '--' . $name;
        foreach ($argv as $index => $token) {
            if (! is_string($token)) {
                continue;
            }

            if ($token === $needle) {
                $next = $argv[$index + 1] ?? null;
                return is_string($next) ? $next : null;
            }

            if (str_starts_with($token, $needle . '=')) {
                return substr($token, strlen($needle) + 1);
            }
        }

        return null;
    }
}
