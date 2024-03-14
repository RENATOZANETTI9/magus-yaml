<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Condition extends Model
{
    use HasFactory;

    protected $table = 'conditions'; 

    
    protected $fillable = ['id_status', 'description', 'parameter_value', 'frequency', 'status', 'active'];

    // Relacionamento com Action
    public function action()
    {
        return $this->belongsTo(Action::class, 'id_action');
    }

    // Relacionamento com Status (assumindo que você já tenha um Model para Status)
    public function status()
    {
        return $this->belongsTo(Status::class, 'id_status');
    }

    public function parameter()
    {
        return $this->belongsTo(Parameter::class, 'id_parameter');
    }
}
