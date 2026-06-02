# Примеры использования

## Работа с API через cURL

### 1. Получить все магазины

```bash
curl http://localhost/api/stores.php
```

Ответ:
```json
{
  "ok": true,
  "data": {
    "AquaCam": {
      "ozon": {
        "client_id": "123456",
        "api_key": "secret_key"
      },
      "wildberries": {
        "token": "wb_token"
      },
      "yandex": {}
    }
  }
}
```

### 2. Создать новый магазин

```bash
curl -X POST http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{"name":"MyNewShop"}'
```

Ответ:
```json
{
  "ok": true,
  "data": {
    "ozon": {},
    "wildberries": {},
    "yandex": {}
  }
}
```

### 3. Обновить API ключи Ozon

```bash
curl -X PUT http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{
    "storeName": "AquaCam",
    "marketplace": "ozon",
    "client_id": "new_client_id",
    "api_key": "new_api_key"
  }'
```

### 4. Обновить токен Wildberries

```bash
curl -X PUT http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{
    "storeName": "AquaCam",
    "marketplace": "wildberries",
    "token": "new_wb_token"
  }'
```

### 5. Обновить Yandex Market

```bash
curl -X PUT http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{
    "storeName": "AquaCam",
    "marketplace": "yandex",
    "campaign_id": "123",
    "business_id": "456",
    "oauth_token": "yandex_oauth_token"
  }'
```

### 6. Удалить магазин

```bash
curl -X DELETE http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{"name":"MyNewShop"}'
```

---

## Работа в PHP коде

### Получить все магазины

```php
<?php
require_once 'db_stores.php';

$stores = db_get_all_stores();
foreach ($stores as $name => $config) {
    echo "Store: $name\n";
    print_r($config);
}
```

### Получить конкретный магазин

```php
<?php
require_once 'db_stores.php';

$store = db_get_store('AquaCam');
if ($store) {
    echo "Ozon client ID: " . $store['ozon']['client_id'];
}
```

### Создать/обновить магазин

```php
<?php
require_once 'db_stores.php';

$storeData = [
    'ozon' => [
        'client_id' => '123456',
        'api_key' => 'secret'
    ],
    'wildberries' => [
        'token' => 'wb_token'
    ],
    'yandex' => []
];

db_save_store('MyShop', $storeData);
```

### Обновить маркетплейс

```php
<?php
require_once 'db_stores.php';

db_update_marketplace('AquaCam', 'ozon', [
    'client_id' => 'new_id',
    'api_key' => 'new_key'
]);
```

### Удалить магазин

```php
<?php
require_once 'db_stores.php';

db_delete_store('AquaCam');
```

### Валидировать учетные данные

```php
<?php
require_once 'db_stores.php';

$errors = validate_marketplace_creds('ozon', [
    'client_id' => '123',
    'api_key' => ''  // Ошибка: пусто!
]);

if ($errors) {
    print_r($errors); // Array ( [0] => api_key required )
}
```

---

## Пример Cron скрипта

```php
<?php
// my_custom_cron.php
declare(strict_types=1);

require_once 'config.php';
require_once 'functions.php';
require_once 'db_stores.php';

// Скрипт автоматически читает магазины из БД через config.php
$result = poll_and_notify($config);

// Дополнительная логика
if ($result['sent'] > 0) {
    echo "Sent {$result['sent']} notifications\n";
}

if (!empty($result['errors'])) {
    foreach ($result['errors'] as $error) {
        error_log("Order sync error: " . json_encode($error));
    }
}
```

Запуск:
```bash
php my_custom_cron.php
```

---

## Примеры .env файлов

### Development (локальный тест)

```env
# Telegram (получи от @BotFather)
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHAT_ID=-987654321

# Database
STORES_DB_PATH=storage/stores.json

# Debug
LOG_VERBOSE=true
DEFAULT_LOOKBACK_SECONDS=600
```

### Production (сервер)

```env
TELEGRAM_BOT_TOKEN=${TELEGRAM_BOT_TOKEN}
TELEGRAM_CHAT_ID=${TELEGRAM_CHAT_ID}

STORES_DB_PATH=/var/lib/notification_marketplace/stores.json

LOG_VERBOSE=false
DEFAULT_LOOKBACK_SECONDS=300
```

---

## Миграция со старого формата

### Если у вас был старый config.php:

```php
<?php
// Старый формат
$config = [
    'bot_token' => 'xxx',
    'telegram_chat_id' => 'yyy',
    'stores' => [
        'AquaCam' => [
            'ozon' => [
                'client_id' => '123',
                'api_key' => 'secret'
            ],
            // ...
        ]
    ]
];
```

### Миграция:

```php
<?php
require_once 'db_stores.php';

// 1. Переместить Telegram в .env
// .env:
// TELEGRAM_BOT_TOKEN=xxx
// TELEGRAM_CHAT_ID=yyy

// 2. Перенести магазины в БД
$config = [ /* старый формат */ ];

foreach ($config['stores'] as $name => $data) {
    db_save_store($name, $data);
}

// Готово! Теперь все магазины в storage/stores.json
```

Или использовать встроенный скрипт:

```bash
php migrate.php
```

---

## Запуск и отладка

### Проверить, что config загружает магазины

```bash
php -r "
require_once 'config.php';
echo 'Bot token: ' . ($config['bot_token'] ?: 'NOT SET') . PHP_EOL;
echo 'Stores: ' . count($config['stores']) . PHP_EOL;
print_r($config['stores']);
"
```

### Включить verbose логирование

```env
LOG_VERBOSE=true
```

Логи в `storage/debug.log`:

```bash
tail -f storage/debug.log
```

### Очистить и перезапустить

```bash
rm storage/sent.json
rm storage/debug.log
php init.php
```

---

## Типичные ошибки

### "Store not found"
Убедитесь, что магазин создан через админку и имя совпадает (регистр имеет значение).

### "API Key required"
При сохранении API ключей поля помечены как обязательные. Заполните все поля для маркетплейса.

### Notifications not sending
1. Проверьте `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID` в `.env`
2. Проверьте логи: `tail -f storage/debug.log`
3. Убедитесь, что бот добавлен в чат и имеет права на отправку сообщений

### Permissions denied (storage/)
```bash
chmod 775 storage/
chmod 644 storage/*.json
```

---

## Защита API (для production)

### Базовая аутентификация в nginx

```nginx
location /api/ {
    auth_basic "Restricted";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://localhost:9000;
}

location /admin/ {
    auth_basic "Admin Panel";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://localhost:9000;
}
```

Создать пользователя:
```bash
htpasswd -c /etc/nginx/.htpasswd admin
```

### API токен в PHP

```php
<?php
// api/stores.php - добавить в начало
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? '';
    if ($token !== getenv('API_TOKEN')) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Unauthorized']));
    }
}
```

Использование:
```bash
curl -H "X-API-Token: secret_token" \
  -X POST http://localhost/api/stores.php \
  -d '{"name":"Shop"}'
```
