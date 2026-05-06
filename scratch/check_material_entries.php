<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

$conn = 'mysql'; // Default connection for this test, or check config
$entries = DB::connection($conn)->table('material_entry')
    ->whereBetween('date', ['2025-12-31', '2026-05-06'])
    ->get();

echo "Count: " . count($entries) . "\n";
foreach($entries as $e) {
    echo "ID: {$e->id}, Date: {$e->date}, Status: {$e->status}, Site: {$e->site_id}\n";
}
