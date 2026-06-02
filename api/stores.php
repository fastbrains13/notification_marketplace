<?php
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../db_stores.php';

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$parts = array_filter(explode('/', trim($path, '/')));

$response = ['ok' => false, 'error' => 'Unknown endpoint'];
http_response_code(400);

try {
    // GET /api/stores - List all stores
    if ($method === 'GET' && end($parts) === 'stores') {
        $response = [
            'ok' => true,
            'data' => db_get_all_stores()
        ];
        http_response_code(200);
    }
    
    // GET /api/stores/{name} - Get store by name
    elseif ($method === 'GET' && count($parts) >= 2 && $parts[count($parts)-2] === 'stores') {
        $storeName = end($parts);
        $store = db_get_store($storeName);
        if ($store) {
            $response = ['ok' => true, 'data' => $store];
            http_response_code(200);
        } else {
            $response = ['ok' => false, 'error' => 'Store not found'];
            http_response_code(404);
        }
    }
    
    // POST /api/stores - Create store
    elseif ($method === 'POST' && end($parts) === 'stores') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $name = $input['name'] ?? '';
        
        if (!$name) {
            $response = ['ok' => false, 'error' => 'Store name required'];
            http_response_code(400);
        } elseif (db_get_store($name)) {
            $response = ['ok' => false, 'error' => 'Store already exists'];
            http_response_code(409);
        } else {
            // Create empty store with marketplaces
            $storeData = [];
            foreach (['ozon', 'wildberries', 'yandex'] as $market) {
                $storeData[$market] = []; // Empty credentials
            }
            
            if (db_save_store($name, $storeData)) {
                $response = ['ok' => true, 'data' => db_get_store($name)];
                http_response_code(201);
            } else {
                $response = ['ok' => false, 'error' => 'Failed to create store'];
                http_response_code(500);
            }
        }
    }
    
    // PUT /api/stores.php - Update marketplace (send storeName and marketplace in body)
    elseif ($method === 'PUT' && end($parts) === 'stores.php') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $storeName = $input['storeName'] ?? '';
        $marketplace = $input['marketplace'] ?? '';
        
        if (!$storeName || !$marketplace) {
            $response = ['ok' => false, 'error' => 'storeName and marketplace required'];
            http_response_code(400);
        } else {
            // Remove storeName and marketplace from input, keep only credentials
            unset($input['storeName'], $input['marketplace']);
            
            // Validate credentials
            $errors = validate_marketplace_creds($marketplace, $input);
            if ($errors) {
                $response = ['ok' => false, 'error' => 'Validation failed', 'errors' => $errors];
                http_response_code(400);
            } elseif (!db_get_store($storeName)) {
                $response = ['ok' => false, 'error' => 'Store not found'];
                http_response_code(404);
            } else {
                if (db_update_marketplace($storeName, $marketplace, $input)) {
                    $response = ['ok' => true, 'data' => db_get_store($storeName)];
                    http_response_code(200);
                } else {
                    $response = ['ok' => false, 'error' => 'Failed to update'];
                    http_response_code(500);
                }
            }
        }
    }
    
    // DELETE /api/stores.php - Delete store (send name in body)
    elseif ($method === 'DELETE' && end($parts) === 'stores.php') {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $storeName = $input['name'] ?? '';
        
        if (!$storeName) {
            $response = ['ok' => false, 'error' => 'Store name required'];
            http_response_code(400);
        } elseif (!db_get_store($storeName)) {
            $response = ['ok' => false, 'error' => 'Store not found'];
            http_response_code(404);
        } else {
            if (db_delete_store($storeName)) {
                $response = ['ok' => true, 'message' => 'Store deleted'];
                http_response_code(200);
            } else {
                $response = ['ok' => false, 'error' => 'Failed to delete'];
                http_response_code(500);
            }
        }
    }
    
} catch (Exception $e) {
    $response = ['ok' => false, 'error' => $e->getMessage()];
    http_response_code(500);
}

echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
