
<?php
declare(strict_types=1);

// Load .env file if exists
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with($line, '#')) continue;
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) putenv("{$key}={$value}");
        }
    }
}

// Load stores from JSON database
function load_stores(): array {
    $dbPath = getenv('STORES_DB_PATH') ?: __DIR__ . '/storage/stores.json';
    if (!file_exists($dbPath)) {
        return [];
    }
    $data = json_decode(file_get_contents($dbPath), true);
    return is_array($data) ? $data : [];
}

$config = [
    'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
    'telegram_chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '',
    'stores' => load_stores(),
];
