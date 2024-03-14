<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Model;

class Parameter extends Model
{
    protected $table = 'parameters'; 

    protected $fillable = ['name', 'message', 'timeout_retry', 'action', 'active'];

    public function conditions()
    {
        return $this->hasMany(Condition::class, 'id_parameter');
    }
}
