<?php

namespace App\Services;

use App\Models\AiRouterAccountSessionModel;
use App\Models\AiRouterUsageEventModel;

class RouterAnalyticsService
{
    private AiRouterAccountSessionModel $sessions;
    private AiRouterUsageEventModel $usageEvents;

    public function __construct()
    {
        $this->sessions = new AiRouterAccountSessionModel();
        $this->usageEvents = new AiRouterUsageEventModel();
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(string $provider = '', int $days = 30): array
    {
        $provider = strtolower(trim($provider));
        $days = max(1, min(3650, $days));
        $minSeenAt = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $sessionBuilder = $this->sessions;
        if ($provider !== '') {
            $sessionBuilder = $sessionBuilder->where('provider', $provider);
        }
        $sessionBuilder = $sessionBuilder->where('last_seen_at >=', $minSeenAt);
        $sessionRows = $sessionBuilder->findAll();

        $totals = [
            'tracked_accounts' => count($sessionRows),
            'total_requests' => 0,
            'total_input_tokens' => 0,
            'total_output_tokens' => 0,
            'total_cache_read_tokens' => 0,
            'total_reasoning_tokens' => 0,
            'total_duration_ms' => 0,
        ];

        foreach ($sessionRows as $row) {
            $totals['total_requests'] += (int) ($row['total_requests'] ?? 0);
            $totals['total_input_tokens'] += (int) ($row['total_input_tokens'] ?? 0);
            $totals['total_output_tokens'] += (int) ($row['total_output_tokens'] ?? 0);
            $totals['total_cache_read_tokens'] += (int) ($row['total_cache_read_tokens'] ?? 0);
            $totals['total_reasoning_tokens'] += (int) ($row['total_reasoning_tokens'] ?? 0);
            $totals['total_duration_ms'] += (int) ($row['total_duration_ms'] ?? 0);
        }

        $avgLatency = $totals['total_requests'] > 0
            ? (int) round($totals['total_duration_ms'] / $totals['total_requests'])
            : 0;

        $cacheEfficiency = $totals['total_input_tokens'] > 0
            ? round(($totals['total_cache_read_tokens'] / $totals['total_input_tokens']) * 100, 2)
            : 0.0;

        return [
            'filters' => [
                'provider' => $provider !== '' ? $provider : 'all',
                'days' => $days,
                'min_seen_at' => $minSeenAt,
            ],
            'overview' => [
                'tracked_accounts' => $totals['tracked_accounts'],
                'total_requests' => $totals['total_requests'],
                'total_input_tokens' => $totals['total_input_tokens'],
                'total_output_tokens' => $totals['total_output_tokens'],
                'total_cache_read_tokens' => $totals['total_cache_read_tokens'],
                'total_reasoning_tokens' => $totals['total_reasoning_tokens'],
                'avg_latency_ms' => $avgLatency,
                'cache_efficiency_ratio_percent' => $cacheEfficiency,
            ],
            'leaders' => [
                'most_used_account' => $this->pickLeader($sessionRows, 'total_requests'),
                'most_expensive_account' => $this->pickLeader($sessionRows, null, static function (array $row): int {
                    return ((int) ($row['total_input_tokens'] ?? 0)) + ((int) ($row['total_output_tokens'] ?? 0));
                }),
                'highest_reasoning_account' => $this->pickLeader($sessionRows, 'total_reasoning_tokens'),
                'best_cache_efficiency_account' => $this->pickLeader($sessionRows, null, static function (array $row): float {
                    $input = (int) ($row['total_input_tokens'] ?? 0);
                    if ($input <= 0) {
                        return 0.0;
                    }

                    return ((int) ($row['total_cache_read_tokens'] ?? 0)) / $input;
                }),
                'lowest_avg_latency_account' => $this->pickLeader($sessionRows, null, static function (array $row): float {
                    $requests = (int) ($row['total_requests'] ?? 0);
                    if ($requests <= 0) {
                        return 0.0;
                    }

                    return ((int) ($row['total_duration_ms'] ?? 0)) / $requests;
                }, true),
            ],
            'top_models' => $this->topModels($provider, $minSeenAt),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function charts(string $provider = '', int $days = 30, int $top = 10): array
    {
        $provider = strtolower(trim($provider));
        $days = max(1, min(3650, $days));
        $top = max(3, min(30, $top));
        $minSeenAt = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        $dailyRows = $this->dailyTokenSeries($provider, $minSeenAt);
        $accountRows = $this->accountUsageBreakdown($provider, $minSeenAt, $top);
        $modelRows = $this->modelUsageBreakdown($provider, $minSeenAt, $top);
        $hourlyRows = $this->hourlyActivity($provider, $minSeenAt);

        return [
            'filters' => [
                'provider' => $provider !== '' ? $provider : 'all',
                'days' => $days,
                'top' => $top,
                'min_seen_at' => $minSeenAt,
            ],
            'daily_tokens' => $dailyRows,
            'usage_by_account' => $accountRows,
            'usage_by_model' => $modelRows,
            'activity_by_hour' => $hourlyRows,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @param callable(array<string,mixed>):int|float|null $scoreResolver
     *
     * @return array<string, mixed>|null
     */
    private function pickLeader(array $rows, ?string $metricField = null, ?callable $scoreResolver = null, bool $lowerIsBetter = false): ?array
    {
        if ($rows === []) {
            return null;
        }

        $winner = null;
        $winnerScore = null;

        foreach ($rows as $row) {
            $score = 0;
            if ($scoreResolver !== null) {
                $score = $scoreResolver($row);
            } elseif ($metricField !== null) {
                $score = (int) ($row[$metricField] ?? 0);
            }

            if (! is_int($score) && ! is_float($score)) {
                $score = 0;
            }

            if ($winner === null) {
                $winner = $row;
                $winnerScore = $score;
                continue;
            }

            $isBetter = $lowerIsBetter
                ? ((float) $score < (float) $winnerScore)
                : ((float) $score > (float) $winnerScore);

            if ($isBetter) {
                $winner = $row;
                $winnerScore = $score;
            }
        }

        if ($winner === null) {
            return null;
        }

        $requests = (int) ($winner['total_requests'] ?? 0);
        $input = (int) ($winner['total_input_tokens'] ?? 0);
        $cacheRead = (int) ($winner['total_cache_read_tokens'] ?? 0);
        $avgLatency = $requests > 0
            ? (int) round(((int) ($winner['total_duration_ms'] ?? 0)) / $requests)
            : 0;

        return [
            'provider' => (string) ($winner['provider'] ?? '9router'),
            'router_account_ref' => (string) ($winner['router_account_ref'] ?? ''),
            'email' => (string) ($winner['email'] ?? ''),
            'score' => is_float($winnerScore) ? round($winnerScore, 6) : (int) $winnerScore,
            'total_requests' => $requests,
            'total_input_tokens' => $input,
            'total_output_tokens' => (int) ($winner['total_output_tokens'] ?? 0),
            'total_reasoning_tokens' => (int) ($winner['total_reasoning_tokens'] ?? 0),
            'avg_latency_ms' => $avgLatency,
            'cache_efficiency_ratio_percent' => $input > 0 ? round(($cacheRead / $input) * 100, 2) : 0.0,
            'last_seen_at' => (string) ($winner['last_seen_at'] ?? ''),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topModels(string $provider, string $minSeenAt): array
    {
        $builder = $this->usageEvents
            ->select('model, COUNT(*) AS total_requests, SUM(input_tokens) AS total_input_tokens, SUM(output_tokens) AS total_output_tokens, AVG(duration_ms) AS avg_latency_ms')
            ->where('event_at >=', $minSeenAt);

        if ($provider !== '') {
            $builder = $builder->where('provider', $provider);
        }

        return $builder
            ->groupBy('model')
            ->orderBy('total_requests', 'DESC')
            ->limit(5)
            ->findAll();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function dailyTokenSeries(string $provider, string $minSeenAt): array
    {
        $builder = db_connect()
            ->table('ai_router_usage_events')
            ->select("
                DATE(event_at) AS day,
                COUNT(*) AS total_requests,
                SUM(input_tokens) AS input_tokens,
                SUM(output_tokens) AS output_tokens,
                SUM(cache_read_tokens) AS cache_read_tokens,
                SUM(reasoning_tokens) AS reasoning_tokens,
                AVG(NULLIF(duration_ms, 0)) AS avg_latency_ms
            ", false)
            ->where('event_at >=', $minSeenAt);

        if ($provider !== '') {
            $builder->where('provider', $provider);
        }

        $rows = $builder
            ->groupBy('DATE(event_at)', false)
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $input = (int) ($row['input_tokens'] ?? 0);
            $output = (int) ($row['output_tokens'] ?? 0);
            $cacheRead = (int) ($row['cache_read_tokens'] ?? 0);

            $row['day'] = (string) ($row['day'] ?? '');
            $row['total_requests'] = (int) ($row['total_requests'] ?? 0);
            $row['input_tokens'] = $input;
            $row['output_tokens'] = $output;
            $row['total_tokens'] = $input + $output;
            $row['cache_read_tokens'] = $cacheRead;
            $row['reasoning_tokens'] = (int) ($row['reasoning_tokens'] ?? 0);
            $row['avg_latency_ms'] = (int) round((float) ($row['avg_latency_ms'] ?? 0));
            $row['cache_ratio_percent'] = $input > 0 ? round(($cacheRead / $input) * 100, 2) : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function accountUsageBreakdown(string $provider, string $minSeenAt, int $limit): array
    {
        $builder = db_connect()
            ->table('ai_router_usage_events e')
            ->select("
                e.provider,
                e.router_account_ref,
                COALESCE(a.email, '') AS email,
                COUNT(*) AS total_requests,
                SUM(e.input_tokens) AS input_tokens,
                SUM(e.output_tokens) AS output_tokens,
                SUM(e.cache_read_tokens) AS cache_read_tokens,
                SUM(e.reasoning_tokens) AS reasoning_tokens,
                AVG(NULLIF(e.duration_ms, 0)) AS avg_latency_ms
            ", false)
            ->join('ai_router_accounts a', 'a.provider = e.provider AND a.router_account_ref = e.router_account_ref', 'left')
            ->where('e.event_at >=', $minSeenAt)
            ->where('e.router_account_ref IS NOT NULL', null, false)
            ->where('e.router_account_ref !=', '');

        if ($provider !== '') {
            $builder->where('e.provider', $provider);
        }

        $rows = $builder
            ->groupBy('e.provider, e.router_account_ref, a.email')
            ->orderBy('(SUM(e.input_tokens) + SUM(e.output_tokens))', 'DESC', false)
            ->limit($limit)
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $input = (int) ($row['input_tokens'] ?? 0);
            $output = (int) ($row['output_tokens'] ?? 0);
            $cacheRead = (int) ($row['cache_read_tokens'] ?? 0);
            $requests = (int) ($row['total_requests'] ?? 0);

            $row['provider'] = (string) ($row['provider'] ?? '');
            $row['router_account_ref'] = (string) ($row['router_account_ref'] ?? '');
            $row['email'] = strtolower(trim((string) ($row['email'] ?? '')));
            $row['display'] = $row['email'] !== '' ? $row['email'] : 'email belum terdeteksi';
            $row['total_requests'] = $requests;
            $row['input_tokens'] = $input;
            $row['output_tokens'] = $output;
            $row['total_tokens'] = $input + $output;
            $row['cache_read_tokens'] = $cacheRead;
            $row['reasoning_tokens'] = (int) ($row['reasoning_tokens'] ?? 0);
            $row['avg_latency_ms'] = (int) round((float) ($row['avg_latency_ms'] ?? 0));
            $row['cache_ratio_percent'] = $input > 0 ? round(($cacheRead / $input) * 100, 2) : 0.0;
            $row['avg_tokens_per_request'] = $requests > 0
                ? (int) round(($input + $output) / $requests)
                : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelUsageBreakdown(string $provider, string $minSeenAt, int $limit): array
    {
        $builder = db_connect()
            ->table('ai_router_usage_events')
            ->select("
                provider,
                model,
                COUNT(*) AS total_requests,
                SUM(input_tokens) AS input_tokens,
                SUM(output_tokens) AS output_tokens,
                SUM(cache_read_tokens) AS cache_read_tokens,
                SUM(reasoning_tokens) AS reasoning_tokens,
                AVG(NULLIF(duration_ms, 0)) AS avg_latency_ms
            ", false)
            ->where('event_at >=', $minSeenAt);

        if ($provider !== '') {
            $builder->where('provider', $provider);
        }

        $rows = $builder
            ->groupBy('provider, model')
            ->orderBy('(SUM(input_tokens) + SUM(output_tokens))', 'DESC', false)
            ->limit($limit)
            ->get()
            ->getResultArray();

        foreach ($rows as &$row) {
            $input = (int) ($row['input_tokens'] ?? 0);
            $output = (int) ($row['output_tokens'] ?? 0);
            $cacheRead = (int) ($row['cache_read_tokens'] ?? 0);
            $requests = (int) ($row['total_requests'] ?? 0);

            $row['provider'] = (string) ($row['provider'] ?? '');
            $row['model'] = (string) ($row['model'] ?? 'unknown');
            $row['total_requests'] = $requests;
            $row['input_tokens'] = $input;
            $row['output_tokens'] = $output;
            $row['total_tokens'] = $input + $output;
            $row['cache_read_tokens'] = $cacheRead;
            $row['reasoning_tokens'] = (int) ($row['reasoning_tokens'] ?? 0);
            $row['avg_latency_ms'] = (int) round((float) ($row['avg_latency_ms'] ?? 0));
            $row['cache_ratio_percent'] = $input > 0 ? round(($cacheRead / $input) * 100, 2) : 0.0;
            $row['avg_tokens_per_request'] = $requests > 0
                ? (int) round(($input + $output) / $requests)
                : 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hourlyActivity(string $provider, string $minSeenAt): array
    {
        $builder = db_connect()
            ->table('ai_router_usage_events')
            ->select('HOUR(event_at) AS hour_of_day, COUNT(*) AS total_requests', false)
            ->where('event_at >=', $minSeenAt);

        if ($provider !== '') {
            $builder->where('provider', $provider);
        }

        $rows = $builder
            ->groupBy('HOUR(event_at)', false)
            ->orderBy('hour_of_day', 'ASC')
            ->get()
            ->getResultArray();

        $hourMap = [];
        foreach ($rows as $row) {
            $hour = (int) ($row['hour_of_day'] ?? 0);
            if ($hour < 0 || $hour > 23) {
                continue;
            }

            $hourMap[$hour] = (int) ($row['total_requests'] ?? 0);
        }

        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[] = [
                'hour_of_day' => $hour,
                'label' => str_pad((string) $hour, 2, '0', STR_PAD_LEFT) . ':00',
                'total_requests' => $hourMap[$hour] ?? 0,
            ];
        }

        return $result;
    }
}
