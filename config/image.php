<?php

return [
    // GD is available in every application container and CI test runtime.
    // Imagick remains an explicit opt-in via IMAGE_DRIVER.
    'default' => env('IMAGE_DRIVER', 'gd'),
];
