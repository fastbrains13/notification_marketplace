# Безопасность

## Текущее состояние

### ✅ Защищено по умолчанию

- **`.env` файл** исключен в `.gitignore` - никогда не попадет в git
- **`storage/` директория** исключена в `.gitignore` - не будет залита на GitHub
- **API ключи** хранятся только локально в `storage/stores.json`
- **Пароли Telegram** находятся в `.env` - не в коде

### ⚠️ Требует защиты (важно для production!)

- **Веб-админка** (`/admin/index.php`) - доступна без пароля
- **REST API** (`/api/stores.php`) - доступен без аутентификации
- **Хранилище ключей** - в открытом виде в JSON
- **Логирование** - может содержать чувствительные данные

---

## Защита для Production

### 1️⃣ Защита доступа через Nginx

**Базовая аутентификация (HTTP Auth):**

```nginx
# /etc/nginx/sites-available/notification-marketplace

server {
    listen 80;
    server_name marketplace.example.com;

    # Защита админки
    location /admin/ {
        auth_basic "Admin Only";
        auth_basic_user_file /etc/nginx/.htpasswd;
        proxy_pass http://localhost:9000;
    }

    # Защита API
    location /api/ {
        auth_basic "API Only";
        auth_basic_user_file /etc/nginx/.htpasswd;
        proxy_pass http://localhost:9000;
    }

    # Остальное (check_orders.php) - открыто
    location / {
        proxy_pass http://localhost:9000;
    }
}
```

**Создать пользователя:**

```bash
# Первый пользователь
sudo htpasswd -c /etc/nginx/.htpasswd admin
# Пароль: (введите пароль)

# Добавить еще пользователя
sudo htpasswd /etc/nginx/.htpasswd user2
# Пароль: (введите пароль)

# Проверить
sudo cat /etc/nginx/.htpasswd
```

**Использование:**

```bash
# С username/password
curl -u admin:password http://marketplace.example.com/api/stores.php

# Или через header
curl -H "Authorization: Basic YWRtaW46cGFzc3dvcmQ=" \
    http://marketplace.example.com/api/stores.php
```

---

### 2️⃣ API Токены (вместо HTTP Auth)

**Более удобно для API:**

Отредактируйте `api/stores.php`:

```php
<?php
header('Content-Type: application/json');

// Проверить API токен
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    $token = $_SERVER['HTTP_X_API_TOKEN'] ?? 
             $_GET['api_token'] ?? 
             '';
    
    if ($token !== getenv('API_TOKEN')) {
        http_response_code(403);
        die(json_encode(['ok' => false, 'error' => 'Invalid API token']));
    }
}

// ... остальной код ...
```

**В `.env`:**

```env
API_TOKEN=your_secret_token_here_min_32_chars_long_uuid_format
```

**Использование:**

```bash
# В header
curl -H "X-API-Token: your_secret_token" \
    -X POST http://localhost/api/stores.php \
    -d '{"name":"Shop"}'

# Или в query params
curl "http://localhost/api/stores.php?api_token=your_secret_token"
```

---

### 3️⃣ HTTPS/SSL

**Обязательно для production!**

```nginx
server {
    listen 443 ssl http2;
    server_name marketplace.example.com;

    # SSL сертификаты
    ssl_certificate /etc/letsencrypt/live/marketplace.example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/marketplace.example.com/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # ... остальная конфиг ...
}

# Редирект с HTTP на HTTPS
server {
    listen 80;
    server_name marketplace.example.com;
    return 301 https://$server_name$request_uri;
}
```

**Получить SSL сертификат:**

```bash
sudo apt-get install certbot python3-certbot-nginx
sudo certbot certonly --nginx -d marketplace.example.com
```

---

### 4️⃣ Rate Limiting

**Защита от brute-force:**

```nginx
# Ограничение запросов
limit_req_zone $binary_remote_addr zone=api_limit:10m rate=10r/s;
limit_req_zone $binary_remote_addr zone=admin_limit:10m rate=5r/s;

server {
    # API limit: 10 запросов в секунду
    location /api/ {
        limit_req zone=api_limit burst=20 nodelay;
        auth_basic "API Only";
        auth_basic_user_file /etc/nginx/.htpasswd;
        proxy_pass http://localhost:9000;
    }

    # Админка limit: 5 запросов в секунду
    location /admin/ {
        limit_req zone=admin_limit burst=10 nodelay;
        auth_basic "Admin Only";
        auth_basic_user_file /etc/nginx/.htpasswd;
        proxy_pass http://localhost:9000;
    }
}
```

