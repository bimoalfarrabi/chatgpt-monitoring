<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionModel extends Model
{
    protected $table            = 'subscriptions';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'account_id',
        'account_type',
        'pro_account_type',
        'workspace_name',
        'personal_workspace_name',
        'is_workspace_deactivated',
        'store_source',
        'subscription_type',
        'subscribed_at',
        'is_one_month_duration',
        'expired_at',
        'status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
