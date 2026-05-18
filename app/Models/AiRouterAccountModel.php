<?php

namespace App\Models;

use CodeIgniter\Model;

class AiRouterAccountModel extends Model
{
    protected $table            = 'ai_router_accounts';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'user_id',
        'account_id',
        'subscription_id',
        'provider',
        'router_account_ref',
        'email',
        'account_plan',
        'plan_started_at',
        'plan_expires_at',
        'renewal_at',
        'status',
        'mapping_status',
        'notes',
        'last_seen_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
