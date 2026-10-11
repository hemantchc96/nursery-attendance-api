<?php

// A real HTTP-kernel request in an independent process, sharing only the test database.
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

DB::listen(function (QueryExecuted $query): void {
    if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"children"')) {
        echo "READY\n";
        flush();
    }
});

$request = Request::create('/rooms/2/check-ins', 'POST', [], [], [], [
    'HTTP_X_TENANT_ID' => '1',
    'HTTP_ACCEPT' => 'application/json',
    'CONTENT_TYPE' => 'application/json',
], '{"child_id":1}');
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode(), 'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR);
$kernel->terminate($request, $response);
