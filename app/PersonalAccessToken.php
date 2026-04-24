<?php

namespace App;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * The database connection that should be used by the model.
     * Always use the main 'mysql' connection for token storage in multi-tenant setup.
     *
     * @var string
     */
    protected $connection = 'mysql';
}
