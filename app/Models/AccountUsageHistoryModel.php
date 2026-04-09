<?php

namespace App\Models;

use CodeIgniter\Model;

class AccountUsageHistoryModel extends Model
{
    protected $table            = 'account_usage_histories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'account_usage_id',
        'old_percent',
        'new_percent',
        'created_at',
    ];
    protected $useTimestamps = false;
}