---

### 5️⃣ Firewall

**Ограничить доступ к портам:**

```bash
# UFW (Ubuntu Firewall)

# Открыть только 80 и 443
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 22/tcp  # SSH

# Закрыть остальное
sudo ufw default deny incoming
sudo ufw enable
```

**Ограничить доступ к API с белого листа:**

```nginx
# Только с определённых IP
location /api/ {
    allow 192.168.1.0/24;      # Локальная сеть
    allow 203.0.113.0/24;      # Удаленный сервер
    deny all;

    auth_basic "API Only";
    auth_basic_user_file /etc/nginx/.htpasswd;
    proxy_pass http://localhost:9000;
}
```

---

### 6️⃣ Логирование и Мониторинг

**Включить подробное логирование:**

```env
LOG_VERBOSE=true
```

**Отслеживать логи:**

```bash
# Логи операций
tail -f storage/debug.log

# Nginx логи
tail -f /var/log/nginx/access.log
tail -f /var/log/nginx/error.log

# System логи (если запущено как сервис)
journalctl -u notification-marketplace -f
```

**Настроить ротацию логов:**

```bash
# /etc/logrotate.d/notification-marketplace
/path/to/storage/debug.log {
    daily
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

---

### 7️⃣ Шифрование API ключей

⚠️ **На данный момент ключи хранятся в открытом виде!**

Для production рассмотрите:

**Вариант 1: Использовать system keyring**

```php
// Вместо хранения в JSON, использовать OS keyring
function get_store_secret($store, $market) {
    $cmd = escapeshellcmd("security find-generic-password -w -a {$store}_{$market}");
    return shell_exec($cmd);
}
```

**Вариант 2: Шифровать в БД**

```php
// Шифровать при сохранении
function db_save_encrypted($key, $value) {
    $encrypted = openssl_encrypt($value, 'AES-256-CBC', 
        getenv('ENCRYPTION_KEY'), 
        OPENSSL_RAW_DATA
    );
    return base64_encode($encrypted);
}

