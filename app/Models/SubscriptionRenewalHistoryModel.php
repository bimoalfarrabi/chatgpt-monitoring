<?php

namespace App\Models;

use CodeIgniter\Model;

class SubscriptionRenewalHistoryModel extends Model
{
    protected $table            = 'subscription_renewal_histories';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'subscription_id',
        'old_expired_at',
        'new_expired_at',
        'renewed_at',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
