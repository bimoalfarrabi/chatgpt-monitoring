<?php

namespace App\Services;

use App\Models\AiRouterCollectorStateModel;
use App\Models\AiRouterUsageEventModel;

class RouterUsageCollectorService
{
    private AiRouterUsageEventModel $usageEvents;
    private AiRouterCollectorStateModel $collectorStates;
    private RouterAccountMappingService $mappingService;

    public function __construct()
    {
        $this->usageEvents = new AiRouterUsageEventModel();
        $this->collectorStates = new AiRouterCollectorStateModel();
        $this->mappingService = new RouterAccountMappingService();
    }

    /**
     * @return array{
     *     source:string,
     *     state_key:string,
     *     scanned:int,
     *     parsed:int,
     *     inserted:int,
     *     duplicates:int,
     *     invalid:int,
     *     skipped:int,
     *     cursor_offset:int,
     *     cursor_line:int
     * }
     */
    public function collectFromLogFile(string $sourcePath, string $provider = '9router', bool $resetCursor = false): array
    {
        $sourcePath = trim($sourcePath);
        if ($sourcePath === '') {
            throw new \InvalidArgumentException('Path log 9router belum diisi.');
        }

        $realPath = realpath($sourcePath) ?: $sourcePath;
        if (! is_file($realPath)) {
            throw new \RuntimeException('File log tidak ditemukan: ' . $realPath);
        }

        $handle = fopen($realPath, 'rb');
        if ($handle === false) {
            throw new \RuntimeException('Gagal membuka file log: ' . $realPath);
        }

        $provider = $this->normalizeProvider($provider);
        $stateKey = hash('sha1', 'file:' . $realPath);

        $state = $this->collectorStates->where('source_key', $stateKey)->first();

        $offset = $resetCursor ? 0 : (int) ($state['last_offset'] ?? 0);
        $lineNumber = $resetCursor ? 0 : (int) ($state['last_line_number'] ?? 0);

        $fileSize = filesize($realPath);
        if ($fileSize === false || $offset < 0 || $offset > $fileSize) {
            $offset = 0;
            $lineNumber = 0;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        $stats = [
            'scanned' => 0,
            'parsed' => 0,
            'inserted' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'skipped' => 0,
        ];

        while (($rawLine = fgets($handle)) !== false) {
            $lineNumber++;
            $stats['scanned']++;

            $line = $this->sanitizeLogLine($rawLine);
            if ($line === '') {
                $stats['skipped']++;
                continue;
            }

            if (! str_contains(strtoupper($line), '[USAGE]')) {
                $stats['skipped']++;
                continue;
            }

            $event = $this->parseUsageLine($line, $provider);
            if ($event === null) {
                $stats['invalid']++;
                continue;
            }

            $stats['parsed']++;

            $result = $this->insertUsageEvent(
                $event,
                $stateKey,
                $line,
                $line
            );

            if ($result === 'inserted') {
                $stats['inserted']++;
            } elseif ($result === 'duplicate') {
                $stats['duplicates']++;
            } else {
                $stats['invalid']++;
            }
        }

        $offsetAfter = ftell($handle);
        fclose($handle);

        $statePayload = [
            'source_key' => $stateKey,
            'source_path' => $realPath,
            'last_offset' => $offsetAfter !== false ? (int) $offsetAfter : $offset,
            'last_line_number' => $lineNumber,
            'last_collected_at' => date('Y-m-d H:i:s'),
        ];

        if ($state) {
            $this->collectorStates->update((int) $state['id'], $statePayload);
        } else {
            $this->collectorStates->insert($statePayload);
        }

        return [
            'source' => $realPath,
            'state_key' => $stateKey,
            'scanned' => $stats['scanned'],
            'parsed' => $stats['parsed'],
            'inserted' => $stats['inserted'],
            'duplicates' => $stats['duplicates'],
            'invalid' => $stats['invalid'],
            'skipped' => $stats['skipped'],
            'cursor_offset' => $statePayload['last_offset'],
            'cursor_line' => $lineNumber,
        ];
    }


    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array{
     *     source:string,
     *     state_key:string,
     *     scanned:int,
     *     parsed:int,
     *     inserted:int,
     *     duplicates:int,
     *     invalid:int,
     *     skipped:int,
     *     cursor_offset:int,
     *     cursor_line:int
     * }
     */
    public function ingestStructuredEvents(array $events, string $source = 'remote-shipper', string $defaultProvider = '9router'): array
    {
        $source = trim($source);
        if ($source === '') {
            $source = 'remote-shipper';
        }

        $stateKey = hash('sha1', 'ingest:' . $source);
        $state = $this->collectorStates->where('source_key', $stateKey)->first();

        $stats = [
            'scanned' => 0,
            'parsed' => 0,
            'inserted' => 0,
            'duplicates' => 0,
            'invalid' => 0,
            'skipped' => 0,
        ];

        foreach ($events as $index => $row) {
            $stats['scanned']++;
            if (! is_array($row)) {
                $stats['invalid']++;
                continue;
            }

            $event = $this->normalizeJsonUsageEvent($row, $defaultProvider);
            if ($event === null) {
                $stats['invalid']++;
                continue;
            }

            $stats['parsed']++;

            $rawLog = $this->buildRawLogPayload($row);

            $seed = (string) ($row['seed'] ?? ('ingest:' . $index . ':' . $rawLog));
            $eventHash = trim((string) ($row['event_hash'] ?? ''));
            $result = $this->insertUsageEvent(
                $event,
                $stateKey,
                $seed,
                $rawLog,
                $eventHash !== '' ? $eventHash : null
            );

            if ($result === 'inserted') {
                $stats['inserted']++;
            } elseif ($result === 'duplicate') {
                $stats['duplicates']++;
            } else {
                $stats['invalid']++;
            }
        }

        $statePayload = [
            'source_key' => $stateKey,
            'source_path' => $source,
            'last_offset' => 0,
            'last_line_number' => ((int) ($state['last_line_number'] ?? 0)) + $stats['scanned'],
            'last_collected_at' => date('Y-m-d H:i:s'),
        ];

        if ($state) {
            $this->collectorStates->update((int) $state['id'], $statePayload);
        } else {
            $this->collectorStates->insert($statePayload);
        }

        return [
            'source' => $source,
            'state_key' => $stateKey,
            'scanned' => $stats['scanned'],
            'parsed' => $stats['parsed'],
            'inserted' => $stats['inserted'],
            'duplicates' => $stats['duplicates'],
            'invalid' => $stats['invalid'],
            'skipped' => $stats['skipped'],
            'cursor_offset' => 0,
            'cursor_line' => $statePayload['last_line_number'],
        ];
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{
     *     provider:string,
     *     model:string,
     *     router_account_ref:string|null,
     *     account_email:string|null,
     *     input_tokens:int,
     *     output_tokens:int,
     *     cache_read_tokens:int,
     *     reasoning_tokens:int,
     *     duration_ms:int,
     *     status:string,
     *     event_at:string,
     *     meta:array<string,mixed>
     * }|null
     */
    private function normalizeJsonUsageEvent(array $row, string $defaultProvider): ?array
    {
        $provider = $this->normalizeProvider((string) ($row['provider'] ?? $defaultProvider));
        $model = strtolower(trim((string) ($row['model'] ?? $row['id'] ?? 'unknown')));

        $usage = isset($row['usage']) && is_array($row['usage']) ? $row['usage'] : [];

        $routerAccountRef = trim((string) (
            $row['router_account_ref']
                ?? $row['account_ref']
                ?? $row['account']
                ?? ''
        ));
        $routerAccountRef = $routerAccountRef !== '' ? $routerAccountRef : null;
        $accountEmail = trim((string) (
            $row['account_email']
                ?? $row['email']
                ?? ''
        ));
        if ($accountEmail !== '' && ! str_contains($accountEmail, '@')) {
            $accountEmail = '';
        }
        $accountEmail = $accountEmail !== '' ? strtolower($accountEmail) : null;

        $meta = isset($row['meta']) && is_array($row['meta']) ? $row['meta'] : [];
        if ($accountEmail === null && isset($meta['account_email'])) {
            $metaEmail = strtolower(trim((string) $meta['account_email']));
            if ($metaEmail !== '' && str_contains($metaEmail, '@')) {
                $accountEmail = $metaEmail;
            }
        }

        $inputTokens = $this->normalizeInteger((string) (
            $row['input_tokens']
                ?? $row['in']
                ?? $row['input']
                ?? $usage['prompt_tokens']
                ?? $usage['input_tokens']
                ?? 0
        ));

        $outputTokens = $this->normalizeInteger((string) (
            $row['output_tokens']
                ?? $row['out']
                ?? $row['output']
                ?? $usage['completion_tokens']
                ?? $usage['output_tokens']
                ?? 0
        ));

        $cacheReadTokens = $this->normalizeInteger((string) (
            $row['cache_read_tokens']
                ?? $row['cache_read']
                ?? $usage['cache_read_tokens']
                ?? $usage['cached_tokens']
                ?? 0
        ));

        $reasoningTokens = $this->normalizeInteger((string) (
            $row['reasoning_tokens']
                ?? $row['reasoning']
                ?? $usage['reasoning_tokens']
                ?? 0
        ));

        $durationMs = $this->normalizeInteger((string) (
            $row['duration_ms']
                ?? $row['latency']
                ?? 0
        ));

        $status = $this->normalizeStatus((string) ($row['status'] ?? 'success'));
        $eventAt = $this->normalizeDateTime((string) (
            $row['event_at']
                ?? $row['created_at']
                ?? $row['timestamp']
                ?? ''
        ));

        if ($routerAccountRef === null && $inputTokens === 0 && $outputTokens === 0 && $cacheReadTokens === 0 && $reasoningTokens === 0) {
            return null;
        }

        if ($model === '') {
            $model = 'unknown';
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'router_account_ref' => $routerAccountRef,
            'account_email' => $accountEmail,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'reasoning_tokens' => $reasoningTokens,
            'duration_ms' => $durationMs,
            'status' => $status,
            'event_at' => $eventAt,
            'meta' => $meta,
        ];
    }

    /**
     * @param array<string, mixed> $event
     */
    private function insertUsageEvent(array $event, string $stateKey, string $seed, string $rawLog, ?string $eventHashOverride = null): string
    {
        $eventHash = $eventHashOverride !== null && trim($eventHashOverride) !== ''
            ? trim($eventHashOverride)
            : $this->buildEventHash($event, $stateKey, $seed);
        $alreadyExists = $this->usageEvents->where('event_hash', $eventHash)->first();
        if ($alreadyExists) {
            return 'duplicate';
        }

        $routerAccountId = null;
        if ($event['router_account_ref'] !== null && trim((string) $event['router_account_ref']) !== '') {
            $mappingAttributes = [];
            $accountEmail = trim((string) ($event['account_email'] ?? ''));
            if ($accountEmail !== '' && str_contains($accountEmail, '@')) {
                $mappingAttributes['email'] = strtolower($accountEmail);
            }

            $routerAccount = $this->mappingService->findOrCreateByRef(
                (string) $event['provider'],
                (string) $event['router_account_ref'],
                $mappingAttributes
            );

            $routerAccountId = isset($routerAccount['id']) ? (int) $routerAccount['id'] : null;

            $this->mappingService->touchSession(
                (string) $event['provider'],
                (string) $event['router_account_ref'],
                $routerAccountId,
                [
                    'email' => $accountEmail,
                    'input_tokens' => (int) $event['input_tokens'],
                    'output_tokens' => (int) $event['output_tokens'],
                    'cache_read_tokens' => (int) $event['cache_read_tokens'],
                    'reasoning_tokens' => (int) $event['reasoning_tokens'],
                    'duration_ms' => (int) $event['duration_ms'],
                    'status' => (string) $event['status'],
                    'event_at' => (string) $event['event_at'],
                ]
            );
        }

        $inserted = $this->usageEvents->insert([
            'router_account_id' => $routerAccountId,
            'provider' => $event['provider'],
            'model' => $event['model'],
            'router_account_ref' => $event['router_account_ref'],
            'input_tokens' => $event['input_tokens'],
            'output_tokens' => $event['output_tokens'],
            'cache_read_tokens' => $event['cache_read_tokens'],
            'reasoning_tokens' => $event['reasoning_tokens'],
            'duration_ms' => $event['duration_ms'],
            'status' => $event['status'],
            'event_hash' => $eventHash,
            'event_at' => $event['event_at'],
            'raw_log' => $rawLog,
        ]);

        return $inserted ? 'inserted' : 'invalid';
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildRawLogPayload(array $row): string
    {
        $rawLog = trim((string) ($row['raw_log'] ?? ''));
        $hasMeta = isset($row['meta']) && is_array($row['meta']) && $row['meta'] !== [];
        $accountEmail = trim((string) ($row['account_email'] ?? $row['email'] ?? ''));

        if ($rawLog !== '' && ! $hasMeta && $accountEmail === '') {
            return $rawLog;
        }

        $payload = [];
        if ($rawLog !== '') {
            $payload['raw_log'] = $rawLog;
        }
        if ($accountEmail !== '' && str_contains($accountEmail, '@')) {
            $payload['account_email'] = strtolower($accountEmail);
        }
        if ($hasMeta) {
            $payload['meta'] = $row['meta'];
        }

        if ($payload === []) {
            $encoded = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return is_string($encoded) ? $encoded : '[structured-event]';
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)) {
            return $rawLog !== '' ? $rawLog : '[structured-event]';
        }

        return $encoded;
    }

    /**
     * @param array<string, mixed> $event
     */
    private function buildEventHash(array $event, string $stateKey, string $seed): string
    {
        $hashPayload = [
            'state_key' => $stateKey,
            'provider' => (string) ($event['provider'] ?? ''),
            'model' => (string) ($event['model'] ?? ''),
            'router_account_ref' => (string) ($event['router_account_ref'] ?? ''),
            'input_tokens' => (int) ($event['input_tokens'] ?? 0),
            'output_tokens' => (int) ($event['output_tokens'] ?? 0),
            'cache_read_tokens' => (int) ($event['cache_read_tokens'] ?? 0),
            'reasoning_tokens' => (int) ($event['reasoning_tokens'] ?? 0),
            'duration_ms' => (int) ($event['duration_ms'] ?? 0),
            'status' => (string) ($event['status'] ?? ''),
            'event_at' => (string) ($event['event_at'] ?? ''),
            'seed' => $seed,
        ];

        return hash('sha256', json_encode($hashPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array{
     *     provider:string,
     *     model:string,
     *     router_account_ref:string|null,
     *     input_tokens:int,
     *     output_tokens:int,
     *     cache_read_tokens:int,
     *     reasoning_tokens:int,
     *     duration_ms:int,
     *     status:string,
     *     event_at:string
     * }|null
     */
    public function parseUsageLine(string $line, string $defaultProvider = '9router'): ?array
    {
        if (! preg_match('/\[USAGE\]\s*([^|\n]+)(?:\|(.*))?$/i', $line, $matches)) {
            return null;
        }

        $head = trim((string) ($matches[1] ?? ''));
        $tail = trim((string) ($matches[2] ?? ''));

        $provider = $this->normalizeProvider($defaultProvider);
        $model = strtolower(trim($head));

        $routerAccountRef = null;
        $inputTokens = 0;
        $outputTokens = 0;
        $cacheReadTokens = 0;
        $reasoningTokens = 0;
        $durationMs = 0;
        $status = 'success';

        if ($tail !== '') {
            $parts = explode('|', $tail);
            foreach ($parts as $part) {
                $segment = trim($part);
                if ($segment === '' || ! str_contains($segment, '=')) {
                    continue;
                }

                [$rawKey, $rawValue] = array_map('trim', explode('=', $segment, 2));
                $key = strtolower($rawKey);
                $value = trim($rawValue);

                switch ($key) {
                    case 'provider':
                        $provider = $this->normalizeProvider($value);
                        break;
                    case 'model':
                        if ($value !== '') {
                            $model = strtolower($value);
                        }
                        break;
                    case 'account':
                    case 'account_ref':
                    case 'router_account_ref':
                        $routerAccountRef = $value !== '' ? $value : null;
                        break;
                    case 'in':
                    case 'input':
                    case 'input_tokens':
                        $inputTokens = $this->normalizeInteger($value);
                        break;
                    case 'out':
                    case 'output':
                    case 'output_tokens':
                        $outputTokens = $this->normalizeInteger($value);
                        break;
                    case 'cache_read':
                    case 'cache_read_tokens':
                        $cacheReadTokens = $this->normalizeInteger($value);
                        break;
                    case 'reasoning':
                    case 'reasoning_tokens':
                        $reasoningTokens = $this->normalizeInteger($value);
                        break;
                    case 'duration':
                    case 'duration_ms':
                    case 'latency':
                        $durationMs = $this->normalizeInteger($value);
                        break;
                    case 'status':
                        $status = $this->normalizeStatus($value);
                        break;
                }
            }
        }

        if ($model === '') {
            $model = 'unknown';
        }

        if ($routerAccountRef === null && $inputTokens === 0 && $outputTokens === 0 && $cacheReadTokens === 0 && $reasoningTokens === 0) {
            return null;
        }

        return [
            'provider' => $provider,
            'model' => $model,
            'router_account_ref' => $routerAccountRef,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheReadTokens,
            'reasoning_tokens' => $reasoningTokens,
            'duration_ms' => $durationMs,
            'status' => $status,
            'event_at' => $this->extractEventDateTime($line),
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        $value = strtolower(trim($provider));

        return $value !== '' ? $value : '9router';
    }

    private function normalizeStatus(string $status): string
    {
        $value = strtolower(trim($status));

        return $value !== '' ? $value : 'success';
    }

    private function normalizeInteger(string $value): int
    {
        $normalized = preg_replace('/[^0-9\-]/', '', $value);
        $number = (int) ($normalized ?? '0');

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

    private function extractEventDateTime(string $line): string
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})/', $line, $matches)) {
            $timestamp = strtotime((string) $matches[1]);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        if (preg_match('/^\[(\d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $timestamp = strtotime(date('Y-m-d') . ' ' . $matches[1]);
            if ($timestamp !== false) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        return date('Y-m-d H:i:s');
    }

    private function sanitizeLogLine(string $raw): string
    {
        $line = str_replace(["\r", "\n"], '', $raw);
        $line = (string) preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $line);
        $line = (string) preg_replace('/\x1B\][^\x07]*(\x07|\x1B\\\\)/', '', $line);
        $line = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $line);

        return trim($line);
    }
}