// Расшифровать при чтении
function db_get_decrypted($encrypted) {
    $decoded = base64_decode($encrypted);
    return openssl_decrypt($decoded, 'AES-256-CBC',
        getenv('ENCRYPTION_KEY'),
        OPENSSL_RAW_DATA
    );
}
```

**Вариант 3: Использовать vault (HashiCorp Vault)**

```php
// Хранить ключи в внешнем хранилище
function get_secret($path) {
    $token = getenv('VAULT_TOKEN');
    $url = "https://vault.example.com/v1/secret/{$path}";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_HTTPHEADER => ["X-Vault-Token: {$token}"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    
    $response = json_decode(curl_exec($ch), true);
    return $response['data']['data'] ?? null;
}
```

---

### 8️⃣ Резервные копии

**Регулярное архивирование БД:**

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backups/notification_marketplace"
PROJECT_DIR="/var/www/notification_marketplace"
DATE=$(date +%Y-%m-%d_%H-%M-%S)

mkdir -p $BACKUP_DIR

# Архивировать storage
tar -czf "$BACKUP_DIR/storage_$DATE.tar.gz" \
    -C $PROJECT_DIR storage/

# Архивировать .env
cp $PROJECT_DIR/.env "$BACKUP_DIR/.env_$DATE"

# Удалить старые бэкапы (старше 30 дней)
find $BACKUP_DIR -name "*.tar.gz" -mtime +30 -delete
find $BACKUP_DIR -name ".env_*" -mtime +30 -delete

echo "Backup completed: $DATE"
```

**Cron задача:**

```bash
# Каждый день в 2 AM
0 2 * * * /var/www/notification_marketplace/backup.sh
```

---

### 9️⃣ Permissions и Ownership

**Правильные права доступа:**

```bash
# Чей-то пользователь / www-data для веб-сервера
cd /var/www/notification_marketplace

# Владелец файлов
sudo chown -R www-data:www-data .

# Права на файлы (644)
chmod 644 config.php functions.php *.php *.md

# Права на директории (755)
chmod 755 admin api storage

# .env файл (600 - только владелец)
chmod 600 .env

# storage директория (755, файлы 644)
chmod 755 storage
chmod 644 storage/*
```

**Проверить:**

```bash
ls -la /var/www/notification_marketplace/
```

---

### 🔟 Развертывание без проблем

**Чеклист безопасности перед production:**

- [ ] `.env` создан и заполнен
- [ ] `.env` исключен в `.gitignore`
- [ ] `storage/` исключен в `.gitignore`
- [ ] HTTPS настроен (SSL сертификат)
- [ ] HTTP Auth или API токены включены
- [ ] Rate limiting настроен в Nginx
- [ ] Firewall разрешает только 80/443
- [ ] Логирование включено (`LOG_VERBOSE=true`)
- [ ] Права доступа правильно установлены (600 для .env)
- [ ] Резервные копии настроены
- [ ] SSH ключи используются (не пароли)
- [ ] Мониторинг настроен
- [ ] X-Frame-Options header добавлен
- [ ] CORS разрешены только для trusted domains

---

## Быстрые команды

### Защита для development

```bash
# Создать .env
cp .env.example .env

# Защитить .env
chmod 600 .env

# Добавить в .gitignore
echo ".env" >> .gitignore
echo "storage/" >> .gitignore

# Инициализировать
php init.php
```

### Защита для production

```bash
# 1. Создать пользователя для HTTP Auth
sudo htpasswd -c /etc/nginx/.htpasswd admin

# 2. Настроить Nginx (смотри примеры выше)
sudo nano /etc/nginx/sites-available/notification-marketplace

# 3. Включить HTTPS
sudo certbot certonly --nginx -d marketplace.example.com

# 4. Перезагрузить Nginx
sudo systemctl reload nginx

# 5. Настроить права доступа
chmod 600 /var/www/notification_marketplace/.env

# 6. Запустить
php /var/www/notification_marketplace/check_orders.php
```

---

## Типичные уязвимости и защита

| Уязвимость | Защита |
|-----------|--------|
| API доступен без пароля | HTTP Auth или API токены |
| Админка открыта для всех | Nginx auth_basic |
| Ключи в git | .gitignore для .env и storage/ |
| HTTP вместо HTTPS | Let's Encrypt SSL |
| Доступ ко всем портам | UFW firewall |
| Brute-force атаки | Rate limiting в Nginx |
| Логирование чувствительных данных | Скрывать ключи в логах |
| Отсутствие резервных копий | Автоматизированные backup'ы |
| Плохие права доступа | chmod 600 для .env |

---

## Рекомендуемая архитектура (production)

```
┌─────────────────┐
│   Internet      │
└────────┬────────┘
         │ :443 (HTTPS)
         ▼
┌─────────────────────────────┐
│     Nginx (Reverse Proxy)   │
│  - SSL/TLS (Let's Encrypt)  │
│  - HTTP Auth                │
│  - Rate limiting            │
│  - Security headers         │
└────────────┬────────────────┘
             │ :9000 (localhost)
             ▼
┌─────────────────────────────┐
│   PHP Application           │
│  - config.php (из .env)     │
│  - storage/stores.json      │
│  - API endpoints            │
│  - Admin panel              │
└────────────┬────────────────┘
             │
             ▼
┌─────────────────────────────┐
│   External APIs             │
│  - Ozon                     │
│  - Wildberries              │
│  - Yandex Market            │
│  - Telegram Bot             │
└─────────────────────────────┘

Резервная копия каждый день в 2 AM
```

---

## Поддержка

Вопросы по безопасности?

- Прочитайте этот файл полностью
- Проверьте `EXAMPLES.md → Защита API`
- Консультируйтесь с системным администратором
- Используйте инструменты типа OWASP ZAP для тестирования

---

**Безопасность - это не продукт, это процесс!**

Постоянно обновляйте, мониторьте, и улучшайте.

---

**Последнее обновление:** 2024-01-15
