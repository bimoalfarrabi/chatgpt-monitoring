<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWeeklyPersonalUsageForPersonalInvite extends Migration
{
    public function up()
    {
        $subscriptions = $this->db->table('subscriptions')
            ->select('id')
            ->where('account_type', 'pro')
            ->where('pro_account_type', 'personal_invite')
            ->get()
            ->getResultArray();

        if ($subscriptions === []) {
            return;
        }

        $usageBuilder = $this->db->table('account_usages');

        foreach ($subscriptions as $subscription) {
            $subscriptionId = (int) ($subscription['id'] ?? 0);
            if ($subscriptionId <= 0) {
                continue;
            }

            $exists = $usageBuilder
                ->where('subscription_id', $subscriptionId)
                ->where('usage_type', 'weekly_personal')
                ->countAllResults();

            if ($exists > 0) {
                continue;
            }

            $usageBuilder->insert([
                'subscription_id' => $subscriptionId,
                'usage_type' => 'weekly_personal',
                'remaining_percent' => 100,
                'reset_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $this->db->table('account_usages')
            ->where('usage_type', 'weekly_personal')
            ->delete();
    }
}
