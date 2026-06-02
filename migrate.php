<?php
/**
 * Migration script from old config.php format to new JSON database
 * Usage: php migrate.php
 * 
 * Это преобразует старый формат (жёстко закодированные магазины в config.php)
 * в новый формат (JSON база с управлением через админку)
 */

declare(strict_types=1);

require_once __DIR__ . '/db_stores.php';

echo "=== Migration Tool ===\n";
echo "Converts old config.php format to new JSON database\n\n";

// Старый формат (из config.php)
$oldConfig = [
    'stores' => [
        'AquaCam' => [
            'ozon' => [
                'client_id' => 'old_client_id',
                'api_key' => 'old_api_key'
            ],
            'wildberries' => [
                'token' => 'old_wb_token'
            ],
            'yandex' => [
                'campaign_id' => 'old_campaign',
                'business_id' => 'old_business',
                'oauth_token' => 'old_oauth'
            ]
        ],
    ]
];

echo "This script helps you migrate from the old config.php format.\n";
echo "Current database: " . get_stores_db_path() . "\n\n";

$existingStores = db_load();

if (!empty($existingStores)) {
    echo "⚠️  Database already has " . count($existingStores) . " store(s).\n";
    echo "Proceeding will preserve existing data.\n\n";
}

echo "If you have an old config.php:\n";
echo "1. Open your config.php\n";
echo "2. Copy the 'stores' array\n";
echo "3. Replace \$oldConfig['stores'] in this script\n";
echo "4. Run: php migrate.php\n\n";

// Миграция
function migrateStore(string $name, array $data): bool {
    echo "Migrating store: {$name}... ";
    
    if (db_get_store($name)) {
        echo "⚠️  Already exists, skipping\n";
        return false;
    }
    
    $storeData = [];
    
    // Migrate each marketplace
    if (!empty($data['ozon'])) {
        $storeData['ozon'] = $data['ozon'];
        echo "[ozon] ";
    }
    
    if (!empty($data['wildberries'])) {
        $storeData['wildberries'] = $data['wildberries'];
        echo "[wildberries] ";
    }
    
    if (!empty($data['yandex'])) {
        $storeData['yandex'] = $data['yandex'];
        echo "[yandex] ";
    }
    
    if (db_save_store($name, $storeData)) {
        echo "✓\n";
        return true;
    } else {
        echo "✗\n";
        return false;
    }
}

// Example: миграция старого формата
echo "=== Migration Example ===\n";
$migrated = 0;

foreach ($oldConfig['stores'] as $storeName => $storeData) {
    if (migrateStore($storeName, $storeData)) {
        $migrated++;
    }
}

echo "\n=== Summary ===\n";
echo "Migrated: {$migrated} store(s)\n\n";

$allStores = db_get_all_stores();
echo "Total stores in database: " . count($allStores) . "\n";

if ($allStores) {
    echo "\nStores:\n";
    foreach ($allStores as $name => $data) {
        $markets = array_keys(array_filter($data));
        echo "  - {$name}: " . implode(', ', $markets ?: ['empty']) . "\n";
    }
}

echo "\n✓ Migration complete!\n";
echo "You can now manage stores through http://localhost/admin/\n";
