.PHONY: setup dev dev-backend dev-frontend check dev-check php-check frontend-check check-built-assets test build production-update production-update-with-node format

APP_ENV ?= prod
APP_DEBUG ?= 0

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

check: php-check check-built-assets

dev-check: frontend-check php-check

php-check:
	composer check

frontend-check:
	pnpm lint
	pnpm format:check
	pnpm typecheck
	pnpm test
	pnpm build
	$(MAKE) check-built-assets

check-built-assets:
	@if ! git diff --quiet -- public/build || [ -n "$$(git ls-files --others --exclude-standard public/build)" ]; then \
		git status --short -- public/build; \
		echo "public/build is out of date. Rebuild frontend assets and commit the generated files."; \
		exit 1; \
	fi

test:
	composer test
	pnpm test

build:
	pnpm build
	APP_ENV=$(APP_ENV) APP_DEBUG=$(APP_DEBUG) php bin/console cache:clear

production-update:
	composer install --no-dev --optimize-autoloader
	APP_ENV=$(APP_ENV) APP_DEBUG=$(APP_DEBUG) php bin/console cache:clear

production-update-with-node:
	composer install --no-dev --optimize-autoloader
	pnpm install --frozen-lockfile
	$(MAKE) build APP_ENV=$(APP_ENV) APP_DEBUG=$(APP_DEBUG)

format:
	composer format
	pnpm format
