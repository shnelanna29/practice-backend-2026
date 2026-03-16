# Dance Studio Booking API (Laravel + Sanctum)

API для бронирования занятий в студии танцев с авторизацией, ролевой моделью, бизнес-правилами бронирования и управлением расписанием.

## Быстрый запуск

1. **Запуск проекта:**
```bash
docker-compose up --build
```

2. **Открыть Swagger UI:** `http://localhost:8080/docs`

3. **Получить токен администратора:**
```bash
curl -X POST http://localhost:8080/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@studio.com","password":"password"}'
```

4. **Прогнать тесты:**
```bash
# Локально
php artisan test

# В Docker
docker-compose exec app php artisan test
```

## Цель проекта

Создать защищённое REST API для управления расписанием занятий и бронирования мест в студии танцев с чёткими правилами доступа и валидацией бизнес-логики.

## Предметная область

Система бронирования занятий в студии:
- Пользователь регистрируется, авторизуется и получает токен доступа
- Пользователь просматривает расписание занятий и бронирует места
- Администратор управляет расписанием (CRUD занятий) и видит все бронирования
- Система предотвращает двойное бронирование, пересечения по времени и превышение вместимости
- Поддерживается отмена бронирования с проверкой прав доступа

## Стек

- PHP 8.3
- Laravel 12
- Laravel Sanctum (аутентификация через токены)
- MySQL 8 (в Docker)
- PHPUnit (автотесты)
- OpenAPI 3 + Swagger UI (Scribe)
- Docker / Docker Compose

## Что сделано по чекпоинтам

### Чекпоинт 1. Проектирование и старт
- Выбрана предметная область: `Dance Studio Booking API`
- Спроектирована модель данных: `users`, `class_types`, `schedule_items`, `bookings`
- Сформирован API-контракт (`routes/api.php`)
- Созданы и запускаются миграции
- Подготовлена базовая структура проекта

### Чекпоинт 2. Авторизация и базовый CRUD
- Реализованы регистрация/логин/логаут `/api/me` на Laravel Sanctum
- Добавлена ролевая модель: `admin` / `client`
- CRUD расписания (`/api/admin/schedule`) доступен только администратору
- Добавлена валидация входных данных (Laravel Validator)
- Подготовлены Factory и Seeder с тестовыми данными

### Чекпоинт 3. Основная бизнес-логика
- Создание бронирования с проверками:
  - Занятие ещё не началось
  - Есть свободные места (`booked_count < capacity`)
  - Пользователь ещё не записан на это занятие
  - Нет пересечений по времени с другими подтверждёнными бронированиями
- Отмена бронирования:
  - Клиент может отменить только своё
  - Администратор может отменить любое
  - Нельзя отменить уже отменённое или прошедшее занятие
- Логирование критичных бизнес-событий (Laravel Log)
- Транзакционная запись для целостности данных

### Чекпоинт 4. Продвинутый функционал
- Фильтрация расписания по дате: `GET /api/schedule?date=2026-03-20`
- Уникальное ограничение БД: `UNIQUE(user_id, schedule_item_id)` для защиты от гонок
- Единые правила валидации и обработки ошибок
- Корректные HTTP-коды ответов (200, 201, 403, 422, 500)

### Чекпоинт 5. Тесты, Swagger, Docker
- Добавлены автотесты (6 тестов, критичные сценарии покрыты)
- Добавлена OpenAPI-спецификация (`docs/openapi.yaml`) и Swagger UI
- Добавлены `Dockerfile` и `docker-compose.yml`
- Проект запускается одной командой: `docker-compose up`
- Секреты вынесены в `.env`, `.env.example` предоставлен

## 🧪 Тестирование

### Запуск тестов

**Локально:**
```bash
# Все тесты
php artisan test

# Только тесты бронирования
php artisan test --filter BookingApiTest

# С отчётом о покрытии (требуется xdebug)
php artisan test --coverage
```

**В Docker:**
```bash
docker-compose exec app php artisan test
docker-compose exec app php artisan test --filter BookingApiTest
```

### Покрытые сценарии

| Тест | Метод | Что проверяет | Статус |
|------|-------|---------------|--------|
| `client_cannot_create_schedule_item` | `POST /api/admin/schedule` | Клиент не может создавать занятия | ✅ |
| `admin_can_create_schedule_item` | `POST /api/admin/schedule` | Админ может создавать занятия | ✅ |
| `client_can_book_a_class` | `POST /api/bookings` | Успешное бронирование | ✅ |
| `client_cannot_book_if_no_seats_left` | `POST /api/bookings` | Защита от переполнения | ✅ |
| `client_cannot_book_if_already_booked` | `POST /api/bookings` | Защита от дублирования | ✅ |
| `client_cannot_cancel_others_booking` | `DELETE /api/bookings/{id}` | Защита от отмены чужой брони | ✅ |

