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
        $overview = $this->periodOverview($provider, $minSeenAt);

        $dailyRows = $this->dailyTokenSeries($provider, $minSeenAt);
        $accountRows = $this->accountUsageBreakdown($provider, $minSeenAt, $top, (int) ($overview['total_tokens'] ?? 0));
        $modelRows = $this->modelUsageBreakdown($provider, $minSeenAt, $top, (int) ($overview['total_tokens'] ?? 0));
        $hourlyRows = $this->hourlyActivity($provider, $minSeenAt);
        $statusRows = $this->statusBreakdown((int) ($overview['total_requests'] ?? 0), (int) ($overview['complete_requests'] ?? 0), (int) ($overview['disconnect_requests'] ?? 0));
        $successRate = (float) ($overview['success_rate_percent'] ?? 0.0);

        return [
            'filters' => [
                'provider' => $provider !== '' ? $provider : 'all',
                'days' => $days,
                'top' => $top,
                'min_seen_at' => $minSeenAt,
            ],
            'overview' => $overview,
            'success_rate_percent' => $successRate,
            'status_breakdown' => $statusRows,
            'daily_tokens' => $dailyRows,
            'usage_by_account' => $accountRows,
            'usage_by_model' => $modelRows,
            'activity_by_hour' => $hourlyRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountShareByEmail(string $email, string $provider = '', int $days = 30): array
    {
        $normalizedEmail = strtolower(trim($email));
        $provider = strtolower(trim($provider));
        $days = max(1, min(3650, $days));
        $minSeenAt = date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));

        if ($normalizedEmail === '' || filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL) === false) {
            return [
                'filters' => [
                    'email' => $normalizedEmail,
                    'provider' => $provider !== '' ? $provider : 'all',
                    'days' => $days,
                    'min_seen_at' => $minSeenAt,
                ],
                'summary' => [
                    'total_tokens_all' => 0,
                    'total_tokens_account' => 0,
                    'usage_share_percent' => 0.0,
                    'total_requests_all' => 0,
                    'total_requests_account' => 0,
                    'request_share_percent' => 0.0,
                ],
                'daily_share' => [],
            ];
        }

        $totalsBuilder = db_connect()
            ->table('ai_router_usage_events')
            ->select('COUNT(*) AS total_requests_all, SUM(input_tokens + output_tokens) AS total_tokens_all', false)
            ->where('event_at >=', $minSeenAt);
        if ($provider !== '') {
            $totalsBuilder->where('provider', $provider);
        }
        $totals = $totalsBuilder->get()->getRowArray() ?? [];

        $accountBuilder = db_connect()
            ->table('ai_router_usage_events e')
            ->select('COUNT(*) AS total_requests_account, SUM(e.input_tokens + e.output_tokens) AS total_tokens_account', false)
            ->join('ai_router_accounts a', 'a.provider = e.provider AND a.router_account_ref = e.router_account_ref', 'inner')
            ->where('e.event_at >=', $minSeenAt)
            ->where('LOWER(a.email)', $normalizedEmail);
        if ($provider !== '') {
            $accountBuilder->where('e.provider', $provider);
        }
        $accountTotals = $accountBuilder->get()->getRowArray() ?? [];

        $dailyTotalsBuilder = db_connect()
            ->table('ai_router_usage_events')
            ->select('DATE(event_at) AS day, COUNT(*) AS total_requests_all, SUM(input_tokens + output_tokens) AS total_tokens_all', false)
            ->where('event_at >=', $minSeenAt);
        if ($provider !== '') {
            $dailyTotalsBuilder->where('provider', $provider);
        }
        $dailyTotalsRows = $dailyTotalsBuilder
            ->groupBy('DATE(event_at)', false)
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $dailyAccountBuilder = db_connect()
            ->table('ai_router_usage_events e')
            ->select('DATE(e.event_at) AS day, COUNT(*) AS total_requests_account, SUM(e.input_tokens + e.output_tokens) AS total_tokens_account', false)
            ->join('ai_router_accounts a', 'a.provider = e.provider AND a.router_account_ref = e.router_account_ref', 'inner')
            ->where('e.event_at >=', $minSeenAt)
            ->where('LOWER(a.email)', $normalizedEmail);
        if ($provider !== '') {
            $dailyAccountBuilder->where('e.provider', $provider);
        }
        $dailyAccountRows = $dailyAccountBuilder
            ->groupBy('DATE(e.event_at)', false)
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $dailyAccountMap = [];
        foreach ($dailyAccountRows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }
            $dailyAccountMap[$day] = [
                'total_requests_account' => (int) ($row['total_requests_account'] ?? 0),
                'total_tokens_account' => (int) ($row['total_tokens_account'] ?? 0),
            ];
        }

        $dailyShare = [];
        foreach ($dailyTotalsRows as $row) {
            $day = (string) ($row['day'] ?? '');
            if ($day === '') {
                continue;
            }

            $totalRequestsAll = (int) ($row['total_requests_all'] ?? 0);
            $totalTokensAll = (int) ($row['total_tokens_all'] ?? 0);
            $accountDay = $dailyAccountMap[$day] ?? ['total_requests_account' => 0, 'total_tokens_account' => 0];
            $totalRequestsAccount = (int) ($accountDay['total_requests_account'] ?? 0);
            $totalTokensAccount = (int) ($accountDay['total_tokens_account'] ?? 0);

            $dailyShare[] = [
                'day' => $day,
                'total_tokens_all' => $totalTokensAll,
                'total_tokens_account' => $totalTokensAccount,
                'usage_share_percent' => $totalTokensAll > 0 ? round(($totalTokensAccount / $totalTokensAll) * 100, 2) : 0.0,
                'total_requests_all' => $totalRequestsAll,
                'total_requests_account' => $totalRequestsAccount,
                'request_share_percent' => $totalRequestsAll > 0 ? round(($totalRequestsAccount / $totalRequestsAll) * 100, 2) : 0.0,
            ];
        }

        $totalTokensAll = (int) ($totals['total_tokens_all'] ?? 0);
        $totalTokensAccount = (int) ($accountTotals['total_tokens_account'] ?? 0);
        $totalRequestsAll = (int) ($totals['total_requests_all'] ?? 0);
        $totalRequestsAccount = (int) ($accountTotals['total_requests_account'] ?? 0);

        return [
            'filters' => [
                'email' => $normalizedEmail,
                'provider' => $provider !== '' ? $provider : 'all',
                'days' => $days,
                'min_seen_at' => $minSeenAt,
            ],
            'summary' => [
                'total_tokens_all' => $totalTokensAll,
                'total_tokens_account' => $totalTokensAccount,
                'usage_share_percent' => $totalTokensAll > 0 ? round(($totalTokensAccount / $totalTokensAll) * 100, 2) : 0.0,
                'total_requests_all' => $totalRequestsAll,
                'total_requests_account' => $totalRequestsAccount,
                'request_share_percent' => $totalRequestsAll > 0 ? round(($totalRequestsAccount / $totalRequestsAll) * 100, 2) : 0.0,
            ],
            'daily_share' => $dailyShare,
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
     * @return array<string, int|float>
     */
    private function periodOverview(string $provider, string $minSeenAt): array
    {
        $builder = db_connect()
            ->table('ai_router_usage_events')
            ->select("
                COUNT(*) AS total_requests,
                SUM(input_tokens) AS total_input_tokens,
                SUM(output_tokens) AS total_output_tokens,
                SUM(cache_read_tokens) AS total_cache_read_tokens,
                SUM(reasoning_tokens) AS total_reasoning_tokens,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) = 'complete' THEN 1 ELSE 0 END) AS complete_requests,
                SUM(CASE WHEN LOWER(COALESCE(status, '')) LIKE 'disconnect%' THEN 1 ELSE 0 END) AS disconnect_requests
            ", false)
            ->where('event_at >=', $minSeenAt);

        if ($provider !== '') {
            $builder->where('provider', $provider);
        }

        $row = $builder->get()->getRowArray() ?? [];
        $totalRequests = (int) ($row['total_requests'] ?? 0);
        $inputTokens = (int) ($row['total_input_tokens'] ?? 0);
        $outputTokens = (int) ($row['total_output_tokens'] ?? 0);
        $completeRequests = (int) ($row['complete_requests'] ?? 0);
        $disconnectRequests = (int) ($row['disconnect_requests'] ?? 0);

        return [
            'total_requests' => $totalRequests,
            'total_input_tokens' => $inputTokens,
            'total_output_tokens' => $outputTokens,
            'total_tokens' => $inputTokens + $outputTokens,
            'total_cache_read_tokens' => (int) ($row['total_cache_read_tokens'] ?? 0),
            'total_reasoning_tokens' => (int) ($row['total_reasoning_tokens'] ?? 0),
            'complete_requests' => $completeRequests,
            'disconnect_requests' => $disconnectRequests,
            'other_requests' => max(0, $totalRequests - $completeRequests - $disconnectRequests),
            'success_rate_percent' => $totalRequests > 0 ? round(($completeRequests / $totalRequests) * 100, 2) : 0.0,
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
    private function accountUsageBreakdown(string $provider, string $minSeenAt, int $limit, int $grandTotalTokens = 0): array
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
            $row['usage_percent'] = $grandTotalTokens > 0
                ? round((($input + $output) / $grandTotalTokens) * 100, 2)
                : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function modelUsageBreakdown(string $provider, string $minSeenAt, int $limit, int $grandTotalTokens = 0): array
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
            $row['usage_percent'] = $grandTotalTokens > 0
                ? round((($input + $output) / $grandTotalTokens) * 100, 2)
                : 0.0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function statusBreakdown(int $totalRequests, int $completeRequests, int $disconnectRequests): array
    {
        $otherRequests = max(0, $totalRequests - $completeRequests - $disconnectRequests);
        $percent = static function (int $count) use ($totalRequests): float {
            if ($totalRequests <= 0) {
                return 0.0;
            }

            return round(($count / $totalRequests) * 100, 2);
        };

        return [
            [
                'status' => 'complete',
                'total_requests' => $completeRequests,
                'percentage' => $percent($completeRequests),
            ],
            [
                'status' => 'disconnect',
                'total_requests' => $disconnectRequests,
                'percentage' => $percent($disconnectRequests),
            ],
            [
                'status' => 'other',
                'total_requests' => $otherRequests,
                'percentage' => $percent($otherRequests),
            ],
        ];
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
