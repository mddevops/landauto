<?php

/*
 * Long-lived Artisan runner for browser tests. Boots the application once (APP_ENV comes from the
 * parent process) and runs each command line received on stdin — one JSON array of CLI tokens per
 * line — through the console kernel with the same argument parsing as `php artisan`. Every command
 * is answered with exactly one protocol line on stdout: `PROTOCOL_PREFIX{"status":…,"output":…}`.
 */

use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Output\BufferedOutput;

const PROTOCOL_PREFIX = "\x1Eartisan-worker ";

$root = dirname(__DIR__, 3);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$reply = static function (array $message): void {
    fwrite(STDOUT, PROTOCOL_PREFIX.json_encode($message, JSON_INVALID_UTF8_SUBSTITUTE)."\n");
    fflush(STDOUT);
};

$reply(['ready' => true]);

while (($line = fgets(STDIN)) !== false) {
    $tokens = json_decode($line, true);

    if (! is_array($tokens) || $tokens === []) {
        $reply(['status' => 1, 'output' => 'Invalid command line: '.$line]);

        continue;
    }

    $output = new BufferedOutput;
    ob_start();

    try {
        $status = $kernel->handle(new ArgvInput(['artisan', ...$tokens]), $output);
    } catch (Throwable $exception) {
        $status = 1;
        $output->writeln($exception::class.': '.$exception->getMessage());
    }

    // Anything a command echoes directly stays out of the protocol stream.
    $echoed = (string) ob_get_clean();

    $reply(['status' => $status, 'output' => $echoed.$output->fetch()]);
}
