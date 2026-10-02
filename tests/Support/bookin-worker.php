<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
config([
  'database.default' => 'sqlite',
  'database.connections.sqlite.database' => $argv[1],
  'database.connections.sqlite.url' => null,
  'database.connections.sqlite.busy_timeout' => 5000,
  'session.driver' => 'array', 'cache.default' => 'array', 'logging.default' => 'stderr',
]);
DB::purge('sqlite');

$isOrderUpdate = fn (string $sql) => str_starts_with(strtolower($sql), 'update "orders"');
$signalled = false;
if ($argv[2] === 'hold') {
  DB::listen(function (QueryExecuted $query) use ($isOrderUpdate, &$signalled) {
    if (!$signalled && $isOrderUpdate($query->sql)) {
      $signalled = true;
      echo "CLAIMED\n";
      fflush(STDOUT);
      if (trim((string) fgets(STDIN)) !== 'release') {
        throw new RuntimeException('Worker was not released');
      }
    }
  });
} else {
  DB::connection()->beforeExecuting(function (string $sql) use ($isOrderUpdate, &$signalled) {
    if (!$signalled && $isOrderUpdate($sql)) {
      $signalled = true;
      echo "ATTEMPTING\n";
      fflush(STDOUT);
    }
  });
}

$request = Request::create('/bookin', 'POST', json_decode($argv[3], true, flags: JSON_THROW_ON_ERROR),
  server: ['HTTP_ACCEPT' => 'application/json']);
$response = $kernel->handle($request);
$errors = $request->session()->get('errors');
if ($response->getStatusCode() !== 302 || ($errors && $errors->any())) {
  fwrite(STDERR, 'Unexpected response: '.$response->getStatusCode().' '.$response->getContent().PHP_EOL);
  exit(1);
}
echo "SUCCESS\n";
$kernel->terminate($request, $response);
