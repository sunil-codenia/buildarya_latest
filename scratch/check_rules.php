<?php
require __DIR__ . '/public/index.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Illuminate\Support\Facades\DB;

$rules = DB::table('material_conversion_rules')->get();
echo "Rules in database:\n";
foreach ($rules as $rule) {
    echo "ID: {$rule->id}, Material ID: {$rule->material_id}, From: {$rule->from_unit}, To: {$rule->to_unit}\n";
}
