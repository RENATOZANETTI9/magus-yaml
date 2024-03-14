<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    use HasFactory;

    protected $fillable = ['nome'];

    public function conditions()
    {
        return $this->hasMany(Condition::class, 'id_status');
    }
}
