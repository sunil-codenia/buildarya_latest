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

    /**
     * Get the tokenable model.
     * Override to load the user model from the resolved tenant database connection.
     */
    public function tokenable()
    {
        $relation = $this->morphTo();
        
        $defaultConn = config('database.default');
        if ($defaultConn && $defaultConn !== 'mysql') {
            $relation->getQuery()->getQuery()->connection = \Illuminate\Support\Facades\DB::connection($defaultConn);
        }
        
        return $relation;
    }
}
