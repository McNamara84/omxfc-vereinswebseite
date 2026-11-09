<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;

trait MaddraxikonOAuthClientSecurityTestFixtures
{
    use RefreshDatabase;

    private const TOKEN_URL = 'https://de.maddraxikon.com/rest.php/oauth2/access_token';

    private const PROFILE_URL = 'https://de.maddraxikon.com/rest.php/oauth2/resource/profile';

    private const API_URL = 'https://de.maddraxikon.com/api.php';
}