### Пример вывода успешного запуска

```
   PASS  Tests\Feature\BookingApiTest
  ✓ client cannot create schedule item
  ✓ admin can create schedule item
  ✓ client can book a class
  ✓ client cannot book if no seats left
  ✓ client cannot book if already booked
  ✓ client cannot cancel others booking

  Tests:  6 passed (9 assertions)
```

## ER-диаграмма

```mermaid
erDiagram
    USERS ||--o{ BOOKINGS : makes
    CLASS_TYPES ||--o{ SCHEDULE_ITEMS : defines
    SCHEDULE_ITEMS ||--o{ BOOKINGS : has_bookings

    USERS {
        bigint id PK
        string name
        string email UK
        string password
        enum role "admin|client"
        datetime created_at
        datetime updated_at
    }

    CLASS_TYPES {
        bigint id PK
        string name
        text description
        int default_capacity
        datetime created_at
        datetime updated_at
    }

    SCHEDULE_ITEMS {
        bigint id PK
        bigint class_type_id FK
        datetime start_time
        datetime end_time
        int capacity
        int booked_count
        datetime created_at
        datetime updated_at
    }

    BOOKINGS {
        bigint id PK
        bigint user_id FK
        bigint schedule_item_id FK
        enum status "confirmed|cancelled"
        datetime created_at
        datetime updated_at
        UNIQUE(user_id, schedule_item_id)
    }
```

## Роли и доступ

| Действие | Гость | Клиент | Администратор |
|---|---|---|---|
| Регистрация/логин | ✅ | ✅ | ✅ |
| Просмотр профиля `/api/me` | ❌ | ✅ | ✅ |
| Просмотр расписания `/api/schedule` | ✅ | ✅ | ✅ |
| CRUD занятий `/api/admin/schedule` | ❌ | ❌ | ✅ |
| Создание бронирования | ❌ | ✅ | ✅ |
| Просмотр бронирований | ❌ | Только свои (`/api/my-bookings`) | Все (`/api/admin/bookings`) |
| Отмена бронирования | ❌ | Только своё | Любое |

## Основные эндпоинты API

### Аутентификация
| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| POST | `/api/register` | Публичный | Регистрация пользователя |
| POST | `/api/login` | Публичный | Логин и получение токена |
| POST | `/api/logout` | Авторизованный | Выход (инвалидация токена) |
| GET | `/api/me` | Авторизованный | Профиль текущего пользователя |

### Расписание занятий
| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/api/schedule` | Публичный | Список занятий (фильтр `?date=YYYY-MM-DD`) |
| GET | `/api/schedule/{id}` | Публичный | Детали занятия |
| POST | `/api/admin/schedule` | Только admin | Создание занятия |
| PUT | `/api/admin/schedule/{id}` | Только admin | Обновление занятия |
| DELETE | `/api/admin/schedule/{id}` | Только admin | Удаление занятия |

### Бронирования
| Метод | URL | Доступ | Назначение |
|---|---|---|---|
| GET | `/api/my-bookings` | Авторизованный | Мои бронирования |
| GET | `/api/admin/bookings` | Только admin | Все бронирования |
| POST | `/api/bookings` | Авторизованный | Создание бронирования |
| DELETE | `/api/bookings/{id}` | Авторизованный | Отмена бронирования |

Полный контракт со схемами запросов/ответов: `docs/openapi.yaml`

## Параметры запросов

### Списки (фильтрация и сортировка)
| Эндпоинт | Фильтрация | Сортировка по умолчанию |
|---|---|---|
| `GET /api/schedule` | `date` (YYYY-MM-DD) | `start_time ASC` |
| `GET /api/my-bookings` | — | `created_at DESC` |
| `GET /api/admin/bookings` | — | `created_at DESC` |

### Создание бронирования
```json
POST /api/bookings
{
  "schedule_item_id": 1
}
```

### Создание занятия (админ)
```json
POST /api/admin/schedule
{
  "class_type_id": 1,
  "start_time": "2026-03-20T15:00:00",
  "end_time": "2026-03-20T16:00:00",
  "capacity": 10
}
```

## Коды ответов

| Код | Значение | Когда возвращается |
|---|---|---|
| 200 | OK | Успешный запрос |
| 201 | Created | Успешное создание ресурса |
| 401 | Unauthorized | Токен отсутствует или невалиден |
| 403 | Forbidden | Недостаточно прав (роль/владение) |
| 422 | Unprocessable Entity | Ошибка валидации или бизнес-правила |
| 404 | Not Found | Ресурс не найден |
| 500 | Internal Server Error | Ошибка сервера |

## Бизнес-ошибки (422)

| Сообщение | Условие |
|---|---|
| `Нельзя записаться на занятие, которое уже прошло или идет` | `schedule_item.start_time <= now()` |
| `Мест нет` | `booked_count >= capacity` |
| `Вы уже записаны на это занятие` | Уже есть подтверждённое бронирование |
| `У вас есть запись на другое занятие в это время` | Пересечение интервалов времени |
| `Бронь уже отменена` | Попытка повторной отмены |
| `Нельзя отменить бронь после начала занятия` | `schedule_item.start_time <= now()` |

## Тестовые пользователи (Seeder)

- **Админ**: `admin@studio.com` / `password`
- **Клиент**: `client@studio.com` / `password`

> Пароли хешируются через `bcrypt`. Для локальной разработки можно использовать указанные значения.

## Как проверить ключевые сценарии

### 1. Поднять проект
```bash
docker-compose up --build
```
Проверка:
- `http://localhost:8080/api/up` → `200 OK`
- `http://localhost:8080/docs` → Swagger UI

