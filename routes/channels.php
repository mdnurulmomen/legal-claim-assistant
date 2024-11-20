<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('public-channel', function ($user) {
    return true;
});
