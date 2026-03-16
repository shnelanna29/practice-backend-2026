# Booking API (Laravel)

REST API для бронирования ресурсов (занятий) в студии. Проект выполнен в формате преддипломной практики и покрывает основные требования: регистрация/логин, роли, расписание, бронирования, отзывы, OpenAPI и Docker.

## Предметная область

- **Ресурс**: занятие в расписании (`ScheduleItem`)
- **Характеристики ресурса**: тип занятия (`ClassType`)
- **Бронирование**: запись пользователя на занятие
- **Отзывы**: оценка и комментарий после завершенного занятия

## Стек

- PHP 8.3, Laravel 12
- JWT-аутентификация (Bearer)
- MySQL 8
- PHPUnit
- OpenAPI 3
- Docker / Docker Compose

## Что реализовано

- JWT-аутентификация: регистрация, логин, профиль, logout
- Роли: `admin` и `client`
- Расписание: список, фильтры, пагинация, расписание на день/неделю
- Поиск свободных ресурсов по дате и времени
- Бронирование: создание, отмена, ограничения, конфликты по времени
- Отзывы: только после завершенного бронирования, средний рейтинг
- OpenAPI файл и Swagger UI
- Автотесты (включая авторизацию и конфликты)

## Структура проекта

- `app/Http/Controllers` — контроллеры API
- `app/Models` — модели
- `routes/api.php` — маршруты API
- `database/migrations` — миграции
- `database/seeders` — сидеры
- `docs/er-diagram.png` — ER-диаграмма
- `docs/openapi.yaml` — OpenAPI контракт

## Установка и запуск (локально)

```bash
composer install
copy .env.example .env
```

Сгенерировать JWT секрет:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

Добавить значение в `.env`:

```
JWT_SECRET=ваш_секрет
JWT_TTL=60
```

Запуск миграций и сидов:

```bash
php artisan migrate --seed
```

Запуск сервера:

```bash
php artisan serve
```

## Запуск через Docker

```bash
docker-compose up --build
```

Выполнить миграции и сиды внутри контейнера:

```bash
docker-compose exec app php artisan migrate --seed
```

Swagger UI доступен по адресу:

```
http://localhost:8080/docs
```

## Тесты

```bash
php artisan test
```

В Docker:

```bash
docker-compose exec app php artisan test
```

## OpenAPI / Swagger

- Файл спецификации: `docs/openapi.yaml`
- Swagger UI: `http://localhost:8080/docs`

## Роли и доступ

- `client` — просмотр расписания, бронирование, отзывы
- `admin` — CRUD расписания и типов ресурсов, просмотр всех бронирований

## Основные маршруты

**Auth**
- `POST /api/register`
- `POST /api/login`
- `POST /api/logout`
- `GET /api/me`

**Типы ресурсов (характеристики)**
- `GET /api/class-types` (публичный список)
- `POST /api/admin/class-types` (admin)
- `PUT /api/admin/class-types/{id}` (admin)
- `DELETE /api/admin/class-types/{id}` (admin)

**Расписание**
- `GET /api/schedule`
- `GET /api/schedule/{id}`
- `GET /api/schedule/{id}/schedule` (день/неделя)
- `GET /api/class-types/{id}/schedule` (день/неделя)
- `POST /api/admin/schedule` (admin)
- `PUT /api/admin/schedule/{id}` (admin)
- `DELETE /api/admin/schedule/{id}` (admin)
- `GET /api/schedule/available`

**Бронирования**
- `POST /api/bookings`
- `DELETE /api/bookings/{id}`
- `GET /api/my-bookings`
- `GET /api/admin/bookings` (admin)

**Отзывы**
- `GET /api/schedule/{scheduleItemId}/reviews`
- `POST /api/schedule/{scheduleItemId}/reviews`

## Git workflow для сдачи

Работа ведется в ветке `dev`. Для оформления сдачи в форке:

```bash
git remote add upstream <TEACHER_REPO_URL>
git fetch upstream
git checkout -b dev
git push origin dev
```

Далее оформить PR/MR из `dev` в `main` в форке репозитория преподавателя.

