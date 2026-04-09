<?php

namespace App\Services;

use DateInterval;
use DateTime;

class SubscriptionStatusService
{
    public static function resolveStatus(string $expiredAt): string
    {
        $now = new DateTime();
        $expiry = new DateTime($expiredAt);

        if ($expiry < $now) {
            return 'expired';
        }

        $expiringSoonLimit = (clone $now)->add(new DateInterval('P3D'));

        if ($expiry <= $expiringSoonLimit) {
            return 'expiring_soon';
        }

        return 'active';
    }

    public static function humanize(string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'expiring_soon' => 'Expiring Soon',
            'expired' => 'Expired',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
