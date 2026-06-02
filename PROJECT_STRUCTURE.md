# Структура проекта

## Дерево файлов

```
notification_marketplace/
│
├── 📄 Конфигурация и основное
│   ├── config.php                 # 🔑 Главная конфиг (читает из .env + storage/)
│   ├── functions.php              # 🔧 Логика интеграций (Telegram, API маркетплейсов)
│   ├── check_orders.php           # ⏰ Cron скрипт для проверки заказов
│   ├── .env                       # 🔐 Локальные переменные (в .gitignore!)
│   ├── .env.example               # 📋 Шаблон для .env
│   └── .gitignore                 # 🚫 Исключения из git
│
├── 📚 Управление магазинами
│   ├── db_stores.php              # 💾 Утилиты для JSON БД магазинов
│   ├── api/
│   │   └── stores.php             # 🌐 REST API для управления магазинами
│   └── admin/
│       └── index.php              # 🖥️  Веб-админка (красивый интерфейс)
│
├── 📜 Скрипты утилиты
│   ├── init.php                   # 🚀 Инициализация при первом запуске
│   └── migrate.php                # 🔄 Миграция со старого format
│
├── 📁 Хранилище данных (НЕ коммитить!)
│   └── storage/
│       ├── stores.json            # 🏪 База магазинов и API ключей
│       ├── sent.json              # 📨 История отправленных уведомлений
│       └── debug.log              # 🐛 Логи (если LOG_VERBOSE=true)
│
└── 📖 Документация
    ├── README.md                  # Обзор проекта
    ├── QUICKSTART.md              # ⚡ Быстрый старт (5 минут)
    ├── ADMIN_SETUP.md             # 📚 Полная документация админки
    ├── EXAMPLES.md                # 💡 Примеры использования API и PHP
    ├── CHANGELOG.md               # 📝 Что изменилось (миграция)
    └── PROJECT_STRUCTURE.md       # 📊 Этот файл
```

---

## Файлы по функциям

### 🔐 Конфигурация

| Файл | Размер | Назначение |
|------|--------|-----------|
| `config.php` | 979 B | Главная конфиг. Читает из `.env` и `storage/stores.json` |
| `.env` | ? | Переменные окружения (Telegram credentials) |
| `.env.example` | 174 B | Шаблон `.env` |

**Поток:**
```
.env  →  config.php  →  check_orders.php / functions.php
   ↓
storage/stores.json  →  config.php  →  poll_and_notify()
```

---

### 🔧 Логика интеграций

| Файл | Размер | Назначение |
|------|--------|-----------|
| `functions.php` | 15 KB | Все API вызовы (Telegram, Ozon, Wildberries, Yandex) |
| `check_orders.php` | 680 B | Cron скрипт для проверки заказов |

**Маркетплейсы:**
- 🟠 **Ozon** - `ozon_list_since()`, `ozon_parse_orders()`
- 🟡 **Wildberries** - `wb_list_new()`, `wb_parse_orders()`
- 🔵 **Yandex Market** - `ym_list_processing()`, `ym_parse_orders()`
- 📱 **Telegram** - `tg_send()` для отправки уведомлений

---

### 💾 Управление магазинами

| Файл | Размер | Назначение |
|------|--------|-----------|
| `db_stores.php` | 2.7 KB | API для работы с JSON БД магазинов |
| `api/stores.php` | ~3 KB | REST API endpoints |
| `admin/index.php` | ~18 KB | Веб-админка (HTML + CSS + JS) |

**JSON БД:** `storage/stores.json`
```json
{
  "AquaCam": {
    "ozon": { "client_id": "...", "api_key": "..." },
    "wildberries": { "token": "..." },
    "yandex": { "campaign_id": "...", "..." }
  }
}
```

---

### 🚀 Утилиты

| Файл | Назначение |
|------|-----------|
| `init.php` | Создает `storage/`, `.env`, `stores.json` при первом запуске |
| `migrate.php` | Переносит магазины из старого `config.php` в новую БД |

---

### 📁 Хранилище

| Файл | Назначение |
|------|-----------|
| `storage/stores.json` | ✅ Основная БД магазинов |
| `storage/sent.json` | Дедупликация уведомлений (не отправлять дубли) |
| `storage/debug.log` | Логи операций (если `LOG_VERBOSE=true`) |

⚠️ **Исключить из git:** добавлено в `.gitignore`

---

### 📖 Документация

| Файл | Размер | Для кого |
|------|--------|----------|
| `README.md` | 2 KB | Быстрый обзор |
| `QUICKSTART.md` | 4 KB | Новичков (старт за 5 минут) |
| `ADMIN_SETUP.md` | 4.5 KB | Администраторов |
| `EXAMPLES.md` | 8 KB | Разработчиков (cURL, PHP примеры) |
| `CHANGELOG.md` | 9 KB | История изменений |
| `PROJECT_STRUCTURE.md` | этот файл | Структура проекта |

---

## Использование (шаг за шагом)

### 1️⃣ Инициализация

```bash
php init.php
```

Создает:
- ✅ Директория `storage/`
- ✅ Файл `.env` (из `.env.example`)
- ✅ Файл `storage/stores.json` (пустой)

### 2️⃣ Конфигурация

```bash
nano .env
```

Заполните:
- `TELEGRAM_BOT_TOKEN` (от @BotFather)
- `TELEGRAM_CHAT_ID` (ID чата для уведомлений)

### 3️⃣ Добавить магазины

