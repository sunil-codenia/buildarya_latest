<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;

// Use a known connection or default
$conn = 'mysql'; // Or whatever is in .env
$data = DB::connection($conn)->table('sales_dedadd')->get();
foreach($data as $row) {
    echo "ID: {$row->id}, Name: {$row->name}, Type: {$row->type}\n";
}
