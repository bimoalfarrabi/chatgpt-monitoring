<?php

namespace App\Models;

use CodeIgniter\Model;

class AiRouterUsageEventModel extends Model
{
    protected $table            = 'ai_router_usage_events';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'router_account_id',
        'provider',
        'model',
        'router_account_ref',
        'input_tokens',
        'output_tokens',
        'cache_read_tokens',
        'reasoning_tokens',
        'duration_ms',
        'status',
        'event_hash',
        'event_at',
        'raw_log',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
