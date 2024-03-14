<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Model;

class QueueLog extends Model
{
    protected $table = 'queue_log';

    protected $fillable = ['id_queue', 'id_flow_control', 'response'];

    public function queue()
    {
        return $this->belongsTo(Queue::class, 'id_queue');
    }
}
