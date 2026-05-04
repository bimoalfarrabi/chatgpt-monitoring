<?php

namespace App\Commands;

use App\Models\ReminderLogModel;
use App\Models\SubscriptionModel;
use App\Services\SubscriptionStatusService;
use App\Services\TelegramService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class SendSubscriptionReminders extends BaseCommand
{
    protected $group = 'Subscription';
    protected $name = 'reminders:subscriptions';
    protected $description = 'Kirim reminder Telegram untuk subscription expiring/expired.';

    public function run(array $params)
    {
        $telegram = new TelegramService();
        $logs = new ReminderLogModel();
        $subscriptionModel = new SubscriptionModel();

        $subscriptions = $subscriptionModel
            ->select('subscriptions.*, accounts.account_name, accounts.email')
            ->join('accounts', 'accounts.id = subscriptions.account_id')
            ->findAll();

        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');

        foreach ($subscriptions as $subscription) {
            $accountType = SubscriptionStatusService::normalizeAccountType($subscription['account_type'] ?? null);
            $isWorkspace = SubscriptionStatusService::isWorkspaceAccountType($accountType);
            $isOneMonthDuration = SubscriptionStatusService::resolveOneMonthDurationForAccount(
                $accountType,
                SubscriptionStatusService::parseBoolean($subscription['is_one_month_duration'] ?? null, false)
            );
            $expiredAt = $isWorkspace
                ? SubscriptionStatusService::calculateExpiredAt($subscription['subscribed_at'] ?? null, $isOneMonthDuration)
                : null;
            $status = SubscriptionStatusService::resolveStatus(
                $expiredAt,
                SubscriptionStatusService::parseBoolean($subscription['is_workspace_deactivated'] ?? null, false)
            );

            $subscriptionUpdate = [];
            if (($subscription['expired_at'] ?? null) !== $expiredAt) {
                $subscriptionUpdate['expired_at'] = $expiredAt;
                $subscription['expired_at'] = $expiredAt;
            }
            if (($subscription['status'] ?? null) !== $status) {
                $subscriptionUpdate['status'] = $status;
            }
            if ((int) ($subscription['is_one_month_duration'] ?? 0) !== ($isOneMonthDuration ? 1 : 0)) {
                $subscriptionUpdate['is_one_month_duration'] = $isOneMonthDuration ? 1 : 0;
            }
            if ($subscriptionUpdate !== []) {
                $subscriptionModel->update((int) ($subscription['id'] ?? 0), $subscriptionUpdate);
            }

            if (! in_array($status, ['expiring_soon', 'expired', 'deactivated'], true)) {
                continue;
            }

            $exists = $logs
                ->where('account_id', $subscription['account_id'])
                ->where('subscription_id', $subscription['id'])
                ->where('reminder_type', $status)
                ->where('sent_at >=', $todayStart)
                ->where('sent_at <=', $todayEnd)
                ->first();

            if ($exists) {
                continue;
            }

            $text = sprintf(
                "[Subscription Reminder]\nAccount: %s\nEmail: %s\nPlan: %s\nStatus: %s\nBerakhir (otomatis): %s",
                $subscription['account_name'],
                $subscription['email'],
                $subscription['subscription_type'],
                SubscriptionStatusService::humanize($status),
                $subscription['expired_at'] ?? '-'
            );

            $send = $telegram->sendMessage($text);
            if (! $send['success']) {
                CLI::error('Gagal kirim reminder untuk subscription #' . $subscription['id'] . ': ' . $send['message']);
                continue;
            }

            $logs->insert([
                'account_id'      => $subscription['account_id'],
                'subscription_id' => $subscription['id'],
                'reminder_type'   => $status,
                'sent_at'         => date('Y-m-d H:i:s'),
            ]);

            CLI::write('Reminder terkirim untuk subscription #' . $subscription['id'], 'green');
        }

        CLI::write('Selesai menjalankan reminder.', 'yellow');
    }
}
