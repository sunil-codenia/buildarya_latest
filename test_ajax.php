<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle(
    $request = Illuminate\Http\Request::create(
        '/pending_expense_ajax', 'POST'
    )
);
$kernel->terminate($request, $response);

echo "HTTP Code: " . $response->getStatusCode() . "\n";
echo "Response Body: \n" . substr($response->getContent(), 0, 1000) . "\n";
