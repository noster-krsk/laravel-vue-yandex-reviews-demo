# Yandex Maps Review Parser

Интеграция с Яндекс Картами для парсинга и отображения отзывов компаний. Приложение позволяет авторизованным пользователям загружать отзывы из Яндекс Карт, просматривать рейтинги и статистику.

## Лицензия

BSD 2-Clause License - см. файл [LICENSE](LICENSE)

## Стек технологий

### Backend
- **Framework**: Laravel 11+
- **Database**: SQLite / MySQL
- **API**: RESTful с использованием Laravel Sanctum для аутентификации
- **Queue**: Database-driven queue
- **Parser**: Puppeteer (Node.js) для парсинга Яндекс Карт

### Frontend
- **Framework**: Vue 3
- **Build Tool**: Vite
- **State Management**: Pinia
- **HTTP Client**: Axios
- **Routing**: Vue Router
- **Styling**: CSS 3

## Функциональность

### 1. Авторизация (логин/пароль)
- Страница входа с валидацией учётных данных
- Регистрация новых пользователей
- JWT токены (Laravel Sanctum) для аутентификации
- Защита всех приватных маршрутов

**Endpoints:**
- `POST /api/login` - вход в систему
- `POST /api/register` - создание новой учётной записи
- `GET /api/user` - получение данных текущего пользователя
- `POST /api/logout` - выход из системы

### 2. Страница настроек
- Вставка ссылки на Яндекс Карты
- Сохранение конфигурации пользователя
- Валидация URL формата

**Endpoints:**
- `GET /api/settings` - получение текущих настроек
- `POST /api/settings` - сохранение настроек

**Требуемый формат URL:**
```
https://yandex.ru/maps/org/{organization_name}/{org_id}/reviews/
```

### 3. Отзывы компании
- Отображение всех отзывов в пагинированном виде
- Фильтрация по рейтингу
- Поддержка различных рангов
- Статус парсинга в реальном времени

**Endpoints:**
- `GET /api/reviews?page=1` - получение отзывов с пагинацией
- `POST /api/reviews/parse` - принудительное обновление отзывов

### 4. Рейтинг и статистика
- Общий рейтинг компании (из 5)
- Количество отзывов всего
- Количество отзывов по каждой категории рейтинга
- Время последнего обновления

**Отображаемая информация:**
```json
{
  "statistics": {
    "rating": 4.5,
    "total_reviews": 250,
    "by_rating": {
      "5": 150,
      "4": 60,
      "3": 20,
      "2": 15,
      "1": 5
    }
  }
}
```

## Клонирование репозитория

```bash
git clone https://github.com/noster-krsk/laravel-vue-yandex-reviews-demo.git
cd laravel-vue-yandex-reviews-demo
```

## Установка и запуск

### Требования
- PHP 8.2+
- Node.js 18+
- SQLite или MySQL
- Chromium/Chrome (для парсера)
- Puppeteer
- npm или yarn

### Backend (Laravel)

1. **Перейти в директорию backend:**
```bash
cd private/backend
```

2. **Установить зависимости PHP (Composer):**
```bash
composer install
```

3. **Скопировать .env файл:**
```bash
cp .env.example .env
```

4. **Сгенерировать ключ приложения:**
```bash
php artisan key:generate
```

5. **Выполнить миграции:**
```bash
php artisan migrate
```

6. **Запустить сервер (development):**
```bash
php artisan serve
```

### Frontend (Vue 3)

1. **Перейти в директорию frontend:**
```bash
cd private/frontend
```

2. **Установить зависимости:**
```bash
npm install
```

3. **Запустить development сервер:**
```bash
npm run dev
```

4. **Build для production:**
```bash
npm run build
```

Собранные файлы будут в `backend/public/dist`.

### Node.js Парсер Яндекс Карт

Парсер использует Puppeteer для автоматизированной загрузки отзывов из Яндекс Карт.

1. **Перейти в директорию скрипта:**
```bash
cd private/backend/scripts
```

2. **Установить Node.js зависимости:**
```bash
npm install
```

**Требуемые пакеты (package.json):**
```json
{
  "dependencies": {
    "puppeteer": "^21.0.0"
  }
}
```

3. **Запустить парсер вручную:**
```bash
# Парсить конкретный URL
node parse-yandex.js "https://yandex.ru/maps/org/restaurant/123456/reviews/"

# С дополнительными параметрами
node parse-yandex.js "https://yandex.ru/maps/org/restaurant/123456/reviews/" --headless=true --debug=false
```

