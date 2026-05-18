<?php

namespace App\Controllers\Api;

use App\Services\RouterAnalyticsService;
use CodeIgniter\HTTP\ResponseInterface;

class RouterAnalyticsController extends BaseApiController
{
    private RouterAnalyticsService $analytics;

    public function __construct()
    {
        $this->analytics = new RouterAnalyticsService();
    }

    public function summary(): ResponseInterface
    {
        $provider = trim((string) ($this->request->getGet('provider') ?? ''));
        $days = (int) ($this->request->getGet('days') ?? 30);
        if ($days <= 0) {
            $days = 30;
        }

        try {
            $data = $this->analytics->summary($provider, $days);
        } catch (\Throwable $e) {
            return $this->failMessage('Gagal mengambil analytics: ' . $e->getMessage(), 500);
        }

        return $this->ok($data);
    }

    public function charts(): ResponseInterface
    {
        $provider = trim((string) ($this->request->getGet('provider') ?? ''));
        $days = (int) ($this->request->getGet('days') ?? 30);
        $top = (int) ($this->request->getGet('top') ?? 10);
        if ($days <= 0) {
            $days = 30;
        }
        if ($top <= 0) {
            $top = 10;
        }

        try {
            $data = $this->analytics->charts($provider, $days, $top);
        } catch (\Throwable $e) {
            return $this->failMessage('Gagal mengambil chart analytics: ' . $e->getMessage(), 500);
        }

        return $this->ok($data);
    }

    public function accountShare(): ResponseInterface
    {
        $email = trim((string) ($this->request->getGet('email') ?? ''));
        $provider = trim((string) ($this->request->getGet('provider') ?? ''));
        $days = (int) ($this->request->getGet('days') ?? 30);
        if ($days <= 0) {
            $days = 30;
        }

        try {
            $data = $this->analytics->accountShareByEmail($email, $provider, $days);
        } catch (\Throwable $e) {
            return $this->failMessage('Gagal mengambil account share analytics: ' . $e->getMessage(), 500);
        }

        return $this->ok($data);
    }
}
