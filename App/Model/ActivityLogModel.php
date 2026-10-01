<?php

namespace App\Model;

use CodeIgniter\Model;

class ActivityLogModel extends Model
{
    protected $table         = 'activity_log';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $updatedField  = '';
    protected $allowedFields = [
        'user_id', 'user_name', 'action', 'description',
        'subject_type', 'subject_id', 'ip_address', 'user_agent',
    ];

    /**
     * Delete entries older than the given number of days.
     */
    public function prune(int $days): int
    {
        $this->where('created_at <', date('Y-m-d H:i:s', strtotime("-{$days} days")))->delete();

        return $this->db->affectedRows();
    }
}
