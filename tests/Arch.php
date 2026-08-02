<?php

declare(strict_types=1);

arch('application entry points do not use debugging helpers')
    ->expect('App')
    ->not->toUse(['dd', 'dump', 'ray', 'var_dump']);

arch('application symbols match their PSR-4 path casing')
    ->expect('App')
    ->toBeCasedCorrectly();

arch('controller classes keep the project naming convention')
    ->expect('App\Http\Controllers')
    ->classes()
    ->toHaveSuffix('Controller');

arch('policies keep the project naming convention')
    ->expect('App\Policies')
    ->toBeClasses()
    ->toHaveSuffix('Policy');

arch()->preset()->php();

// MD5/SHA-1 are non-secret cache/document IDs; mt_rand creates reversible map jitter.
arch()->preset()->security()->ignoring(['md5', 'mt_rand', 'sha1']);
