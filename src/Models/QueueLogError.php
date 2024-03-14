<?php

namespace  Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QueueLogError extends Model
{
    protected $fillable = ['id_queue', 'error'];

    public function queue()
    {
        return $this->belongsTo(Queue::class);
    }
}

