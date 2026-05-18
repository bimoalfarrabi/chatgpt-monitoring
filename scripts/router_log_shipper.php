#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Router Log Shipper
 *
 * Flow:
 * 9router log (local) -> parse usage events -> POST HTTPS -> GPT Tracker /api/router/ingest
 */

final class RouterLogShipper
{
    /** @var array<string, mixed> */
    private array $config;

    /** @var array<string, string> */
    private array $modelContextByProvider = [];

    /** @var array<string, string> */
    private array $accountEmailByProvider = [];

    /** @var array<string, array<string, mixed>> */
    private array $requestMetaByProvider = [];

    /** @var array<string, int[]> */
    private array $pendingUsageIndexesByKey = [];

    /** @var array<string, mixed> */
    private array $state = [];

    /** @var array<int, array<string, mixed>> */
    private array $events = [];

    private int $lineNumber = 0;
    private int $scannedLines = 0;
    private int $usageLines = 0;

    public function __construct(array $argv)
    {
        $this->config = $this->buildConfig($argv);
        $this->state = $this->loadState();
    }

    public function run(): int
    {
        $logPath = (string) $this->config['log_path'];
        if (! is_file($logPath)) {
            $this->writeErr('Log file tidak ditemukan: ' . $logPath);
            return 1;
        }

        $handle = fopen($logPath, 'rb');
        if ($handle === false) {
            $this->writeErr('Gagal membuka log file: ' . $logPath);
            return 1;
        }

        $offset = (int) ($this->state['offset'] ?? 0);
        $this->lineNumber = (int) ($this->state['line_number'] ?? 0);

        $fileSize = filesize($logPath);
        if ($fileSize === false || $offset < 0 || $offset > $fileSize) {
            $offset = 0;
            $this->lineNumber = 0;
        }

        if ($offset > 0) {
            fseek($handle, $offset);
        }

        while (($raw = fgets($handle)) !== false) {
            $this->lineNumber++;
            $this->scannedLines++;

            $line = $this->sanitizeLine($raw);
            if ($line === '') {
                continue;
            }

            $this->captureContext($line);

            $event = $this->parseUsageLine($line);
            if ($event === null) {
                continue;
            }

            $this->usageLines++;
            $this->events[] = $event;

            $eventIndex = count($this->events) - 1;
            $this->registerPendingUsage($eventIndex, (string) $event['provider'], (string) $event['model']);

            if (count($this->events) >= (int) $this->config['max_events']) {
                break;
            }
        }

        $offsetAfter = ftell($handle);
        fclose($handle);

        if ($offsetAfter === false) {
            $offsetAfter = $offset;
        }

        if ($this->events === []) {
            $this->saveState((int) $offsetAfter, $this->lineNumber);
            $this->writeOut('No usage event found. State updated.');
            $this->writeOut('Scanned lines: ' . $this->scannedLines);
            return 0;
        }

        if ((bool) $this->config['dry_run']) {
            $this->writeOut('Dry run mode: payload not sent.');
            $this->writeOut('Events prepared: ' . count($this->events));
            $this->writeOut('Usage lines: ' . $this->usageLines);
            $this->writeOut('Scanned lines: ' . $this->scannedLines);
            $this->printPreview();
            return 0;
        }

        $response = $this->postEvents($this->events);

        if ($response['ok'] !== true) {
            $this->writeErr('POST gagal: ' . $response['message']);
            $this->writeErr('State tidak diupdate agar event bisa dicoba ulang.');
            return 2;
        }

        $this->saveState((int) $offsetAfter, $this->lineNumber);

        $this->writeOut('POST success: ' . $response['message']);
        $this->writeOut('Events sent: ' . count($this->events));
        $this->writeOut('Usage lines: ' . $this->usageLines);
        $this->writeOut('Scanned lines: ' . $this->scannedLines);
        $this->writeOut('New offset: ' . (int) $offsetAfter);

        return 0;
    }

