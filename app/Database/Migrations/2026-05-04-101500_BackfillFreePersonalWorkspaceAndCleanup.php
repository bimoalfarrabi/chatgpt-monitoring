<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillFreePersonalWorkspaceAndCleanup extends Migration
{
    public function up()
    {
        if (! $this->db->tableExists('subscriptions')) {
            return;
        }

        if (! $this->db->fieldExists('account_type', 'subscriptions')) {
            return;
        }

        if (! $this->db->fieldExists('personal_workspace_name', 'subscriptions')) {
            return;
        }

        $accountsById = [];
        if ($this->db->tableExists('accounts')) {
            $accounts = $this->db->table('accounts')
                ->select('id, account_name')
                ->get()
                ->getResultArray();

            foreach ($accounts as $account) {
                $accountsById[(int) ($account['id'] ?? 0)] = trim((string) ($account['account_name'] ?? ''));
            }
        }

        $subscriptions = $this->db->table('subscriptions')
            ->select('id, account_id, account_type, workspace_name, personal_workspace_name')
            ->where('account_type', 'free')
            ->get()
            ->getResultArray();

        $subscriptionBuilder = $this->db->table('subscriptions');
        $freeSubscriptionIds = [];

        foreach ($subscriptions as $subscription) {
            $subscriptionId = (int) ($subscription['id'] ?? 0);
            if ($subscriptionId <= 0) {
                continue;
            }

            $freeSubscriptionIds[] = $subscriptionId;

            $personalWorkspace = trim((string) ($subscription['personal_workspace_name'] ?? ''));
            $workspaceName = trim((string) ($subscription['workspace_name'] ?? ''));

            if ($personalWorkspace === '' && $workspaceName !== '') {
                $personalWorkspace = $workspaceName;
            }

            if ($personalWorkspace === '') {
                $accountId = (int) ($subscription['account_id'] ?? 0);
                $personalWorkspace = trim((string) ($accountsById[$accountId] ?? ''));
            }

            if ($personalWorkspace === '') {
                $personalWorkspace = 'Personal Workspace';
            }

            $updateData = [
                'pro_account_type' => null,
                'workspace_name' => null,
                'personal_workspace_name' => $personalWorkspace,
                'store_source' => 'free_account',
                'subscription_type' => 'Free Weekly',
                'status' => 'active',
            ];

            if ($this->db->fieldExists('is_workspace_deactivated', 'subscriptions')) {
                $updateData['is_workspace_deactivated'] = 0;
            }
            if ($this->db->fieldExists('subscribed_at', 'subscriptions')) {
                $updateData['subscribed_at'] = null;
            }
            if ($this->db->fieldExists('is_one_month_duration', 'subscriptions')) {
                $updateData['is_one_month_duration'] = 0;
            }
            if ($this->db->fieldExists('expired_at', 'subscriptions')) {
                $updateData['expired_at'] = null;
            }

            $subscriptionBuilder->where('id', $subscriptionId)->update($updateData);
        }

        if ($freeSubscriptionIds === []) {
            return;
        }

        if ($this->db->tableExists('subscription_renewal_histories')) {
            $this->db->table('subscription_renewal_histories')
                ->whereIn('subscription_id', $freeSubscriptionIds)
                ->delete();
        }

        if (! $this->db->tableExists('account_usages')) {
            return;
        }

        $usageBuilder = $this->db->table('account_usages');
        $usageBuilder
            ->whereIn('subscription_id', $freeSubscriptionIds)
            ->whereIn('usage_type', ['5h', 'weekly_personal'])
            ->delete();

        $weeklyRows = $this->db->table('account_usages')
            ->select('subscription_id')
            ->whereIn('subscription_id', $freeSubscriptionIds)
            ->where('usage_type', 'weekly')
            ->get()
            ->getResultArray();

        $hasWeekly = [];
        foreach ($weeklyRows as $row) {
            $hasWeekly[(int) ($row['subscription_id'] ?? 0)] = true;
        }

        foreach ($freeSubscriptionIds as $subscriptionId) {
            if (isset($hasWeekly[$subscriptionId])) {
                continue;
            }

            $this->db->table('account_usages')->insert([
                'subscription_id' => $subscriptionId,
                'usage_type' => 'weekly',
                'remaining_percent' => 100,
                'reset_at' => null,
            ]);
        }
    }

    public function down()
    {
        // Irreversible data cleanup migration.
    }
}
