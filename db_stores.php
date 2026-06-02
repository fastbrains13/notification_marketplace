<?php
declare(strict_types=1);

/**
 * Database helper for managing stores
 * Stores data in JSON format: storage/stores.json
 */

function get_stores_db_path(): string {
    $path = getenv('STORES_DB_PATH') ?: __DIR__ . '/storage/stores.json';
    $dir = dirname($path);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $path;
}

function db_load(): array {
    $path = get_stores_db_path();
    if (!file_exists($path)) {
        return [];
    }
    $data = json_decode(file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function db_save(array $data): bool {
    $path = get_stores_db_path();
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($path, $json) !== false;
}

/**
 * Get all stores
 */
function db_get_all_stores(): array {
    return db_load();
}

/**
 * Get store by name
 */
function db_get_store(string $name): ?array {
    $stores = db_load();
    return $stores[$name] ?? null;
}

/**
 * Create/update store
 * @param string $name Store name (unique key)
 * @param array $data Store config with marketplaces
 */
function db_save_store(string $name, array $data): bool {
    $stores = db_load();
    $stores[$name] = array_merge($stores[$name] ?? [], $data);
    return db_save($stores);
}

/**
 * Delete store
 */
function db_delete_store(string $name): bool {
    $stores = db_load();
    unset($stores[$name]);
    return db_save($stores);
}

/**
 * Update marketplace credentials for a store
 */
function db_update_marketplace(string $storeName, string $marketName, array $creds): bool {
    $store = db_get_store($storeName);
    if (!$store) {
        $store = [];
    }
    if (!isset($store[$marketName])) {
        $store[$marketName] = [];
    }
    // Merge credentials, keeping empty strings as is
    $store[$marketName] = array_merge($store[$marketName], $creds);
    return db_save_store($storeName, $store);
}

/**
 * Validate marketplace credentials
 */
function validate_marketplace_creds(string $market, array $creds): array {
    $errors = [];
    
    switch ($market) {
        case 'ozon':
            if (empty($creds['client_id'])) $errors[] = 'client_id required';
            if (empty($creds['api_key'])) $errors[] = 'api_key required';
            break;
            
        case 'wildberries':
            if (empty($creds['token'])) $errors[] = 'token required';
            break;
            
        case 'yandex':
            if (empty($creds['campaign_id'])) $errors[] = 'campaign_id required';
            if (empty($creds['oauth_token'])) $errors[] = 'oauth_token required';
            break;
    }
    
    return $errors;
}
