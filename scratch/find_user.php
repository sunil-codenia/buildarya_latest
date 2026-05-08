<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = config('database.default'); // Default central DB
echo "Central DB: " . $conn . "\n";

try {
    $users = DB::connection($conn)->table('users')->where('name', 'LIKE', '%sunil%')->get();
    foreach($users as $u) {
        echo "ID: {$u->id}, Name: {$u->name}, RoleID: {$u->role_id}, CompanyID: {$u->company_id}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
