<?php

namespace App\Commands;

use App\Services\RouterUsageCollectorService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class Collect9routerUsage extends BaseCommand
{
    protected $group = '9router';
    protected $name = 'router:push-usage';
    protected $description = 'Push usage 9router dari file log lokal ke tabel ai_router_usage_events.';

    public function run(array $params)
    {
        $options = $this->parseOptions($params);

        $fileOption = CLI::getOption('file') ?? $this->argvOption('file');
        $providerOption = CLI::getOption('provider') ?? $this->argvOption('provider');
        $resetCursorOption = CLI::getOption('reset-cursor') ?? $this->argvOption('reset-cursor');

        $sourceFile = trim((string) ($fileOption ?? $options['file'] ?? env('router.logPath', '')));
        $provider = trim((string) ($providerOption ?? $options['provider'] ?? env('router.provider', '9router')));
        $resetCursor = (bool) (($resetCursorOption ?? ($options['reset_cursor'] ?? null)) ?? false);

        if ($sourceFile === '') {
            CLI::error('Path log belum diisi. Pakai --file=/path/log atau set env router.logPath');
            return;
        }

        try {
            $collector = new RouterUsageCollectorService();
            $result = $collector->collectFromLogFile($sourceFile, $provider, $resetCursor);
        } catch (\Throwable $e) {
            CLI::error('Collector gagal: ' . $e->getMessage());
            return;
        }

        CLI::write('Sumber: ' . $result['source'], 'yellow');
        CLI::write('Scanned: ' . $result['scanned']);
        CLI::write('Parsed: ' . $result['parsed']);
        CLI::write('Inserted: ' . $result['inserted'], 'green');
        CLI::write('Duplicates: ' . $result['duplicates']);
        CLI::write('Invalid: ' . $result['invalid']);
        CLI::write('Skipped: ' . $result['skipped']);
        CLI::write('Cursor line: ' . $result['cursor_line']);
        CLI::write('Cursor offset: ' . $result['cursor_offset']);
    }

    /**
     * @param array<int, string> $params
     *
     * @return array{file?:string,provider?:string,reset_cursor?:bool}
     */
    private function parseOptions(array $params): array
    {
        $options = [];

        foreach ($params as $param) {
            $value = trim((string) $param);

            if ($value === '--reset-cursor') {
                $options['reset_cursor'] = true;
                continue;
            }

            if (str_starts_with($value, '--file=')) {
                $options['file'] = trim(substr($value, 7));
                continue;
            }

            if (str_starts_with($value, '--provider=')) {
                $options['provider'] = trim(substr($value, 11));
            }
        }

        return $options;
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
                $next = $argv[$index + 1] ?? true;
                if (! is_string($next) || str_starts_with($next, '--')) {
                    return true;
                }

                return $next;
            }

            if (str_starts_with($token, $needle . '=')) {
                return substr($token, strlen($needle) + 1);
            }
        }

        return null;
    }
}
