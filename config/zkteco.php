<?php

return [
    'ip' => env('BIOMETRIC_IP'),
    'port' => env('BIOMETRIC_PORT', 4370),
    'protocol' => env('BIOMETRIC_PROTOCOL', 'UDP'),
    'establecimiento_master' => env('ESTABLECIMIENTO_MASTER', true),
    'timeout' => (int) env('BIOMETRIC_TIMEOUT', 2),
    'admin_key' => env('BIOMETRIC_ADMIN_KEY', 'admin2026'),
];
