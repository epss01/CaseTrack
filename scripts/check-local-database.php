<?php

// Read-only identity check: elevated MariaDB processes may hide their Windows path.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';

try {
    $app = require dirname(__DIR__).'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    $connection = config('database.default');
    $settings = config('database.connections.'.$connection);
    if (($settings['driver'] ?? null) !== 'mysql'
        || ($settings['host'] ?? null) !== '127.0.0.1'
        || (int) ($settings['port'] ?? 0) !== 3307
        || ! empty($settings['url']) || ! empty($settings['unix_socket'])
        || ! empty($settings['read']) || ! empty($settings['write'])) {
        throw new RuntimeException('Use the local mysql connection at 127.0.0.1:3307 without URL, socket, or read/write overrides.');
    }

    config(['database.connections.'.$connection.'.options' => [PDO::ATTR_TIMEOUT => 5] + ($settings['options'] ?? [])]);
    $server = DB::connection()->selectOne('SELECT @@basedir AS base_dir, @@datadir AS data_dir, @@port AS port, VERSION() AS version');
    $base = strtolower(rtrim(str_replace('\\', '/', $server->base_dir), '/'));
    $data = strtolower(rtrim(str_replace('\\', '/', $server->data_dir), '/'));
    if ($base !== 'c:/xampp/mysql' || $data !== 'c:/xampp/mysql/data'
        || (int) $server->port !== 3307 || stripos($server->version, 'MariaDB') === false) {
        throw new RuntimeException('Port 3307 is not the expected C:/xampp MariaDB installation.');
    }
} catch (Throwable $error) {
    // Connection exceptions can contain credentials or SQL; keep them out of the console.
    $message = get_class($error) === RuntimeException::class
        ? $error->getMessage()
        : 'Confirm XAMPP MariaDB, the casetrack database, and .env credentials/settings.';
    fwrite(STDERR, 'Local database verification failed. '.$message."\n");
    exit(1);
}