4. **Параметры парсера:**
- `url` - Обязательный параметр, ссылка на страницу отзывов Яндекс Карт
- `--headless=true|false` - Запуск Chrome в headless режиме (по умолчанию true)
- `--debug=false|true` - Вывод debug информации (по умолчанию false)

5. **Интеграция с Laravel через Artisan команду:**
```bash
# Парсить для конкретного пользователя
php artisan parse:yandex-reviews --user=1

# Парсить для всех пользователей
php artisan parse:yandex-reviews --all

# Парсить конкретный URL
php artisan parse:yandex-reviews --url="https://yandex.ru/maps/org/..."
```

6. **Автоматический парсинг через очередь:**

Команда Laravel запускает Node.js парсер в фоновом процессе через очередь:
```bash
# Запустить worker для очереди
php artisan queue:work database --sleep=3 --tries=3
```

7. **Мониторинг парсера:**
```bash
# Просмотр логов
tail -f private/backend/storage/logs/laravel.log
tail -f private/backend/storage/app/{org_id}/node.log
tail -f private/backend/storage/app/{org_id}/batch__meta.log
tail -f private/backend/storage/app/{org_id}/batch__page_{page_id}.log

# Статус парсинга
php artisan parse:yandex-reviews --status

# Остановить текущий парсинг
pkill -f "parse-yandex"
```

### Полная сборка проекта

1. **Установка всех зависимостей одной командой:**
```bash
# Backend
cd private/backend
composer install
npm install

# Frontend
cd ../frontend
npm install

# Parser (Node.js скрипт)
cd ../backend/scripts
npm install
```

2. **Build проекта:**
```bash
# Backend build (PHP)
cd private/backend
php artisan optimize

# Frontend build (Vue)
cd ../frontend
npm run build

# Скрипт парсера готов к использованию
cd ../backend/scripts
# parse-yandex.js готов к запуску через Node.js
```

3. **Проверка установки:**
```bash
# Проверить PHP
php -v

# Проверить Composer
composer -V

# Проверить Node.js
node -v
npm -v

# Проверить Puppeteer
cd private/backend/scripts && npm list puppeteer
```

## Конфигурация

### Переменные окружения (.env)

**Основные параметры:**
```env
APP_NAME=Yandex Maps Parser
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=your_db
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Yandex Maps API (если требуется)
YANDEX_MAPS_API_KEY=your_api_key

# Queue
QUEUE_CONNECTION=database

# Cache
CACHE_STORE=database

# Session
SESSION_DRIVER=database
```

## Архитектура проекта

### Backend структура

```
app/
├── Console/
│   └── Commands/
│       └── ParseYandexReviews.php    # Команда для парсинга отзывов
├── Http/
│   └── Controllers/Api/
│       ├── AuthController.php        # Аутентификация
│       ├── ReviewController.php      # Отзывы
│       └── SettingsController.php    # Настройки пользователя
├── Models/
│   ├── User.php                      # Модель пользователя
│   ├── Review.php                    # Модель отзыва
│   ├── Setting.php                   # Настройки пользователя
│   └── ParserTask.php                # История парсинга
├── Services/
│   ├── ReviewParserService.php       # Бизнес-логика парсинга
│   ├── YandexMapsParser.php          # Парсер данных
│   └── YandexMapsService.php         # API сервис Яндекса
└── Jobs/
    └── ParseReviewPageJob.php        # Queue job для парсинга
```

### Frontend структура

```
src/
├── views/
│   ├── LoginView.vue                 # Страница входа
│   ├── ReviewsView.vue               # Список отзывов
│   └── SettingsView.vue              # Страница настроек
├── layouts/
│   └── AppLayout.vue                 # Главный layout
├── stores/
│   └── auth.js                       # Pinia store для auth
├── router/
│   └── index.js                      # Vue Router конфигурация
├── api/
│   └── axios.js                      # HTTP клиент
└── assets/                           # Статические файлы
```

## Парсер Яндекс Карт (Node.js + Puppeteer)

Приложение использует Puppeteer (Node.js) для автоматизированного парсинга отзывов с Яндекс Карт.

### Архитектура парсера

```
private/backend/
├── scripts/
│   ├── package.json          # Зависимости Node.js
│   ├── parse-yandex.js       # Основной скрипт парсера
│   └── run_parser.sh         # Shell скрипт для запуска
├── app/
│   ├── Console/Commands/
│   │   └── ParseYandexReviews.php    # Laravel команда для парсинга
│   ├── Services/
│   │   ├── YandexMapsParser.php      # Обёртка парсера
│   │   └── ReviewParserService.php   # Бизнес-логика
│   └── Jobs/
│       └── ParseReviewPageJob.php    # Queue job
├── storage/logs/app/parser_cookies/{org_id}/
│   └── batch_meta.log                       # Полученная информация по карточке
│   └── batcр__page_{id_page}.log            # Список полученных отзывов
└── storage/logs/
│   └── laravel.log            # Логи парсера
```

