# Yandex Maps Reviews Integration

Проект для интеграции с Яндекс Картами и отображения отзывов.

## Технологии

- Laravel 11
- Vue 3
- Inertia.js
- Tailwind CSS
- Docker & Docker Compose
- Puppeteer (для парсинга Яндекс Карт)

## Установка

1. Клонируйте репозиторий
2. Скопируйте `.env.example` в `.env`
3. Запустите Docker Compose:

```bash
docker-compose up -d
```

4. Установите зависимости:

```bash
docker-compose exec app composer install
docker-compose exec node npm install
```

5. Сгенерируйте ключ приложения:

```bash
docker-compose exec app php artisan key:generate
```

6. Запустите миграции:

```bash
docker-compose exec app php artisan migrate
```

7. Запустите сидер для создания тестового пользователя:

```bash
docker-compose exec app php artisan db:seed
```

8. Соберите фронтенд:

```bash
docker-compose exec node npm run build
```

## Доступ

- Приложение: http://localhost:8000
- Логин: admin@example.com
- Пароль: password

## Использование

1. Войдите в систему
2. Перейдите в "Настройка" и укажите ссылку на Яндекс Карты
3. Перейдите в "Отзывы" и нажмите "Синхронизировать отзывы"
4. Просматривайте отзывы и рейтинг компании

## Структура проекта

- `app/Http/Controllers` - Контроллеры
- `app/Models` - Модели
- `app/Services` - Сервисы (парсинг отзывов)
- `resources/js/Pages` - Vue компоненты страниц
- `resources/js/Layouts` - Vue компоненты макетов
- `database/migrations` - Миграции базы данных

## Деплой на хостинг

Для деплоя на обычный shared hosting см. подробные инструкции:

- **QUICK_DEPLOY.md** - Быстрая инструкция (5 минут)
- **DEPLOY.md** - Подробная инструкция со всеми деталями

Также доступен автоматический скрипт подготовки:
```bash
./deploy.sh
```
