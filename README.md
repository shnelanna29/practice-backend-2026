# Booking API (Laravel)

REST API для бронирования ресурсов (занятий) в студии. Проект подготовлен для преддипломной практики и включает JWT-аутентификацию, роли, расписание, бронирования, отзывы, тесты, OpenAPI и Docker.

## Предметная область и сущности

- **Resource (Ресурс)**: занятие в расписании (`schedule_items`)
- **ClassType (Характеристика ресурса)**: тип занятия (`class_types`)
- **Booking (Бронирование)**: запись пользователя на занятие (`bookings`)
- **Review (Отзыв)**: отзыв и оценка после завершенного занятия (`reviews`)
- **User**: роли `admin` и `client` (`users`)

## Стек

- PHP 8.3, Laravel 12
- JWT (Bearer Token)
- MySQL 8
- PHPUnit
- OpenAPI 3
- Docker / Docker Compose

## Где что лежит

- Контроллеры: `app/Http/Controllers`
- Модели: `app/Models`
- Маршруты: `routes/api.php`
- Миграции: `database/migrations`
- Сидеры: `database/seeders`
- ER-диаграмма: `docs/er-diagram.png` (+ `ER_1.xml` в корне практики)
- OpenAPI: `docs/openapi.yaml`

---

## Архитектура и поток данных

### Аутентификация (JWT)
JWT-токен выдается при регистрации/логине и используется в заголовке:
```
Authorization: Bearer <token>
```

**Флоу:**
1. `POST /api/register` → создаёт пользователя → возвращает `access_token`
2. `POST /api/login` → проверяет пароль → возвращает `access_token`
3. `GET /api/me` → отдаёт профиль (только с токеном)
4. `POST /api/logout` → токен попадает в blacklist (Cache)

### Источник данных
Данные хранятся в MySQL и создаются миграциями:
- `class_types`, `schedule_items`, `bookings`, `reviews`, `users`

Сиды:
- `ClassTypeSeeder` — типы занятий
- `UserSeeder` — admin и client
- `ScheduleSeeder` — расписание занятий

---

## Контроллеры и ответственность

### AuthController
- `register` — создание пользователя, выдача JWT
- `login` — проверка пароля, выдача JWT
- `logout` — инвалидирует текущий JWT (blacklist)
- `me` — профиль текущего пользователя

### ScheduleController
- `index` — список занятий с фильтрацией, сортировкой и пагинацией
- `show` — детали одного занятия
- `store/update/destroy` — CRUD расписания (только admin)
- `schedule` — расписание по конкретному занятию (день/неделя)
- `scheduleForClassType` — расписание по типу занятия (день/неделя)
- `available` — поиск свободных занятий по дате/времени

### BookingController
- `store` — создание бронирования с проверками:
  - занятие не в прошлом
  - есть свободные места
  - пользователь не записан на это занятие
  - нет пересечения по времени с другими бронями
- `destroy` — отмена бронирования (клиент — только своё, admin — любое)
- `myBookings` — список броней текущего пользователя
- `index` — список всех броней (только admin)

### ReviewController
- `index` — отзывы и средний рейтинг по занятию
- `store` — отзыв возможен только:
  - по своему бронированию
  - после завершения занятия
  - только один раз на бронирование

### ClassTypeController
- `index` — список типов занятий (публично)
- `store/update/destroy` — CRUD типов занятий (admin)

---

## Middleware

### JwtAuthenticate
Проверяет `Authorization: Bearer <token>`, декодирует JWT и подставляет пользователя в `request->user()`.

### RoleMiddleware
Доступ по роли (`admin`/`client`) для защищённых маршрутов.

---

## Фильтрация и пагинация

### ScheduleController::index
Фильтры:
- `date=YYYY-MM-DD` — дата занятия
- `class_type_id` — тип занятия
- `capacity_min` — минимальная вместимость
- `search` — поиск по названию типа

Сортировка:
- `sort=start_time|end_time|capacity|created_at|booked_count`
- `direction=asc|desc`

Пагинация:
- `per_page` (макс. 50)
- Возвращает стандартный Laravel paginator (`current_page`, `data`, `total` и т.д.)

### BookingController::myBookings
Фильтры:
- `status=confirmed|cancelled`
- `only_future=true` — только будущие брони
- сортировка по `created_at` или `status`

### ReviewController::index
Фильтр:
- `rating=1..5`

### ScheduleController::available (поиск свободных занятий)
Параметры:
- `date`, `start_time`, `end_time`
- `capacity_min`, `class_type_id`

Логика:
- берутся занятия в указанном интервале
- проверяется `capacity > booked_count`
- фильтруется по `class_type_id` и вместимости

---

## Docker

### Запуск
```
docker-compose up --build
```

### Миграции и сиды в контейнере
```
docker-compose exec app php artisan migrate --seed
```

### Конфиг
- `.env.docker` — переменные окружения для Docker
- MySQL доступен как `db` (внутри контейнерной сети)

---

## Запуск локально

1. Установка зависимостей
```
composer install
```

2. Настройка окружения
```
copy .env.example .env
```

3. Сгенерировать JWT секрет
```
php -r "echo bin2hex(random_bytes(32));"
```

4. Записать в `.env`
```
JWT_SECRET=ваш_секрет
JWT_TTL=60
```

5. Миграции и сиды
```
php artisan migrate --seed
```

6. Запуск сервера
```
php artisan serve
```

---

## Тесты (PHPUnit)

```
php artisan test
```

В Docker:
```
docker-compose exec app php artisan test
```

---

## Postman тесты

Готовой коллекции в репозитории нет (папка `postman/` содержит пустую коллекцию).  
Рекомендуемый способ: импорт OpenAPI.

### Вариант 1: импорт OpenAPI
1. Открыть Postman → Import → `docs/openapi.yaml`
2. Создать Environment:
   - `baseUrl` = `http://localhost:8080`
   - `token` = JWT из `/api/login`
3. В заголовках запросов:
   - `Authorization: Bearer {{token}}`

### Вариант 2: вручную
1. Выполнить `POST /api/login` и сохранить `access_token`
2. Прописать токен в Authorization для остальных запросов
3. Запустить коллекцию через Runner

---

## Swagger / OpenAPI

- Файл: `docs/openapi.yaml`
- UI (через Scribe): `http://localhost:8080/docs`

---

## Основные маршруты

**Auth**
- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

**Типы ресурсов**
- `GET /api/class-types`
- `POST /api/admin/class-types` (admin)
- `PUT /api/admin/class-types/{id}` (admin)
- `DELETE /api/admin/class-types/{id}` (admin)

**Расписание**
- `GET /api/schedule`
- `GET /api/schedule/{id}`
- `GET /api/schedule/{id}/schedule`
- `GET /api/class-types/{id}/schedule`
- `GET /api/schedule/available`
- `POST /api/admin/schedule` (admin)
- `PUT /api/admin/schedule/{id}` (admin)
- `DELETE /api/admin/schedule/{id}` (admin)

**Бронирования**
- `POST /api/bookings`
- `DELETE /api/bookings/{id}`
- `GET /api/my-bookings`
- `GET /api/admin/bookings` (admin)

**Отзывы**
- `GET /api/schedule/{scheduleItemId}/reviews`
- `POST /api/schedule/{scheduleItemId}/reviews`

---

## Git workflow для сдачи

Работа ведется в ветке `dev`. Для сдачи требуется PR/MR из `dev` в `main` в форке преподавателя.

Пример команд:
```
git remote add upstream <TEACHER_REPO_URL>
git fetch upstream
git checkout -b dev
git push origin dev
```
