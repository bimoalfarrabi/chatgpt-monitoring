<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountUsageModel extends Model
{
    protected $table            = 'account_usages';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'subscription_id',
        'usage_type',
        'remaining_percent',
        'reset_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
