#!/bin/bash

# Скрипт для подготовки проекта к деплою на хостинг
# Использование: ./deploy.sh

set -e

# Цвета для вывода
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Подготовка проекта к деплою на хостинг${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""

# Проверка наличия необходимых инструментов
echo -e "${YELLOW}Проверка инструментов...${NC}"
command -v php >/dev/null 2>&1 || { echo -e "${RED}PHP не установлен!${NC}" >&2; exit 1; }
command -v composer >/dev/null 2>&1 || { echo -e "${RED}Composer не установлен!${NC}" >&2; exit 1; }
command -v npm >/dev/null 2>&1 || { echo -e "${RED}npm не установлен!${NC}" >&2; exit 1; }
echo -e "${GREEN}✓ Все инструменты установлены${NC}"
echo ""

# 1. Установка зависимостей PHP
echo -e "${YELLOW}[1/6] Установка зависимостей PHP...${NC}"
composer install --optimize-autoloader --no-dev --no-interaction
echo -e "${GREEN}✓ Зависимости PHP установлены${NC}"
echo ""

# 2. Установка зависимостей Node.js
echo -e "${YELLOW}[2/6] Установка зависимостей Node.js...${NC}"
npm install
echo -e "${GREEN}✓ Зависимости Node.js установлены${NC}"
echo ""

# 3. Сборка фронтенда
echo -e "${YELLOW}[3/6] Сборка фронтенда...${NC}"
npm run build
echo -e "${GREEN}✓ Фронтенд собран${NC}"
echo ""

# 4. Очистка кеша
echo -e "${YELLOW}[4/6] Очистка кеша...${NC}"
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
echo -e "${GREEN}✓ Кеш очищен${NC}"
echo ""

# 5. Проверка .env
echo -e "${YELLOW}[5/6] Проверка конфигурации...${NC}"
if [ ! -f .env ]; then
    echo -e "${RED}⚠ Файл .env не найден!${NC}"
    echo -e "${YELLOW}Создайте файл .env на основе .env.example${NC}"
else
    if ! grep -q "APP_KEY=base64:" .env; then
        echo -e "${YELLOW}⚠ APP_KEY не установлен. Генерирую ключ...${NC}"
        php artisan key:generate --force
    fi
    echo -e "${GREEN}✓ Конфигурация проверена${NC}"
fi
echo ""

# 6. Создание списка файлов для загрузки
echo -e "${YELLOW}[6/6] Создание списка файлов...${NC}"
cat > .deploy-files.txt << EOF
# Файлы и папки для загрузки на хостинг
# Исключить из загрузки:
/node_modules
/.git
/.idea
/.vscode
/vendor (загрузить отдельно после composer install на сервере)
.env (создать на сервере)
.env.backup
.env.production
.phpunit.result.cache
/tests
/storage/logs/*
/storage/framework/cache/*
/storage/framework/sessions/*
/storage/framework/views/*
/docker
docker-compose.yml
Dockerfile
.dockerignore
README.md
SETUP.md
DEPLOY.md
deploy.sh
.gitignore
EOF
echo -e "${GREEN}✓ Список файлов создан (.deploy-files.txt)${NC}"
echo ""

# Итоговая информация
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}Готово! Проект подготовлен к деплою${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "${YELLOW}Следующие шаги:${NC}"
echo "1. Загрузите файлы на хостинг (кроме исключенных)"
echo "2. На хостинге выполните: composer install --no-dev"
echo "3. Создайте .env файл с настройками для продакшена"
echo "4. Настройте права доступа: chmod -R 755 storage bootstrap/cache"
echo "5. Выполните миграции: php artisan migrate --force"
echo "6. Создайте пользователя: php artisan db:seed"
echo ""
echo -e "${YELLOW}Подробная инструкция в файле DEPLOY.md${NC}"
