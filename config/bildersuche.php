<?php

return [
    'enabled' => filter_var(env('BILDERSUCHE_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'sync_enabled' => filter_var(env('BILDERSUCHE_SYNC_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
];
