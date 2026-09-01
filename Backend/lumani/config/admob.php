<?php

return [
    'ssv_enabled' => (bool) env('ADMOB_SSV_ENABLED', false),
    'verifier_keys_url' => env('ADMOB_VERIFIER_KEYS_URL', 'https://gstatic.com/admob/reward/verifier-keys.json'),
];