    /**
     * @param array<int, string> $argv
     *
     * @return array<string, mixed>
     */
    private function buildConfig(array $argv): array
    {
        $options = $this->parseArgs($argv);

        $logPath = (string) ($options['log'] ?? getenv('ROUTER_SHIPPER_LOG_PATH') ?: '');
        $endpoint = (string) ($options['endpoint'] ?? getenv('ROUTER_SHIPPER_ENDPOINT') ?: '');
        $ingestKey = (string) ($options['key'] ?? getenv('ROUTER_SHIPPER_INGEST_KEY') ?: '');

        if ($logPath === '' || $endpoint === '') {
            $this->writeErr('Usage: php scripts/router_log_shipper.php --log=/path/9router.log --endpoint=https://domain/api/router/ingest [--key=secret] [--source=nama-device] [--provider=9router] [--state=/path/state.json] [--max-events=500] [--timeout=20] [--dry-run]');
            exit(1);
        }

        $stateFileDefault = dirname(__FILE__) . '/.router_shipper_state.json';

        $timeout = (int) ($options['timeout'] ?? getenv('ROUTER_SHIPPER_TIMEOUT') ?: 20);
        if ($timeout <= 0) {
            $timeout = 20;
        }

        $maxEvents = (int) ($options['max-events'] ?? getenv('ROUTER_SHIPPER_MAX_EVENTS') ?: 500);
        if ($maxEvents <= 0) {
            $maxEvents = 500;
        }

        $source = (string) ($options['source'] ?? getenv('ROUTER_SHIPPER_SOURCE') ?: gethostname() ?: 'local-shipper');
        $provider = strtolower(trim((string) ($options['provider'] ?? getenv('ROUTER_SHIPPER_PROVIDER') ?: '9router')));

        return [
            'log_path' => $logPath,
            'endpoint' => $endpoint,
            'ingest_key' => $ingestKey,
            'source' => $source,
            'provider' => $provider !== '' ? $provider : '9router',
            'state_file' => (string) ($options['state'] ?? getenv('ROUTER_SHIPPER_STATE_FILE') ?: $stateFileDefault),
            'max_events' => $maxEvents,
            'timeout' => $timeout,
            'dry_run' => isset($options['dry-run']),
        ];
    }

