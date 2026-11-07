<?php

namespace App\Observers;

use App\Models\ServerStatus;
use Illuminate\Support\Facades\Cache;

class ServerStatusObserver
{
    public function created(ServerStatus $status): void
    {
        Cache::forget('home-stats');
    }
}