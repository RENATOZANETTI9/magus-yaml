<?php

namespace Magus\Yaml\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Bus\Dispatchable;
use Services\IntegrationService;

class Queue extends Model
{
    protected $table = 'queue';
    protected $fillable = ['identify','header', 'referer', 'request', 'response', 'response_status', 'message', 'flag_process', 'last_execution', 'next_execution', 'next_timeout_retry'];

    // protected static function boot()
    // {
    //     parent::boot();

    //     static::created(function ($queue) {
    //         // Despacha o job quando um novo registro é criado  
    //         $queueArray = $queue->toArray();
    //         $queueArray = IntegrationService::updateAuth($queueArray);
    //         Log::info($queueArray);
    //         // ProcessQueueJob::dispatch($queue);
    //     });
    // }
    
}
