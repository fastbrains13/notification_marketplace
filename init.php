<?php
/**
 * Initialization script
 * Создает необходимые директории и файлы при первом запуске
 * 
 * Usage: php init.php
 */

declare(strict_types=1);

require_once __DIR__ . '/db_stores.php';

echo "=== Initialization ===\n\n";

// 1. Создать storage директорию
$storageDir = __DIR__ . '/storage';
if (!is_dir($storageDir)) {
    if (@mkdir($storageDir, 0775, true)) {
        echo "✓ Created storage/ directory\n";
    } else {
        echo "✗ Failed to create storage/ directory\n";
        exit(1);
    }
} else {
    echo "✓ storage/ directory exists\n";
}

// 2. Создать .env если его нет
$envFile = __DIR__ . '/.env';
if (!file_exists($envFile)) {
    if (@copy(__DIR__ . '/.env.example', $envFile)) {
        echo "✓ Created .env file\n";
        echo "  ⚠️  Please edit .env and add your credentials\n";
    } else {
        echo "! Could not create .env (you can copy .env.example manually)\n";
    }
} else {
    echo "✓ .env file exists\n";
}

// 3. Создать пустой stores.json если его нет
$dbPath = get_stores_db_path();
if (!file_exists($dbPath)) {
    if (db_save([])) {
        echo "✓ Created stores.json database\n";
    } else {
        echo "✗ Failed to create stores.json\n";
        exit(1);
    }
} else {
    echo "✓ stores.json exists\n";
}

// 4. Создать sent.json если его нет
$sentFile = __DIR__ . '/storage/sent.json';
if (!file_exists($sentFile)) {
    $data = ['sent' => [], 'last_run' => []];
    if (file_put_contents($sentFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
        echo "✓ Created sent.json (dedup/history)\n";
    } else {
        echo "✗ Failed to create sent.json\n";
    }
} else {
    echo "✓ sent.json exists\n";
}

// 5. Информация о доступных маркетплейсах
echo "\n=== Available Marketplaces ===\n";
echo "- Ozon (requires: client_id, api_key)\n";
echo "- Wildberries (requires: token)\n";
echo "- Yandex Market (requires: campaign_id, business_id, oauth_token)\n";

echo "\n=== Next Steps ===\n";
echo "1. Edit .env with your Telegram credentials\n";
echo "2. Open http://localhost/admin/ to add stores and API keys\n";
echo "3. Test with: php check_orders.php\n";
echo "4. Set up cron: 0 */5 * * * cd /path && php check_orders.php\n";

echo "\nInitialization complete!\n";
