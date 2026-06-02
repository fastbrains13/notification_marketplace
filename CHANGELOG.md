# Changelog: Миграция на ENV + Админку

## Что изменилось?

### Было
- Магазины жёстко закодированы в `config.php`
- Нужно редактировать код для добавления/удаления магазина
- Все API ключи в одном файле
- Не безопасно для production

### Стало
- Магазины хранятся в JSON базе: `storage/stores.json`
- Телеграм конфиг в `.env` файле
- Веб-админка для управления магазинами без редактирования кода
- REST API для программного управления
- Инициализация через скрипты
- Миграция со старого формата

---

## Новые файлы

### Конфигурация
- **`.env`** - Переменные окружения (не коммитить!)
- **`.env.example`** - Шаблон .env с пояснениями
- **`.gitignore`** - Исключение важных файлов

### Управление магазинами
- **`db_stores.php`** - Утилиты для работы с JSON БД магазинов
- **`admin/index.php`** - Веб-интерфейс админки
- **`api/stores.php`** - REST API для управления магазинами

### Скрипты
- **`init.php`** - Инициализация проекта (создание файлов и директорий)
- **`migrate.php`** - Миграция со старого формата config.php

### Документация
- **`QUICKSTART.md`** - Быстрый старт за 5 минут
- **`ADMIN_SETUP.md`** - Полная документация
- **`EXAMPLES.md`** - Примеры использования API и PHP
- **`CHANGELOG.md`** - Этот файл

---

## Обновленные файлы

### config.php
```diff
БЫЛО:
$config = [
    'bot_token' => '',
    'telegram_chat_id' => '',
    'stores' => [
        'AquaCam' => [ ... hardcoded ... ],
    ]
];

СТАЛО:
// Читает из .env
$config = [
    'bot_token' => getenv('TELEGRAM_BOT_TOKEN'),
    'telegram_chat_id' => getenv('TELEGRAM_CHAT_ID'),
    'stores' => load_stores(), // Из storage/stores.json
];
```

- Загружает `.env` файл
- Функция `load_stores()` читает магазины из JSON
- Никаких жёсткокодированных значений

### check_orders.php
✅ **Без изменений!** Работает как раньше. Теперь просто читает магазины из БД вместо config.php

### functions.php
✅ **Без изменений!** Вся логика интеграций осталась прежней.

---

## JSON Схема БД

### storage/stores.json

```json
{
  "AquaCam": {
    "ozon": {
      "client_id": "123456",
      "api_key": "secret_key"
    },
    "wildberries": {
      "token": "wb_token"
    },
    "yandex": {
      "campaign_id": "789",
      "business_id": "456",
      "oauth_token": "oauth_token"
    }
  },
  "MyShop": {
    "ozon": {},
    "wildberries": {},
    "yandex": {}
  }
}
```

Структура:
- **Верхний уровень** = имена магазинов
- **Второй уровень** = маркетплейсы (ozon, wildberries, yandex)
- **Третий уровень** = API ключи для маркетплейса

---

## API Endpoints

### REST API (`/api/stores.php`)

```
GET    /api/stores.php                              → Получить все магазины
GET    /api/stores.php?name=AquaCam                 → Получить магазин по имени
POST   /api/stores.php                              → Создать магазин
PUT    /api/stores.php                              → Обновить маркетплейс магазина
DELETE /api/stores.php                              → Удалить магазин
```

Примеры в `EXAMPLES.md`

---

## Функции для работы с БД

### db_stores.php

```php
load_stores(): array                    // Загрузить все магазины
db_get_store($name): ?array            // Получить по имени
db_save_store($name, $data): bool      // Создать/обновить магазин
db_delete_store($name): bool           // Удалить
db_update_marketplace($store, $market, $creds): bool // Обновить маркетплейс
validate_marketplace_creds($market, $creds): array   // Валидировать ключи
```

Примеры в `EXAMPLES.md`

---

## Миграция

### Для существующих проектов

```bash
# Шаг 1: Инициализация
php init.php

# Шаг 2: Редактировать .env с Telegram credentials
nano .env

# Шаг 3: Открыть админку и добавить магазины
# http://localhost/admin/

# Шаг 4: (опционально) Если был старый format config.php
php migrate.php
```

**Или вручную:**

1. Открыть админку: `http://localhost/admin/`
2. Добавить магазины через веб-интерфейс
3. Заполнить API ключи
4. Удалить старый код из config.php

---

## Безопасность

### Что защищено?

✅ `.env` исключен в `.gitignore` - не попадет в git
✅ API ключи хранятся только локально в `storage/`
✅ Админка защищена (требуется добавить аутентификацию в production)

### Что добавить для production?

⚠️ Защита админки через nginx htpasswd или API токены
⚠️ HTTPS для всех запросов
⚠️ Шифрование ключей в БД (рассмотреть)
⚠️ Rate limiting для API
⚠️ CORS headers

Примеры защиты в `EXAMPLES.md → Защита API (для production)`

---

## Backward Compatibility

### Старый code будет работать?

✅ **Да!** `check_orders.php` и `functions.php` не изменились.

Единственное - нужно отредактировать вызов `poll_and_notify()`:

```php
// Было (старый config.php):
$config = [ /* hardcoded stores */ ];

// Теперь (новый config.php):
require_once 'config.php'; // $config уже загружен!

$result = poll_and_notify($config);
```

---

## Миграция с GitHub

Если ваш проект уже на GitHub:

```bash
# Убедитесь что .env в .gitignore
echo ".env" >> .gitignore

# Закоммитить .env.example, db_stores.php, admin/, api/, и docs
git add .env.example db_stores.php admin/ api/ *.md .gitignore
git commit -m "chore: migrate to ENV + admin panel"

# Pull на сервер
git pull origin

# Инициализировать на сервере
php init.php
# Отредактировать .env
nano .env
# Готово!
```

---

## Откат к старому формату

Если что-то пошло не так:

```bash
# Восстановить старый config.php из git
git checkout HEAD -- config.php

# Удалить новые файлы
rm -f db_stores.php api/stores.php admin/index.php

# Сбросить storage/
rm -rf storage/
```

---

## Версионирование

- **v0.x** - Старый формат (жёсткие магазины в config.php)
- **v1.0** - Новый формат (ENV + JSON БД + админка)
  - v1.0.0 - Релиз
  - v1.0.1 - Багфиксы
  - v1.1.0 - Новые функции (шифрование, аутентификация, etc.)

---

## Лог изменений

### 2024-01-15 - v1.0.0 Release

**Новое:**
- ✨ Веб-админка для управления магазинами
- ✨ REST API для программного управления
- ✨ JSON БД вместо hardcoded config
- ✨ ENV файл для конфигурации
- ✨ Скрипты инициализации и миграции
- ✨ Полная документация

**Улучшено:**
- 🔧 Отделение конфигурации от кода
- 🔧 Удобство добавления новых магазинов
- 🔧 Безопасность (ключи не в git)

**Исправлено:**
- 🐛 Формат хранения магазинов

---

## Поддержка

Вопросы или проблемы?

- Читай `QUICKSTART.md` для быстрого старта
- Читай `EXAMPLES.md` для примеров
- Проверь `storage/debug.log` для отладки
- Смотри комментарии в коде

---

## Лицензия

Система уведомлений о заказах. Используется как есть.

---

**Последнее обновление:** 2024-01-15
