<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';   // defines $config
require_once __DIR__ . '/functions.php';

$summary = poll_and_notify($config);

echo "sent={$summary['sent']} skipped={$summary['skipped']} errors=" . count($summary['errors']) . " @ " . date('Y-m-d H:i:s') . PHP_EOL;
if (!empty($summary['markets'])) {
    foreach ($summary['markets'] as $m => $cnt) {
        echo "  {$m}: {$cnt} найдено (после фильтров)".PHP_EOL;
    }
}
if (!empty($summary['errors'])) {
    foreach ($summary['errors'] as $e) {
        echo "ERR {$e['market']} #{$e['id']}: {$e['error']}".PHP_EOL;
    }
}
echo "Debug: storage/debug.log".PHP_EOL;