### 2. Протестировать бронирование (Postman)

1. **Логин клиента** → сохранить токен
2. **Получить расписание** → `GET /api/schedule`
3. **Забронировать занятие** → `POST /api/bookings` (должно вернуть 201)
4. **Повторное бронирование того же занятия** → должно вернуть 422 "Вы уже записаны"
5. **Просмотр своих бронирований** → `GET /api/my-bookings`
6. **Отмена бронирования** → `DELETE /api/bookings/{id}`
7. **Попытка отмены чужой брони** → должно вернуть 403

### 3. Проверить права администратора

1. **Логин админа** → сохранить токен
2. **Создать занятие** → `POST /api/admin/schedule` (201)
3. **Попытка создания занятия клиентом** → должно вернуть 403
4. **Просмотр всех бронирований** → `GET /api/admin/bookings`

### 4. Прогнать автотесты
```bash
# Локально
php artisan test --filter BookingApiTest

# В Docker
docker-compose exec app php artisan test --filter BookingApiTest
```

Покрытие тестами:
- ✅ Клиент не может создавать занятия
- ✅ Админ может создавать занятия
- ✅ Клиент может забронировать свободное место
- ✅ Нельзя забронировать, если мест нет
- ✅ Нельзя забронировать, если уже записан
- ✅ Нельзя отменить чужую бронь

## Swagger / OpenAPI

- **UI**: `http://localhost:8080/docs`
- **YAML-файл**: `docs/openapi.yaml`
- **Postman Collection**: `docs/collection.json`
- **Генерация**: `php artisan scribe:generate`

## Docker

### Используемые файлы
- `docker-compose.yml` — оркестрация `app` + `db`
- `Dockerfile` — сборка PHP-контейнера
- `.env` — конфигурация приложения (секреты вынесены из docker-compose)
- `.env.example` — шаблон переменных окружения (коммитится)

### Безопасность: вынос секретов

Чувствительные данные **не хранятся** в `docker-compose.yml`, а вынесены в переменные окружения:

```yaml
# docker-compose.yml — безопасная конфигурация
services:
  db:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
```

```env
# .env.example (коммитится в репозиторий)
DB_CONNECTION=mysql
DB_HOST=db
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
DB_ROOT_PASSWORD=
APP_KEY=
```

```env
# .env (игнорируется .gitignore, не коммитится)
DB_DATABASE=dance_studio_pr
DB_USERNAME=dance_user
DB_PASSWORD=your_secure_password
DB_ROOT_PASSWORD=your_root_password
APP_KEY=base64:your_app_key_here
```

### Логика старта контейнера `app`:
1. Ожидание готовности MySQL
2. Применение миграций: `php artisan migrate --force`
3. Запуск сервера: `php artisan serve --host=0.0.0.0 --port=8000`

### Полезные команды Docker

```bash
# Запуск проекта
docker-compose up -d --build

# Просмотр логов
docker-compose logs -f app

# Вход в контейнер
docker-compose exec app bash

# Запуск тестов внутри контейнера
docker-compose exec app php artisan test

# Остановка контейнеров
docker-compose down

# Остановка с удалением томов (данные БД удалятся!)
docker-compose down -v
```

## Локальный запуск без Docker

