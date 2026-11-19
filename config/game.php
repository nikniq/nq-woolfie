<?php

return [
    // Password for the room editor at /editor. Leave empty to disable it.
    'editor_password' => env('EDITOR_PASSWORD', ''),

    // Token for the JSON health endpoint at /health?token=... (for uptime monitors). Leave empty to disable it.
    'health_token' => env('HEALTH_TOKEN', ''),
];
