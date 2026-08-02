<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Website feedback
    |--------------------------------------------------------------------------
    |
    | The feedback affordance is shown in the first authenticated member
    | session and then every N sessions. Values below one safely fall back to
    | one, which means that every member session is eligible.
    |
    */

    'session_interval' => max(1, (int) env('WEBSITE_FEEDBACK_SESSION_INTERVAL', 1)),

    'message_min_length' => 10,
    'message_max_length' => 5000,

    'rate_limit_per_hour' => 5,
];
