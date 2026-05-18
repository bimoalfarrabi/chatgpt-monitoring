<?php

namespace App\Commands;

use App\Services\RouterAnalyticsService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class RouterAnalyticsSummary extends BaseCommand
{
    protected $group = '9router';
    protected $name = 'router:analytics-summary';
    protected $description = 'Ringkasan analytics usage akun router (requests, token, latency, cache ratio, leaderboard).';

    public function run(array $params)
    {
        $providerOption = CLI::getOption('provider') ?? $this->argvOption('provider');
        $daysOption = CLI::getOption('days') ?? $this->argvOption('days');

        $provider = trim((string) ($providerOption ?? ''));
        $days = (int) ($daysOption ?? $this->extractDays($params));
        if ($days <= 0) {
            $days = 30;
        }

        try {
            $service = new RouterAnalyticsService();
            $summary = $service->summary($provider, $days);
        } catch (\Throwable $e) {
            CLI::error('Gagal mengambil analytics: ' . $e->getMessage());
            return;
        }

        $filterProvider = (string) ($summary['filters']['provider'] ?? 'all');
        $filterDays = (int) ($summary['filters']['days'] ?? $days);

        CLI::write('Router Analytics Summary', 'yellow');
        CLI::write('Provider: ' . $filterProvider . ' | Range: ' . $filterDays . ' hari');
        CLI::newLine();

        $overview = is_array($summary['overview'] ?? null) ? $summary['overview'] : [];
        $this->printKeyValue('Tracked accounts', (string) ($overview['tracked_accounts'] ?? 0));
        $this->printKeyValue('Total requests', (string) ($overview['total_requests'] ?? 0));
        $this->printKeyValue('Total input tokens', (string) ($overview['total_input_tokens'] ?? 0));
        $this->printKeyValue('Total output tokens', (string) ($overview['total_output_tokens'] ?? 0));
        $this->printKeyValue('Total cache read tokens', (string) ($overview['total_cache_read_tokens'] ?? 0));
        $this->printKeyValue('Total reasoning tokens', (string) ($overview['total_reasoning_tokens'] ?? 0));
        $this->printKeyValue('Average latency (ms)', (string) ($overview['avg_latency_ms'] ?? 0));
        $this->printKeyValue('Cache efficiency (%)', (string) ($overview['cache_efficiency_ratio_percent'] ?? 0));

        CLI::newLine();
        CLI::write('Leaders', 'yellow');

        $leaders = is_array($summary['leaders'] ?? null) ? $summary['leaders'] : [];
        $this->printLeader('Most used account', $leaders['most_used_account'] ?? null);
        $this->printLeader('Most expensive account', $leaders['most_expensive_account'] ?? null);
        $this->printLeader('Highest reasoning account', $leaders['highest_reasoning_account'] ?? null);
        $this->printLeader('Best cache efficiency account', $leaders['best_cache_efficiency_account'] ?? null);
        $this->printLeader('Lowest avg latency account', $leaders['lowest_avg_latency_account'] ?? null);

        CLI::newLine();
        CLI::write('Top Models', 'yellow');
        $models = is_array($summary['top_models'] ?? null) ? $summary['top_models'] : [];
        if ($models === []) {
            CLI::write('- Belum ada data model.');
            return;
        }

        foreach ($models as $row) {
            if (! is_array($row)) {
                continue;
            }

            $model = (string) ($row['model'] ?? 'unknown');
            $requests = (int) ($row['total_requests'] ?? 0);
            $avgLatency = (int) round((float) ($row['avg_latency_ms'] ?? 0));
            CLI::write('- ' . $model . ' | requests=' . $requests . ' | avg_latency_ms=' . $avgLatency);
        }
    }

    /**
     * @param array<int, string> $params
     */
    private function extractDays(array $params): int
    {
        foreach ($params as $param) {
            $value = trim((string) $param);
            if (! str_starts_with($value, '--days=')) {
                continue;
            }

            $days = (int) trim(substr($value, 7));
            if ($days > 0) {
                return $days;
            }
        }

        return 30;
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

    private function printKeyValue(string $key, string $value): void
    {
        CLI::write(sprintf('- %-28s %s', $key . ':', $value));
    }

    /**
     * @param mixed $row
     */
    private function printLeader(string $title, $row): void
    {
        if (! is_array($row) || $row === []) {
            CLI::write('- ' . $title . ': -');
            return;
        }

        $email = trim((string) ($row['email'] ?? ''));
        $ref = trim((string) ($row['router_account_ref'] ?? ''));
        $display = $email !== '' ? $email : $ref;
        if ($display === '') {
            $display = '-';
        }

        $score = (string) ($row['score'] ?? 0);
        $requests = (int) ($row['total_requests'] ?? 0);
        $latency = (int) ($row['avg_latency_ms'] ?? 0);
        $cache = (float) ($row['cache_efficiency_ratio_percent'] ?? 0);

        CLI::write('- ' . $title . ': ' . $display . ' | score=' . $score . ' | requests=' . $requests . ' | avg_latency=' . $latency . 'ms | cache=' . $cache . '%');
    }
}
