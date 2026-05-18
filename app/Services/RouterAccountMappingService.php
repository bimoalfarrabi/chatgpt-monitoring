<?php

namespace App\Services;

use App\Models\AiRouterAccountModel;
use App\Models\AiRouterAccountSessionModel;
use App\Models\AiRouterUsageEventModel;

class RouterAccountMappingService
{
    private AiRouterAccountModel $routerAccounts;
    private AiRouterAccountSessionModel $sessions;
    private AiRouterUsageEventModel $usageEvents;

    public function __construct()
    {
        $this->routerAccounts = new AiRouterAccountModel();
        $this->sessions = new AiRouterAccountSessionModel();
        $this->usageEvents = new AiRouterUsageEventModel();
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    public function findOrCreateByRef(string $provider, string $routerAccountRef, array $attributes = []): array
    {
        $provider = $this->normalizeProvider($provider);
        $routerAccountRef = $this->normalizeRouterAccountRef($routerAccountRef);

        if ($routerAccountRef === '') {
            throw new \InvalidArgumentException('router_account_ref tidak boleh kosong.');
        }

        $row = $this->routerAccounts
            ->where('provider', $provider)
            ->where('router_account_ref', $routerAccountRef)
            ->first();

        $now = date('Y-m-d H:i:s');
        $updateData = [
            'last_seen_at' => $now,
        ];

        if (isset($attributes['email'])) {
            $email = trim((string) $attributes['email']);
            if ($email !== '') {
                $updateData['email'] = strtolower($email);
            }
        }

        if (isset($attributes['status'])) {
            $updateData['status'] = $this->normalizeStatus((string) $attributes['status']);
        }

        if (isset($attributes['account_plan'])) {
            $updateData['account_plan'] = $this->normalizePlan((string) $attributes['account_plan']);
        }

        if (! $row) {
            $insertId = $this->routerAccounts->insert(array_merge([
                'provider' => $provider,
                'router_account_ref' => $routerAccountRef,
                'status' => 'unknown',
                'account_plan' => 'unknown',
                'mapping_status' => 'unmapped',
                'last_seen_at' => $now,
            ], $updateData), true);

            return $this->routerAccounts->find((int) $insertId) ?? [];
        }

        if (($row['mapping_status'] ?? 'unmapped') === '') {
            $updateData['mapping_status'] = 'unmapped';
        }

        $this->routerAccounts->update((int) $row['id'], $updateData);

        return $this->routerAccounts->find((int) $row['id']) ?? $row;
    }

    /**
     * Sinkronkan ref account dari usage event yang belum punya relation.
     *
     * @return array{refs_checked:int, accounts_created:int, events_linked:int}
     */
    public function syncFromUsageEvents(int $limit = 2000): array
    {
        $limit = max(1, $limit);

        $rows = $this->usageEvents
            ->select('provider, router_account_ref')
            ->where('router_account_ref IS NOT NULL', null, false)
            ->where('router_account_ref !=', '')
            ->groupBy('provider, router_account_ref')
            ->limit($limit)
            ->findAll();

        $refsChecked = 0;
        $accountsCreated = 0;
        $eventsLinked = 0;

        foreach ($rows as $row) {
            $provider = $this->normalizeProvider((string) ($row['provider'] ?? '9router'));
            $ref = trim((string) ($row['router_account_ref'] ?? ''));
            if ($ref === '') {
                continue;
            }

            $refsChecked++;

            $existing = $this->routerAccounts
                ->where('provider', $provider)
                ->where('router_account_ref', $ref)
                ->first();

            if (! $existing) {
                $existing = $this->findOrCreateByRef($provider, $ref);
                $accountsCreated++;
            }

            $routerAccountId = (int) ($existing['id'] ?? 0);
            if ($routerAccountId <= 0) {
                continue;
            }

            $builder = $this->usageEvents
                ->where('provider', $provider)
                ->where('router_account_ref', $ref)
                ->groupStart()
                ->where('router_account_id', null)
                ->orWhere('router_account_id', 0)
                ->groupEnd();

            $countBefore = $builder->countAllResults(false);
            if ($countBefore > 0) {
                $builder->set('router_account_id', $routerAccountId)->update();
                $eventsLinked += $countBefore;
            }
        }

        return [
            'refs_checked' => $refsChecked,
            'accounts_created' => $accountsCreated,
            'events_linked' => $eventsLinked,
        ];
    }

    /**
     * @param array<string, mixed> $usage
     */
    public function touchSession(
        string $provider,
        string $routerAccountRef,
        ?int $routerAccountId,
        array $usage
    ): void {
        $provider = $this->normalizeProvider($provider);
        $routerAccountRef = $this->normalizeRouterAccountRef($routerAccountRef);
        if ($routerAccountRef === '') {
            return;
        }

        $email = strtolower(trim((string) ($usage['email'] ?? '')));
        if ($email !== '' && ! str_contains($email, '@')) {
            $email = '';
        }

        $eventAt = $this->normalizeDateTime((string) ($usage['event_at'] ?? ''));
        $status = $this->normalizeEventStatus((string) ($usage['status'] ?? 'success'));

        $inputTokens = $this->normalizeUnsignedInt($usage['input_tokens'] ?? 0);
        $outputTokens = $this->normalizeUnsignedInt($usage['output_tokens'] ?? 0);
        $cacheReadTokens = $this->normalizeUnsignedInt($usage['cache_read_tokens'] ?? 0);
        $reasoningTokens = $this->normalizeUnsignedInt($usage['reasoning_tokens'] ?? 0);
        $durationMs = $this->normalizeUnsignedInt($usage['duration_ms'] ?? 0);

        $session = $this->sessions
            ->where('provider', $provider)
            ->where('router_account_ref', $routerAccountRef)
            ->first();

        if (! $session) {
            $this->sessions->insert([
                'router_account_id' => $routerAccountId,
                'provider' => $provider,
                'router_account_ref' => $routerAccountRef,
                'email' => $email !== '' ? $email : null,
                'first_seen_at' => $eventAt,
                'last_seen_at' => $eventAt,
                'total_requests' => 1,
                'total_input_tokens' => $inputTokens,
                'total_output_tokens' => $outputTokens,
                'total_cache_read_tokens' => $cacheReadTokens,
                'total_reasoning_tokens' => $reasoningTokens,
                'total_duration_ms' => $durationMs,
                'last_status' => $status,
            ]);

            return;
        }

        $update = [
            'router_account_id' => $routerAccountId ?: (($session['router_account_id'] ?? null) ?: null),
            'last_status' => $status,
            'total_requests' => ((int) ($session['total_requests'] ?? 0)) + 1,
            'total_input_tokens' => ((int) ($session['total_input_tokens'] ?? 0)) + $inputTokens,
            'total_output_tokens' => ((int) ($session['total_output_tokens'] ?? 0)) + $outputTokens,
            'total_cache_read_tokens' => ((int) ($session['total_cache_read_tokens'] ?? 0)) + $cacheReadTokens,
            'total_reasoning_tokens' => ((int) ($session['total_reasoning_tokens'] ?? 0)) + $reasoningTokens,
            'total_duration_ms' => ((int) ($session['total_duration_ms'] ?? 0)) + $durationMs,
        ];

        $currentFirstSeen = (string) ($session['first_seen_at'] ?? '');
        $currentLastSeen = (string) ($session['last_seen_at'] ?? '');
        $update['first_seen_at'] = $this->minDateTime($currentFirstSeen, $eventAt);
        $update['last_seen_at'] = $this->maxDateTime($currentLastSeen, $eventAt);

        if ($email !== '') {
            $update['email'] = $email;
        }

        $this->sessions->update((int) $session['id'], $update);
    }

    private function normalizeProvider(string $provider): string
    {
        $value = strtolower(trim($provider));

        return $value !== '' ? $value : '9router';
    }

    private function normalizeRouterAccountRef(string $value): string
    {
        $normalized = str_replace(["\r", "\n"], '', $value);
        $normalized = (string) preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $normalized);
        $normalized = (string) preg_replace('/\x1B\][^\x07]*(\x07|\x1B\\\\)/', '', $normalized);
        $normalized = (string) preg_replace('/[\x00-\x1F\x7F]/', '', $normalized);
        $normalized = trim($normalized);
        $normalized = (string) preg_replace('/[^a-zA-Z0-9._:-]/', '', $normalized);

        return $normalized;
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return in_array($value, ['active', 'expired', 'disabled', 'cooldown', 'unknown'], true)
            ? $value
            : 'unknown';
    }

