.PHONY: setup dev dev-backend dev-frontend check php-check frontend-check test build format

setup:
	composer install
	pnpm install

dev:
	@echo "Run these in separate terminals:"
	@echo "  make dev-backend"
	@echo "  make dev-frontend"

dev-backend:
	php -S 127.0.0.1:8000 -t public

dev-frontend:
	pnpm dev

check: frontend-check php-check

php-check:
	composer check

frontend-check:
	pnpm lint
	pnpm format:check
	pnpm typecheck
	pnpm test
	pnpm build

test:
	composer test
	pnpm test

build:
	pnpm build
	APP_ENV=prod APP_DEBUG=0 php bin/console cache:clear

format:
	composer format
	pnpm format
