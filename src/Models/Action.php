<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Action extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // Relacionamento com ParametersCondition
    public function parametersConditions()
    {
        return $this->hasMany(Condition::class, 'id_action');
    }
}
