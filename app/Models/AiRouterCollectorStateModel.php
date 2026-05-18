<?php

namespace App\Models;

use CodeIgniter\Model;

class AiRouterCollectorStateModel extends Model
{
    protected $table            = 'ai_router_collector_states';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'source_key',
        'source_path',
        'last_offset',
        'last_line_number',
        'last_collected_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
