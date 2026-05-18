<?php

namespace App\Controllers\Api;

use App\Services\RouterUsageCollectorService;
use CodeIgniter\HTTP\ResponseInterface;

class RouterIngestController extends BaseApiController
{
    private RouterUsageCollectorService $collector;

    public function __construct()
    {
        $this->collector = new RouterUsageCollectorService();
    }

    public function ingest(): ResponseInterface
    {
        $requiredKey = trim((string) env('router.ingestKey', ''));
        if ($requiredKey !== '') {
            $providedKey = trim((string) ($this->request->getHeaderLine('X-Router-Ingest-Key')));
            if ($providedKey === '' || ! hash_equals($requiredKey, $providedKey)) {
                return $this->failMessage('Unauthorized ingest key.', 401);
            }
        }

        $data = $this->requestData();

        $source = trim((string) ($data['source'] ?? 'remote-shipper'));
        $provider = trim((string) ($data['provider'] ?? '9router'));

        $events = $data['events'] ?? null;
        if (! is_array($events) || $events === []) {
            return $this->validationErrorResponse([
                'events' => 'Payload events wajib berupa array dan tidak boleh kosong.',
            ]);
        }

        if (count($events) > 1000) {
            return $this->validationErrorResponse([
                'events' => 'Maksimal 1000 event per request.',
            ]);
        }

        $normalizedEvents = [];
        foreach ($events as $row) {
            if (! is_array($row)) {
                continue;
            }

            $normalizedEvents[] = $row;
        }

        if ($normalizedEvents === []) {
            return $this->validationErrorResponse([
                'events' => 'Seluruh event tidak valid.',
            ]);
        }

        try {
            $result = $this->collector->ingestStructuredEvents($normalizedEvents, $source, $provider);
        } catch (\Throwable $e) {
            return $this->failMessage('Ingest gagal: ' . $e->getMessage(), 500);
        }

        return $this->ok($result, 201);
    }
}
