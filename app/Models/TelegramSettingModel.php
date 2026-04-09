<?php

namespace App\Models;

use CodeIgniter\Model;

class TelegramSettingModel extends Model
{
    protected $table            = 'telegram_settings';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $allowedFields    = [
        'user_id',
        'bot_token',
        'chat_id',
        'is_active',
    ];
    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