    private function normalizePlan(string $plan): string
    {
        $value = strtolower(trim($plan));

        return in_array($value, ['free', 'plus', 'pro', 'team', 'unknown'], true)
            ? $value
            : 'unknown';
    }

    private function normalizeEventStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return $value !== '' ? $value : 'success';
    }

    /**
     * @param mixed $value
     */
    private function normalizeUnsignedInt($value): int
    {
        $number = (int) $value;

        return $number > 0 ? $number : 0;
    }

    private function normalizeDateTime(string $value): string
    {
        $raw = trim($value);
        if ($raw === '') {
            return date('Y-m-d H:i:s');
        }

        $timestamp = strtotime($raw);
        if ($timestamp === false) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function minDateTime(string $a, string $b): string
    {
        $aTs = strtotime($a);
        $bTs = strtotime($b);

        if ($aTs === false) {
            return $b;
        }
        if ($bTs === false) {
            return $a;
        }

        return $aTs <= $bTs ? date('Y-m-d H:i:s', $aTs) : date('Y-m-d H:i:s', $bTs);
    }

    private function maxDateTime(string $a, string $b): string
    {
        $aTs = strtotime($a);
        $bTs = strtotime($b);

        if ($aTs === false) {
            return $b;
        }
        if ($bTs === false) {
            return $a;
        }

        return $aTs >= $bTs ? date('Y-m-d H:i:s', $aTs) : date('Y-m-d H:i:s', $bTs);
    }
}
