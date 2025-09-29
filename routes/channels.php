<?php

use Illuminate\Support\Facades\Broadcast;

// Allow all public channels (no authentication required)
Broadcast::channel('public-user-{id}', function ($user, $id) {
    return true; // Allow anyone to subscribe
});

// Or allow all public channels without restrictions
Broadcast::channel('public-*', function () {
    return true;
});