### Как работает парсер

1. **Запуск**: Laravel команда или Queue job запускают Node.js скрипт
2. **Инициализация Chromium**: Puppeteer запускает браузер с маскировкой
3. **Загрузка страницы**: Открывается страница отзывов Яндекс Карт
4. **Перехват API**: Получаются параметры для API запросов к Яндексу
5. **Парсинг по страницам**: Последовательные запросы к API Яндекса (50 отзывов за раз)
6. **Обработка данных**: Отзывы обрабатываются и сохраняются в БД

### Node.js скрипт: parse-yandex.js

**Основные функции:**
- Парсинг отзывов по различным рангам (helpful, popular, recent)
- Извлечение параметров организации (название, ID, рейтинг)
- Логирование всех этапов парсинга
- Обработка ошибок и повторные попытки

**Использование:**
```bash
# Простой запуск
node scripts/parse-yandex.js "https://yandex.ru/maps/org/..."

# С опциями
node scripts/parse-yandex.js "URL" --headless=true --timeout=60000
```

**Выходные данные:**
```json
{
  "org_id": "123456",
  "org_name": "Название организации",
  "reviews_count": 250,
  "rating": 4.5,
  "reviews": [
    {
      "id": "rev_123",
      "author": "Иван И.",
      "rating": 5,
      "text": "Отличное место!",
      "date": "2024-02-13",
      "helpful_count": 10
    }
  ]
}
```

### Laravel команда: ParseYandexReviews

**Использование:**
```bash
# Парсить для конкретного пользователя
php artisan parse:yandex-reviews --user=1

# Парсить для всех пользователей с настроенным URL
php artisan parse:yandex-reviews --all

# Парсить конкретный URL
php artisan parse:yandex-reviews --url="https://yandex.ru/maps/org/..."
```

**Логика:**
1. Получает URL из настроек пользователя
2. Запускает Node.js скрипт
3. Сохраняет результаты в БД
4. Обновляет статус парсинга
5. Логирует все ошибки и события

## API Endpoints

### Аутентификация

| Метод | Endpoint | Описание |
|-------|----------|---------|
| POST | `/api/login` | Вход в систему |
| POST | `/api/register` | Регистрация |
| GET | `/api/user` | Получить текущего пользователя |
| POST | `/api/logout` | Выход (требует auth) |

### Отзывы

| Метод | Endpoint | Описание |
|-------|----------|---------|
| GET | `/api/reviews?page=1` | Список отзывов с пагинацией |
| POST | `/api/reviews/parse` | Начать парсинг отзывов |

### Настройки

| Метод | Endpoint | Описание |
|-------|----------|---------|
| GET | `/api/settings` | Получить настройки пользователя |
| POST | `/api/settings` | Сохранить Yandex URL |

## Развёртывание

### На VDS / Выделенном сервере

1. **Клонировать репозиторий:**
```bash
git clone https://github.com/noster-krsk/laravel-vue-yandex-reviews-demo.git
cd laravel-vue-yandex-reviews-demo
```

2. **Установить зависимости:**
```bash
# Backend (Laravel + PHP)
cd private/backend
composer install --optimize-autoloader --no-dev

# Parser (Node.js)
cd scripts
npm install --production
cd ..

# Frontend (Vue)
cd ../frontend
npm install --production
npm run build
```

3. **Установить системные зависимости:**
```bash
# Для Ubuntu/Debian
sudo apt-get update
sudo apt-get install -y \
    php8.2-fpm \
    php8.2-mysql \
    php8.2-curl \
    php8.2-gd \
    php8.2-mbstring \
    php8.2-xml \
    nodejs \
    npm \
    chromium-browser

# Для CentOS/RHEL
sudo yum install -y \
    php82-fpm \
    php82-mysqlnd \
    php82-curl \
    php82-gd \
    php82-mbstring \
    php82-xml \
    nodejs \
    chromium
```

4. **Настроить переменные окружения:**
```bash
cd private/backend
cp .env.example .env

# Отредактировать .env файл с реальными значениями
nano .env

# Сгенерировать ключ приложения
php artisan key:generate

# Выполнить миграции
php artisan migrate --force
```

5. **Настроить Supervisor для Queue Worker:**
```bash
sudo nano /etc/supervisor/conf.d/yandex-parser-worker.conf
```

