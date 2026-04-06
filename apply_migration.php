<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    Schema::connection('mysql')->table('users', function (Blueprint $table) {
        if (!Schema::connection('mysql')->hasColumn('users', 'view_duration')) {
            $table->string('view_duration', 50)->nullable()->after('role_id');
            echo "Added 'view_duration' column.\n";
        } else {
            echo "'view_duration' column already exists.\n";
        }
        
        if (!Schema::connection('mysql')->hasColumn('users', 'add_duration')) {
            $table->string('add_duration', 50)->nullable()->after('view_duration');
            echo "Added 'add_duration' column.\n";
        } else {
            echo "'add_duration' column already exists.\n";
        }
    });
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
