<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = 'company_new_buildarya';
echo "Checking Connection: " . $conn . "\n";

try {
    $roles = DB::connection($conn)->table('roles')->get();
    echo "Roles in Tenant DB:\n";
    foreach($roles as $r) {
        echo "ID: {$r->id}, Name: {$r->name}, Visibility: {$r->visiblity_at_site}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