Содержание файла:
```ini
[program:yandex-parser-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/your-domain/private/backend/artisan queue:work database --sleep=3 --tries=3
directory=/var/www/your-domain/private/backend
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/yandex-parser-worker.log
user=www-data
```

Перезагрузить supervisor:
```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start yandex-parser-worker:*
```

6. **Запланировать парсинг (Cron):**
```bash
# Добавить в crontab
sudo crontab -e

# Добавить строку:
* * * * * cd /var/www/your-domain/private/backend && php artisan schedule:run >> /dev/null 2>&1
```

7. **Проверить работу парсера:**
```bash
# Проверить логи очереди
tail -f /var/www/your-domain/private/backend/storage/logs/laravel.log

# Проверить статус worker
sudo supervisorctl status yandex-parser-worker:*

# Проверить логи Node.js парсера
tail -f /var/www/your-domain/private/backend/storage/logs/parser.log
```

8. **Настроить Nginx:**
```nginx
server {
    listen 443 ssl http2;
    server_name your-domain.com;
    
    root /var/www/your-domain/private/backend/public;
    
    # SSL конфигурация
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    index index.php;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Тестирование

### Backend тесты
```bash
cd private/backend
php artisan test
```

### Frontend тесты
```bash
cd private/frontend
npm run test
```

## Лог файлы

- **Backend**: `private/backend/storage/logs/laravel.log`
- **Parser**: `private/backend/storage/logs/parser.log`
- **Queue**: `private/backend/storage/logs/queue.log`

## Ссылки проекта

- **GitHub**: [noster-krsk/laravel-vue-yandex-reviews-demo](https://github.com/noster-krsk/laravel-vue-yandex-reviews-demo)
- **Production**: https://test1.one-vpn.ru
- **Demo**: https://test1.one-vpn.ru

## Особенности и ограничения

### ✅ Реализовано
- Полная аутентификация пользователей и регистарция
- Парсинг отзывов с Яндекс Карт через Puppeteer (Node.js)
- Кэширование результатов
- Пагинация отзывов (50 на страницу)
- Фильтрация по рейтингу и рангу
- Статистика рейтинга компании
- Асинхронная очередь задач

### ⚠️ Ограничения Яндекс Карт
- Яндекс может блокировать частые запросы
- Требуется маскировка user-agent и headers
- Антибот защита требует задержек между запросами

## Решение проблем

### Проблема: "Yandex URL not configured"
- **Решение**: Перейти в Settings и добавить валидный URL Яндекс Карт в формате:
```
https://yandex.ru/maps/org/{название}/{org_id}/reviews/
```

### Проблема: Node.js парсер не найден
- **Решение**: Убедиться, что Node.js установлен и доступен в PATH:
```bash
which node
node -v
npm -v

# Если не установлен:
# Ubuntu/Debian
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs

# macOS
brew install node
```

### Проблема: Chromium не установлен
- **Решение**: Puppeteer должен автоматически загрузить Chromium при установке:
```bash
cd private/backend/scripts
npm install puppeteer

# Если это не помогло, установить Chromium отдельно:
# Ubuntu/Debian
sudo apt-get install chromium-browser

# macOS
brew install chromium
```

### Проблема: Парсинг зависает или медленный
- **Решение**: Увеличить timeout и добавить задержки:
```bash
# Перезагрузить очередь задач
php artisan queue:restart

# Увеличить timeout в Node.js скрипте
node scripts/parse-yandex.js "URL" --timeout=120000

# Проверить логи
tail -f private/backend/storage/logs/parser.log
```

### Проблема: CORS ошибки
- **Решение**: Проверить конфигурацию CORS в `config/cors.php` и убедиться, что frontend URL добавлен в allowed_origins

### Проблема: Database queue не работает
- **Решение**: Убедиться, что очередь использует database драйвер:
```bash
# Проверить .env
grep QUEUE_CONNECTION private/backend/.env

# Должно быть:
# QUEUE_CONNECTION=database

# Выполнить миграции
php artisan migrate

# Перезагрузить worker
php artisan queue:restart
```

### Проблема: Laravel команда не может запустить Node.js
- **Решение**: Проверить права доступа и PATH:
```bash
# Проверить, что команда доступна
which node

# Если не работает, указать полный путь в команде
# Отредактировать app/Services/YandexMapsParser.php и добавить полный путь к node
/usr/bin/node /var/www/path/to/scripts/parse-yandex.js
```

## Разработчик
Яковлев Александр Леонидович 

Создано как тестовое задание.

## Контакты и поддержка

Для вопросов и проблем используйте Issues в GitHub репозитории.

---

**Последнее обновление:** 13 февраля 2026 г.