```bash
# 1. Установка зависимостей
composer install

# 2. Настройка окружения
cp .env.example .env
php artisan key:generate

# 3. Настройка БД в .env:
DB_CONNECTION=mysql
DB_DATABASE=dance_studio_pr
DB_USERNAME=root
DB_PASSWORD=

# 4. Миграции и сидеры
php artisan migrate --seed

# 5. Запуск сервера
php artisan serve
```

Приложение доступно по адресу: **http://localhost:8000**

## Артефакты для защиты

| Артефакт | Путь | Назначение |
|----------|------|------------|
| ✅ Автотесты | `tests/Feature/BookingApiTest.php` | Покрытие критичных сценариев |
| ✅ OpenAPI-спецификация | `docs/openapi.yaml` | Контракт API для клиентов |
| ✅ Swagger UI | `http://localhost:8080/docs` | Интерактивная документация |
| ✅ Контейнеризация | `Dockerfile`, `docker-compose.yml` | Воспроизводимое окружение |
| ✅ ER-диаграмма | `docs/er-diagram.png` | Визуализация модели данных |
| ✅ Postman Collection | `docs/collection.json` | Готовые запросы для тестирования |
| ✅ Логирование | `storage/logs/laravel.log` | Аудит бизнес-событий |

## Логирование

Критичные события логируются в `storage/logs/laravel.log`:
- Попытки бронирования (успешные и отказанные)
- Отмены бронирований
- Попытки нарушения прав доступа

Пример лога:
```
[2026-03-14 18:17:57] local.INFO: Попытка бронирования занятия {"user_id":2,"schedule_item_id":1}
[2026-03-14 18:17:57] local.WARNING: Отказ в бронировании: нет свободных мест {"user_id":2,"capacity":2,"booked":2}
[2026-03-14 18:17:58] local.INFO: Бронирование успешно выполнено {"booking_id":5,"user_id":2}
```

## Структура проекта

```
practice-backend-2026/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── AuthController.php      # Авторизация
│   │   │   ├── BookingController.php   # Бронирования
│   │   │   └── ScheduleController.php  # Расписание
│   │   └── Middleware/                 # Проверка ролей
│   ├── Models/
│   │   ├── Booking.php
│   │   ├── ClassType.php
│   │   ├── ScheduleItem.php
│   │   └── User.php
├── database/
│   ├── migrations/
│   └── seeders/
├── tests/
│   └── Feature/
│       └── BookingApiTest.php          # Автотесты
├── routes/
│   └── api.php                         # API маршруты
├── docs/
│   ├── openapi.yaml                    # OpenAPI спецификация
│   ├── collection.json                 # Postman коллекция
│   └── er-diagram.png                  # ER-диаграмма
├── docker-compose.yml
├── Dockerfile
├── .env.example
├── .gitignore
└── README.md
```

## Частые проблемы

### Ошибка "could not find driver"
Убедитесь, что в PHP включено расширение `pdo_mysql`:
```bash
php -m | grep pdo_mysql
```

### Ошибка "Unique constraint violation"
Очистите базу данных и запустите сидеры заново:
```bash
php artisan migrate:fresh --seed
```

### Ошибка "Class not found"
Очистите кэш автозагрузки:
```bash
composer dump-autoload
```

### Тесты не запускаются
Создайте файл `.env.testing` с настройками базы данных:
```env
# .env.testing
APP_ENV=testing
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=dance_studio_pr_test
DB_USERNAME=root
DB_PASSWORD=
```

### Docker: "read-only file system"
Перезапустите Docker Desktop и очистите кэш:
```bash
docker system prune -f
docker-compose up -d --build
```

---

> 📌 **Примечание**: Уникальное ограничение `UNIQUE(user_id, schedule_item_id)` в таблице `bookings` обеспечивает защиту от гонок на уровне БД. При попытке дублирования бронирования будет выброшено `UniqueConstraintViolationException`, которое обрабатывается в контроллере для возврата корректного ответа 422.

---

## Чеклист для проверяющего

- [ ] Проект запускается одной командой: `docker-compose up --build`
- [ ] Swagger UI доступен по адресу `http://localhost:8080/docs`
- [ ] Автотесты запускаются: `php artisan test --filter BookingApiTest`
- [ ] Все 6 тестов проходят успешно ✅
- [ ] Секреты вынесены в `.env`, `.env.example` предоставлен
- [ ] Документация описывает все эндпоинты и коды ошибок
- [ ] Демонстрация сценариев повторяема через Postman-коллекцию

---

**Автор:** shnelanna29  
**Репозиторий:** [https://github.com/shnelanna29/practice-backend-2026](https://github.com/shnelanna29/practice-backend-2026)  
**Лицензия:** Проект создан в учебных целях.