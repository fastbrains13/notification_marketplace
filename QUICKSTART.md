# Быстрый старт

## 🚀 Развертывание за 5 минут

### 1. Инициализация
```bash
php init.php
```

Это создаст:
- Директорию `storage/`
- Файл `.env` (если его нет)
- Пустую базу `storage/stores.json`

### 2. Добавьте учетные данные Telegram

Отредактируйте `.env`:
```env
TELEGRAM_BOT_TOKEN=123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11
TELEGRAM_CHAT_ID=-987654321
```

### 3. Откройте админку

```
http://localhost/admin/
```

### 4. Добавьте магазин через веб-интерфейс

Нажмите "+ Добавить магазин" и введите:
- **Название**: например "AquaCam" 
- **Затем** отредактируйте каждый маркетплейс и введите API ключи

### 5. Тестируйте

```bash
php check_orders.php
```

Должно вывести что-то вроде:
```
sent=3 skipped=0 errors=0 @ 2024-01-15 10:30:45
  ozon: 1 найдено
  wb: 1 найдено
  ym: 1 найдено
```

### 6. Настройте Cron (для автоматических проверок)

```bash
# Проверять каждые 5 минут
0 */5 * * * cd /path/to/project && php check_orders.php

# Или каждый час
0 * * * * cd /path/to/project && php check_orders.php
```

---

## 📁 Структура проекта

```
.
├── admin/                  # Веб-админка
│   └── index.php          
├── api/                    # REST API
│   └── stores.php         
├── storage/               # Данные и логи (не коммитить!)
│   ├── stores.json        # База магазинов
│   ├── sent.json          # История уведомлений
│   └── debug.log          # Логи (если LOG_VERBOSE=true)
├── config.php             # Конфигурация (теперь читает из .env + storage/)
├── functions.php          # Логика интеграций
├── db_stores.php          # Утилиты БД
├── check_orders.php       # Cron скрипт
├── init.php               # Инициализация проекта
├── migrate.php            # Миграция со старого формата
├── .env.example           # Шаблон переменных окружения
└── .env                   # Локальные переменные (в .gitignore)
```

---

## 🔐 API Ключи для маркетплейсов

### Ozon
1. Перейти в [Seller Hub](https://seller.ozon.ru)
2. Настройки → API
3. Скопировать **Client ID** и **API Key**

### Wildberries
1. Перейти в [Кабинет](https://seller.wildberries.ru)
2. Настройки → API ключи
3. Скопировать **API токен**

### Yandex Market
1. Перейти в [Партнерский кабинет](https://partner.market.yandex.ru)
2. Настройки → API
3. Скопировать **Campaign ID**, **Business ID**, **OAuth Token**

---

## 🐛 Отладка

### Проверить логи
```bash
tail -f storage/debug.log
```

### Включить подробные логи
```env
LOG_VERBOSE=true
```

### Очистить историю отправленных (для теста)
```bash
echo '{"sent":{},"last_run":{}}' > storage/sent.json
```

### Пересоздать базу
```bash
rm storage/stores.json
php init.php
```

---

## 📚 Дополнительно

- **[ADMIN_SETUP.md](ADMIN_SETUP.md)** - Полная документация админки
- **[.env.example](.env.example)** - Все переменные окружения
- **[check_orders.php](check_orders.php)** - Логика проверки заказов

---

## ✅ Чеклист

- [ ] Запустил `php init.php`
- [ ] Отредактировал `.env` с Telegram credentials
- [ ] Открыл `http://localhost/admin/`
- [ ] Добавил магазин
- [ ] Заполнил API ключи
- [ ] Запустил `php check_orders.php`
- [ ] Получил уведомление в Telegram
- [ ] Настроил Cron

Готово! 🎉
