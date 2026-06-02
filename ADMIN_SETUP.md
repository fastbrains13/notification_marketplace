# Администратор магазинов

## Быстрый старт

### 1. Копировать и настроить `.env` файл

```bash
cp .env.example .env
```

Заполните в `.env`:
- `TELEGRAM_BOT_TOKEN` - токен вашего Telegram бота
- `TELEGRAM_CHAT_ID` - ID чата, куда отправляются уведомления
- `STORES_DB_PATH` - путь к JSON файлу с магазинами (по умолчанию: `storage/stores.json`)

### 2. Доступ к админке

Откройте в браузере:
```
http://localhost/admin/
```

## Структура

### API Endpoints

Все API находятся в `/api/stores.php`:

#### GET /api/stores.php
Получить все магазины
```bash
curl http://localhost/api/stores.php
```

#### POST /api/stores.php
Создать новый магазин
```bash
curl -X POST http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{"name":"AquaCam"}'
```

#### PUT /api/stores.php
Обновить API ключи маркетплейса
```bash
curl -X PUT http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{
    "storeName":"AquaCam",
    "marketplace":"ozon",
    "client_id":"123456",
    "api_key":"your_key_here"
  }'
```

#### DELETE /api/stores.php
Удалить магазин
```bash
curl -X DELETE http://localhost/api/stores.php \
  -H "Content-Type: application/json" \
  -d '{"name":"AquaCam"}'
```

### Файловая структура

```
.
├── config.php              # Основная конфигурация (читает из .env и storage/stores.json)
├── functions.php           # Логика интеграций (Telegram, Ozon, Wildberries, Yandex)
├── check_orders.php        # Cron скрипт для проверки заказов
├── db_stores.php           # Утилиты для управления БД магазинов
├── admin/
│   └── index.php           # Веб-интерфейс админки
├── api/
│   └── stores.php          # REST API для управления магазинами
├── storage/
│   ├── stores.json         # База с магазинами (создаётся автоматически)
│   ├── sent.json           # История отправленных уведомлений
│   └── debug.log           # Логи (если LOG_VERBOSE=true)
├── .env.example            # Пример переменных окружения
├── .env                    # Локальные переменные (не коммитить!)
└── .gitignore              # Исключения из git
```

### Схема JSON (storage/stores.json)

```json
{
  "AquaCam": {
    "ozon": {
      "client_id": "123456",
      "api_key": "your_key"
    },
    "wildberries": {
      "token": "your_token"
    },
    "yandex": {
      "campaign_id": "789",
      "business_id": "456",
      "oauth_token": "your_oauth"
    }
  },
  "MyShop": {
    "ozon": {},
    "wildberries": {},
    "yandex": {}
  }
}
```

## Миграция со старого формата

Если у вас был старый `config.php` с жёстко закодированными магазинами:

1. **Создайте новый магазин** через админку
2. **Введите все API ключи** через веб-интерфейс
3. **Удалите старый формат** из `config.php`

Теперь `config.php` автоматически читает магазины из `storage/stores.json`!

## Безопасность

⚠️ **Важно:**
- Никогда не коммитьте `.env` файл в git
- Обеспечьте доступ к админке через аутентификацию (nginx htpasswd, etc.)
- API ключи хранятся в открытом виде в JSON - используйте шифрование в production
- Ограничьте доступ к `admin/` и `api/` через firewall или веб-сервер

## Использование в Cron

Скрипт `check_orders.php` автоматически читает магазины из `storage/stores.json`:

```bash
0 */5 * * * php /path/to/check_orders.php
```

Всё остальное работает как раньше, но теперь магазины загружаются из БД!