    /**
     * @param array<int, string> $argv
     *
     * @return array<string, string|bool>
     */
    private function parseArgs(array $argv): array
    {
        $result = [];

        foreach ($argv as $idx => $arg) {
            if ($idx === 0 || ! is_string($arg)) {
                continue;
            }

            $arg = trim($arg);
            if (! str_starts_with($arg, '--')) {
                continue;
            }

            $raw = substr($arg, 2);
            if ($raw === 'dry-run') {
                $result['dry-run'] = true;
                continue;
            }

            if (! str_contains($raw, '=')) {
                $result[$raw] = true;
                continue;
            }

            [$key, $value] = explode('=', $raw, 2);
            $result[$key] = trim($value);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function loadState(): array
    {
        $stateFile = (string) $this->config['state_file'];
        if (! is_file($stateFile)) {
            return [
                'offset' => 0,
                'line_number' => 0,
            ];
        }

        $raw = file_get_contents($stateFile);
        if (! is_string($raw) || trim($raw) === '') {
            return [
                'offset' => 0,
                'line_number' => 0,
            ];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [
                'offset' => 0,
                'line_number' => 0,
            ];
        }

        return $decoded;
    }

    private function saveState(int $offset, int $lineNumber): void
    {
        $stateFile = (string) $this->config['state_file'];
        $payload = [
            'offset' => $offset,
            'line_number' => $lineNumber,
            'updated_at' => date('c'),
        ];

        file_put_contents($stateFile, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    private function sanitizeLine(string $raw): string
    {
        $line = str_replace(["\r", "\n"], '', $raw);

        // ANSI CSI sequences (colors/cursor)
        $line = (string) preg_replace('/\x1B\[[0-?]*[ -\/]*[@-~]/', '', $line);
        // ANSI OSC sequences
        $line = (string) preg_replace('/\x1B\][^\x07]*(\x07|\x1B\\\\)/', '', $line);
        // Strip remaining control chars except horizontal tab
        $line = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $line);

        return trim($line);
    }

    private function captureContext(string $line): void
    {
        $this->captureModelContext($line);
        $this->captureAccountEmailContext($line);
        $this->captureRouteContext($line);
        $this->capturePostContext($line);
        $this->captureConnectionContext($line);
        $this->captureStreamContext($line);
    }

    private function captureModelContext(string $line): void
    {
        if (preg_match('/^\[(\d{2}:\d{2}:\d{2})\].*\[REQUEST\]\s+([A-Z0-9_-]+)\s*\|\s*([^|]+?)\s*\|/i', $line, $m)) {
            $provider = $this->normalizeProviderToken((string) $m[2]);
            $model = strtolower(trim((string) $m[3]));
            if ($provider !== '' && $model !== '') {
                $this->modelContextByProvider[$provider] = $model;
            }

            return;
        }

        if (preg_match('/^\[(\d{2}:\d{2}:\d{2})\].*POST\s+\/v1\/responses\s*\|\s*([^|]+?)\s*\|/i', $line, $m)) {
            $rawModel = strtolower(trim((string) $m[2]));
            if ($rawModel === '') {
                return;
            }

            $provider = $this->inferProviderFromModel($rawModel);
            if ($provider !== '') {
                $this->modelContextByProvider[$provider] = $rawModel;
            }
        }
    }

    private function captureAccountEmailContext(string $line): void
    {
        if (! preg_match('/Using\s+([a-z0-9_-]+)\s+account:\s*([^\s]+@[^\s]+)/i', $line, $m)) {
            return;
        }

        $provider = $this->normalizeProviderToken((string) $m[1]);
        $email = strtolower(trim((string) $m[2]));

        if ($provider === '' || $email === '') {
            return;
        }

        $this->accountEmailByProvider[$provider] = $email;

        $meta = $this->requestMetaByProvider[$provider] ?? [];
        $meta['account_email'] = $email;
        $this->requestMetaByProvider[$provider] = $meta;
    }

    private function captureRouteContext(string $line): void
    {
        if (! preg_match('/\[ROUTING\]\s+([^|]+?)\s*→\s*([^|]+)$/u', $line, $m)) {
            return;
        }

        $fromModel = strtolower(trim((string) $m[1]));
        $toModel = strtolower(trim((string) $m[2]));

        if ($toModel === '' && $fromModel === '') {
            return;
        }

        $provider = $this->inferProviderFromModel($toModel !== '' ? $toModel : $fromModel);
        if ($provider === '') {
            $provider = (string) $this->config['provider'];
        }

        if ($toModel !== '') {
            $this->modelContextByProvider[$provider] = $toModel;
        }

        $meta = $this->requestMetaByProvider[$provider] ?? [];
        if ($fromModel !== '') {
            $meta['route_from_model'] = $fromModel;
        }
        if ($toModel !== '') {
            $meta['route_to_model'] = $toModel;
        }

        $this->requestMetaByProvider[$provider] = $meta;
    }

    private function capturePostContext(string $line): void
    {
        if (! preg_match('/POST\s+\/v1\/responses\s*\|\s*([^|]+?)\s*\|\s*(\d+)\s+msgs\s*\|\s*(\d+)\s+tools\s*\|\s*effort=([^\s|]+)/i', $line, $m)) {
            return;
        }

        $rawModel = strtolower(trim((string) $m[1]));
        if ($rawModel === '') {
            return;
        }

        $provider = $this->inferProviderFromModel($rawModel);
        if ($provider === '') {
            $provider = (string) $this->config['provider'];
        }

        $this->modelContextByProvider[$provider] = $rawModel;

        $meta = $this->requestMetaByProvider[$provider] ?? [];
        $meta['request_messages'] = (int) $m[2];
        $meta['request_tools'] = (int) $m[3];
        $meta['reasoning_effort'] = strtolower(trim((string) $m[4]));
        $this->requestMetaByProvider[$provider] = $meta;
    }

    private function captureConnectionContext(string $line): void
    {
        if (! preg_match('/\[AUTH\]\s+([A-Z0-9_-]+)\s*\|\s*available:\s*(\d+)\/(\d+)/i', $line, $m)) {
            return;
        }

        $provider = $this->normalizeProviderToken((string) $m[1]);
        if ($provider === '') {
            return;
        }

        $meta = $this->requestMetaByProvider[$provider] ?? [];
        $meta['connection_available'] = (int) $m[2];
        $meta['connection_total'] = (int) $m[3];
        $this->requestMetaByProvider[$provider] = $meta;
    }

    private function captureStreamContext(string $line): void
    {
        if (! preg_match('/^\[(\d{2}:\d{2}:\d{2})\].*\[STREAM\]\s+([A-Z0-9_-]+)\s*\|\s*([^|]+?)\s*\|\s*(\d+)ms\s*\|\s*(.+)$/i', $line, $m)) {
            return;
        }

        $provider = $this->normalizeProviderToken((string) $m[2]);
        $model = strtolower(trim((string) $m[3]));
        $durationMs = (int) $m[4];
        $statusRaw = trim((string) $m[5]);
        $status = $this->normalizeStreamStatus($statusRaw);

        $eventAt = date('Y-m-d') . ' ' . trim((string) $m[1]);

        $this->applyStreamMetaToPendingUsage($provider, $model, $durationMs, $status, $statusRaw, $eventAt);
    }

    private function applyStreamMetaToPendingUsage(string $provider, string $model, int $durationMs, string $status, string $statusRaw, string $eventAt): void
    {
        $candidates = [];

        $key = $this->buildUsageKey($provider, $model);
        if (isset($this->pendingUsageIndexesByKey[$key])) {
            $candidates[] = $key;
        }

        // fallback ke model context saat ini jika model stream tidak match persis
        $contextModel = $this->modelContextByProvider[$provider] ?? '';
        if ($contextModel !== '') {
            $contextKey = $this->buildUsageKey($provider, $contextModel);
            if (! in_array($contextKey, $candidates, true) && isset($this->pendingUsageIndexesByKey[$contextKey])) {
                $candidates[] = $contextKey;
            }
        }

        // fallback paling longgar per-provider
        $wildcardKey = $this->buildUsageKey($provider, '*');
        if (! in_array($wildcardKey, $candidates, true) && isset($this->pendingUsageIndexesByKey[$wildcardKey])) {
            $candidates[] = $wildcardKey;
        }

        foreach ($candidates as $candidateKey) {
            $index = $this->shiftNextPendingIndex($candidateKey);
            if ($index === null) {
                continue;
            }

            if (! isset($this->events[$index])) {
                continue;
            }

            $event = $this->events[$index];
            $event['duration_ms'] = $durationMs > 0 ? $durationMs : (int) ($event['duration_ms'] ?? 0);
            if ($status !== '') {
                $event['status'] = $status;
            }
            $event['event_at'] = $eventAt;

            $meta = isset($event['meta']) && is_array($event['meta']) ? $event['meta'] : [];
            $meta['stream_status_raw'] = $statusRaw;
            $event['meta'] = $meta;

            $this->events[$index] = $event;
            return;
        }
    }

    private function shiftNextPendingIndex(string $key): ?int
    {
        if (! isset($this->pendingUsageIndexesByKey[$key]) || $this->pendingUsageIndexesByKey[$key] === []) {
            return null;
        }

        $index = array_shift($this->pendingUsageIndexesByKey[$key]);
        if ($this->pendingUsageIndexesByKey[$key] === []) {
            unset($this->pendingUsageIndexesByKey[$key]);
        }

        return is_int($index) ? $index : null;
    }

    private function registerPendingUsage(int $eventIndex, string $provider, string $model): void
    {
        $keys = [
            $this->buildUsageKey($provider, $model),
            $this->buildUsageKey($provider, '*'),
        ];

        foreach ($keys as $key) {
            if (! isset($this->pendingUsageIndexesByKey[$key])) {
                $this->pendingUsageIndexesByKey[$key] = [];
            }

            $this->pendingUsageIndexesByKey[$key][] = $eventIndex;
        }
    }

    private function buildUsageKey(string $provider, string $model): string
    {
        $provider = $provider !== '' ? $provider : (string) $this->config['provider'];
        $model = strtolower(trim($model));

        return $provider . '|' . ($model !== '' ? $model : '*');
    }

    private function normalizeStreamStatus(string $statusRaw): string
    {
        $value = strtolower(trim($statusRaw));
        if ($value === '') {
            return 'success';
        }

        if (str_starts_with($value, 'disconnect')) {
            return 'disconnect';
        }

        if (str_starts_with($value, 'complete')) {
            return 'complete';
        }

        if (str_starts_with($value, 'error')) {
            return 'error';
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseUsageLine(string $line): ?array
    {
        if (! preg_match('/^\[(\d{2}:\d{2}:\d{2})\].*\[USAGE\]\s+([A-Z0-9_-]+)\s*\|\s*in=(\d+)\s*\|\s*out=(\d+)\s*\|\s*account=([^|]+)(.*)$/i', $line, $m)) {
            return null;
        }

        $time = (string) $m[1];
        $providerToken = (string) $m[2];
        $provider = $this->normalizeProviderToken($providerToken);
        $inputTokens = (int) $m[3];
        $outputTokens = (int) $m[4];
        $accountRef = trim((string) $m[5]);
        $tail = (string) ($m[6] ?? '');

        $cacheRead = $this->extractIntField($tail, 'cache_read');
        $reasoning = $this->extractIntField($tail, 'reasoning');

        $model = $this->modelContextByProvider[$provider] ?? 'unknown';
        $eventAt = date('Y-m-d') . ' ' . $time;

        $seed = $this->config['source'] . '|' . $this->lineNumber . '|' . $line;
        $eventHash = hash('sha256', $seed);

        $event = [
            'provider' => $provider,
            'model' => $model,
            'router_account_ref' => $accountRef,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheRead,
            'reasoning_tokens' => $reasoning,
            'duration_ms' => 0,
            'status' => 'success',
            'event_at' => $eventAt,
            'event_hash' => $eventHash,
            'seed' => 'line:' . $this->lineNumber,
            'raw_log' => $line,
        ];

        $email = strtolower(trim((string) ($this->accountEmailByProvider[$provider] ?? '')));
        if ($email !== '' && str_contains($email, '@')) {
            $event['account_email'] = $email;
        }

        $meta = $this->requestMetaByProvider[$provider] ?? [];
        if ($meta !== []) {
            $event['meta'] = $meta;
        }

        return $event;
    }

    private function extractIntField(string $tail, string $field): int
    {
        if (preg_match('/\b' . preg_quote($field, '/') . '=(\d+)/i', $tail, $m)) {
            return (int) ($m[1] ?? 0);
        }

        return 0;
    }

    private function normalizeProviderToken(string $token): string
    {
        $value = strtolower(trim($token));
        if ($value === 'codex') {
            return 'codex';
        }

        if ($value === 'openai') {
            return 'openai';
        }

        return $value !== '' ? $value : (string) $this->config['provider'];
    }

    private function inferProviderFromModel(string $rawModel): string
    {
        $normalized = strtolower(trim($rawModel));

        if (str_starts_with($normalized, 'cx/')) {
            return 'codex';
        }

        if (str_starts_with($normalized, 'kr/')) {
            return 'kroq';
        }

        if (str_starts_with($normalized, 'ag/')) {
            return 'agent';
        }

        if (str_starts_with($normalized, 'openai/') || str_contains($normalized, 'gpt-')) {
            return 'openai';
        }

        return (string) $this->config['provider'];
    }

    /**
     * @param array<int, array<string, mixed>> $events
     *
     * @return array{ok:bool,message:string}
     */
    private function postEvents(array $events): array
    {
        $endpoint = (string) $this->config['endpoint'];

        $payload = [
            'source' => (string) $this->config['source'],
            'provider' => (string) $this->config['provider'],
            'events' => $events,
            'sent_at' => date('c'),
        ];

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            return ['ok' => false, 'message' => 'Gagal encode payload JSON'];
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        $ingestKey = (string) $this->config['ingest_key'];
        if ($ingestKey !== '') {
            $headers[] = 'X-Router-Ingest-Key: ' . $ingestKey;
        }

        $ch = curl_init($endpoint);
        if ($ch === false) {
            return ['ok' => false, 'message' => 'Gagal inisialisasi cURL'];
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, (int) $this->config['timeout']));
        curl_setopt($ch, CURLOPT_TIMEOUT, (int) $this->config['timeout']);

        $body = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'message' => ($error !== '' ? $error : 'Unknown cURL error')];
        }

        if ($httpCode >= 400) {
            return ['ok' => false, 'message' => 'HTTP ' . $httpCode . ' | ' . substr((string) $body, 0, 220)];
        }

        return ['ok' => true, 'message' => 'HTTP ' . $httpCode];
    }

    private function printPreview(): void
    {
        $preview = array_slice($this->events, 0, 2);
        $json = json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_string($json)) {
            $this->writeOut('Preview first events:');
            fwrite(STDOUT, $json . PHP_EOL);
        }
    }

    private function writeOut(string $message): void
    {
        fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL);
    }

    private function writeErr(string $message): void
    {
        fwrite(STDERR, '[' . date('Y-m-d H:i:s') . '] ERROR: ' . $message . PHP_EOL);
    }
}

$shipper = new RouterLogShipper($argv);
exit($shipper->run());
