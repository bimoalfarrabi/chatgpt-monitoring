<?php

namespace App\Services;

use DateInterval;
use DateTime;
use Throwable;

class SubscriptionStatusService
{
    public static function resolveStatus(?string $expiredAt, bool $isWorkspaceDeactivated = false): string
    {
        if ($isWorkspaceDeactivated) {
            return 'deactivated';
        }

        if ($expiredAt === null || trim($expiredAt) === '') {
            return 'active';
        }

        try {
            $now = new DateTime();
            $expiry = new DateTime($expiredAt);
        } catch (Throwable) {
            return 'active';
        }

        if ($expiry < $now) {
            return 'expired';
        }

        $expiringSoonLimit = (clone $now)->add(new DateInterval('P3D'));

        if ($expiry <= $expiringSoonLimit) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public static function calculateExpiredAt(?string $subscribedAt, bool $isOneMonthDuration): ?string
    {
        if (! $isOneMonthDuration || $subscribedAt === null || trim($subscribedAt) === '') {
            return null;
        }

        try {
            $start = new DateTime($subscribedAt);
        } catch (Throwable) {
            return null;
        }

        return $start->add(new DateInterval('P1M'))->format('Y-m-d H:i:s');
    }

    /**
     * @return array<int, string>
     */
    public static function usageTypes(string $accountType, ?string $proAccountType = null): array
    {
        $normalizedAccountType = self::normalizeAccountType($accountType);
        if ($normalizedAccountType !== 'pro') {
            return ['weekly'];
        }

        if (self::normalizeProAccountType($proAccountType) === 'personal_invite') {
            return ['5h', 'weekly', 'weekly_personal'];
        }

        return ['5h', 'weekly'];
    }

    public static function normalizeAccountType(?string $accountType): string
    {
        $value = strtolower(trim((string) $accountType));
        return $value === 'pro' ? 'pro' : 'free';
    }

    public static function normalizeProAccountType(?string $proAccountType): ?string
    {
        $value = strtolower(trim((string) $proAccountType));
        return in_array($value, ['personal_invite', 'seller_account'], true) ? $value : null;
    }

    public static function parseBoolean(mixed $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        $normalized = strtolower(trim((string) $value));
        if ($normalized === '') {
            return $default;
        }

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    public static function humanize(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'expiring_soon' => 'Expiring Soon',
            'expired' => 'Expired',
            'deactivated' => 'Deactivated',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
