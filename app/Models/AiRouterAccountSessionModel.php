<?php

namespace App\Models;

use CodeIgniter\Model;

class AiRouterAccountSessionModel extends Model
{
    protected $table            = 'ai_router_account_sessions';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'router_account_id',
        'provider',
        'router_account_ref',
        'email',
        'first_seen_at',
        'last_seen_at',
        'total_requests',
        'total_input_tokens',
        'total_output_tokens',
        'total_cache_read_tokens',
        'total_reasoning_tokens',
        'total_duration_ms',
        'last_status',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
