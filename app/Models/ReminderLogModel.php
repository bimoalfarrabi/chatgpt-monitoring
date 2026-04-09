<?php

namespace App\Models;

use CodeIgniter\Model;

class ReminderLogModel extends Model
{
    protected $table            = 'reminder_logs';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'account_id',
        'subscription_id',
        'reminder_type',
        'sent_at',
    ];
    protected $useTimestamps = false;
}