**Опция A: Через админку (рекомендуется)**
```
http://localhost/admin/
```

**Опция B: Через API**
```bash
curl -X POST http://localhost/api/stores.php -d '{"name":"AquaCam"}'
```

**Опция C: Через миграцию (если было старое config.php)**
```bash
php migrate.php
```

### 4️⃣ Добавить API ключи

**Через админку:**
- Откройте http://localhost/admin/
- Выберите магазин
- Нажмите "Редактировать" для каждого маркетплейса
- Заполните ключи

### 5️⃣ Тестировать

```bash
php check_orders.php
```

Должно вывести количество найденных заказов.

### 6️⃣ Настроить Cron

```bash
# Каждые 5 минут
0 */5 * * * cd /path/to/project && php check_orders.php

# Или каждый час
0 * * * * cd /path/to/project && php check_orders.php
```

---

## Возвращаемые значения

### config.php

```php
$config = [
    'bot_token' => 'xxx...',           // из .env
    'telegram_chat_id' => '123...',    // из .env
    'stores' => [                       // из storage/stores.json
        'AquaCam' => [
            'ozon' => [...],
            'wildberries' => [...],
            'yandex' => [...]
        ]
    ]
];
```

### functions.php - poll_and_notify()

```php
[
    'sent' => 5,                  // Отправлено уведомлений
    'skipped' => 2,               // Уже отправленных ранее
    'errors' => [
        ['market' => 'ozon', 'id' => '123', 'error' => 'API error']
    ],
    'markets' => [
        'ozon' => 10,
        'wb' => 5,
        'ym' => 3
    ]
]
```

### db_stores.php - db_get_all_stores()

```php
[
    'AquaCam' => [
        'ozon' => ['client_id' => '...', 'api_key' => '...'],
        'wildberries' => ['token' => '...'],
        'yandex' => ['campaign_id' => '...', 'business_id' => '...', 'oauth_token' => '...']
    ],
    'MyShop' => [...]
]
```

---

## Безопасность

### ✅ Защищено

- `.env` исключен в `.gitignore` - не попадет в git
- `storage/` исключен в `.gitignore` - не попадет в git
- API ключи хранятся только локально

### ⚠️ Требует защиты (production)

- Админка (`/admin/`) - добавить authentication
- API (`/api/stores.php`) - добавить authentication
- Шифрование ключей в БД
- HTTPS
- Rate limiting

Примеры: смотри `EXAMPLES.md → Защита API`

---

## Миграция с GitHub

### Если проект уже на GitHub

```bash
# Клонировать
git clone https://github.com/user/notification_marketplace.git
cd notification_marketplace

# Инициализировать
php init.php

# Редактировать .env
nano .env

# Добавить магазины через админку
# http://localhost/admin/

# Готово!
```

### Коммитить в GitHub

```bash
git add .
git commit -m "chore: setup notification marketplace"

# НЕ коммитить:
# - .env
# - storage/
```

---

## Откат (если что-то сломалось)

```bash
# Удалить новые данные
rm -rf storage/
rm -f db_stores.php api/stores.php admin/index.php

# Восстановить старый config.php
git checkout HEAD -- config.php

# Начать заново
php init.php
```

---

## Типичные операции

| Задача | Команда | Файл |
|--------|---------|------|
| Добавить магазин | Админка или API | `api/stores.php` |
| Проверить логи | `tail -f storage/debug.log` | `functions.php` |
| Очистить историю | `rm storage/sent.json` | `functions.php` |
| Мигрировать со старого | `php migrate.php` | `migrate.php` |
| Инициализировать | `php init.php` | `init.php` |
| Проверить конфиг | `php -r "require 'config.php'; print_r(\$config);"` | `config.php` |

---

## Переменные окружения (.env)

| Переменная | Пример | Описание |
|------------|--------|---------|
| `TELEGRAM_BOT_TOKEN` | `123456:ABC...` | Токен бота от @BotFather |
| `TELEGRAM_CHAT_ID` | `-987654321` | ID чата для уведомлений |
| `STORES_DB_PATH` | `storage/stores.json` | Путь к базе магазинов |
| `LOG_VERBOSE` | `true` | Подробные логи в debug.log |
| `DEFAULT_LOOKBACK_SECONDS` | `600` | Период проверки при первом запуске |

---

## Размеры файлов

```
.env.example          174 B      📝 Шаблон
config.php            979 B      🔑 Конфиг (979 B)
db_stores.php      2.7 KB        💾 БД утилиты
init.php           2.4 KB        🚀 Инициализация
migrate.php        3.1 KB        🔄 Миграция
api/stores.php     ~3.5 KB       🌐 API endpoints
admin/index.php   ~18 KB         🖥️  Админка
functions.php     15.3 KB        🔧 Основная логика
check_orders.php    680 B        ⏰ Cron скрипт
───────────────────────────
ИТОГО:           ~50 KB + docs
```

---

## Лог последних изменений

```
2024-01-15 ✨ Добавлена админка и API
2024-01-15 🔄 Миграция на ENV + JSON БД
2024-01-14 🐛 Bugfixes в functions.php
```

---

## Контакты и помощь

- 📖 Документация: смотри `*.md` файлы
- 💡 Примеры: смотри `EXAMPLES.md`
- 🚀 Быстрый старт: смотри `QUICKSTART.md`
- 🐛 Отладка: проверь `storage/debug.log`

---

**Последнее обновление:** 2024-01-15
**Версия:** 1.0.0
