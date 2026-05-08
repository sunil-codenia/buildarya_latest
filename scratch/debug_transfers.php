<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = config('database.default');
echo "Current Connection: " . $conn . "\n";

try {
    $transfers = DB::connection($conn)->table('material_site_transfers')->get();
    echo "Total Transfers: " . count($transfers) . "\n";
    foreach($transfers as $t) {
        echo "ID: {$t->id}, Date: {$t->date}, Material: {$t->material_id}, Qty: {$t->qty}\n";
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
