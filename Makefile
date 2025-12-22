# ZenaManage Development Makefile
# Provides convenient commands for development workflow

.PHONY: help install test test-fast test-e2e test-coverage lint fix-format security-check clean setup-dev setup-prod

# Default target
help: ## Show this help message
	@echo "ZenaManage Development Commands"
	@echo "================================"
	@awk 'BEGIN {FS = ":.*?## "} /^[a-zA-Z_-]+:.*?## / {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}' $(MAKEFILE_LIST)

# Installation and Setup
install: ## Install all dependencies
	composer install
	npm install
	cp env.example .env
	php artisan key:generate

setup-dev: ## Setup development environment
	@echo "Setting up development environment..."
	composer install --dev
	npm install
	cp env.example .env
	php artisan key:generate
	php artisan migrate:fresh --seed
	npm run dev
	@echo "Development environment ready!"

setup-prod: ## Setup production environment
	@echo "Setting up production environment..."
	composer install --no-dev --optimize-autoloader
	npm ci
	npm run build
	cp env.example .env
	php artisan key:generate
	php artisan migrate --force
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache
	@echo "Production environment ready!"

# Testing Commands
test: ## Run all tests (SQLite for speed)
	@echo "Running all tests with SQLite..."
	cp .env.testing .env
	php artisan test

test-fast: ## Run fast tests with SQLite in-memory
	@echo "Running fast tests with SQLite..."
	cp .env.testing .env
	php artisan test --testsuite=Unit,Feature

test-e2e: ## Run E2E tests with MySQL (requires local MySQL)
	@echo "Running E2E tests with MySQL..."
	@if ! mysqladmin ping -h 127.0.0.1 -uroot --silent 2>/dev/null; then \
		echo "Error: MySQL is not running. Please start MySQL first."; \
		exit 1; \
	fi
	cp env.example .env
	sed -i 's/DB_DATABASE=zenamanage/DB_DATABASE=zenamanage_test/' .env
	php artisan migrate:fresh --seed
	php artisan test --testsuite=E2E

test-coverage: ## Run tests with coverage report
	@echo "Running tests with coverage..."
	cp .env.testing .env
	php artisan test --coverage-html=storage/app/coverage
	@echo "Coverage report generated in storage/app/coverage/"

test-smoke: ## Run smoke tests only
	@echo "Running smoke tests..."
	cp .env.testing .env
	php artisan test tests/Feature/SmokeTest.php

test-performance: ## Run performance tests
	@echo "Running performance tests..."
	cp env.example .env
	php artisan migrate:fresh --seed
	php artisan test --testsuite="Final Testing"

# Code Quality
lint: ## Run linting tools
	@echo "Running code quality checks..."
	./vendor/bin/php-cs-fixer fix --dry-run --diff
	./vendor/bin/phpstan analyse --memory-limit=2G
	./vendor/bin/security-checker security:check

fix-format: ## Fix code formatting
	@echo "Fixing code formatting..."
	./vendor/bin/php-cs-fixer fix

security-check: ## Run security checks
	@echo "Running security checks..."
	./vendor/bin/security-checker security:check
	composer audit

# Database Commands
db-fresh: ## Fresh database migration with seeding
	php artisan migrate:fresh --seed

db-reset: ## Reset database (drop and recreate)
	php artisan migrate:fresh --seed

db-test: ## Setup test database
	@echo "Setting up test database..."
	cp .env.testing .env
	php artisan migrate:fresh --seed

# Frontend Commands
frontend-dev: ## Start frontend development server
	npm run dev

frontend-build: ## Build frontend for production
	npm run build

frontend-watch: ## Watch frontend files for changes
	npm run watch

# Cache Commands
cache-clear: ## Clear all caches
	php artisan cache:clear
	php artisan config:clear
	php artisan route:clear
	php artisan view:clear

cache-warm: ## Warm up caches
	php artisan config:cache
	php artisan route:cache
	php artisan view:cache

# Development Utilities
logs: ## Show application logs
	tail -f storage/logs/laravel.log

queue-work: ## Start queue worker
	php artisan queue:work

serve: ## Start development server
	php artisan serve

tinker: ## Start Laravel Tinker
	php artisan tinker

# Cleanup Commands
clean: ## Clean up temporary files
	rm -rf storage/app/coverage
	rm -rf storage/logs/*.log
	rm -rf bootstrap/cache/*.php
	rm -rf storage/framework/cache/data/*
	rm -rf storage/framework/sessions/*
	rm -rf storage/framework/views/*

clean-vendor: ## Clean vendor directories
	rm -rf vendor
	rm -rf node_modules
	composer install
	npm install

# Docker Commands (if using Docker)
docker-up: ## Start Docker containers
	docker-compose up -d

docker-down: ## Stop Docker containers
	docker-compose down

docker-build: ## Build Docker images
	docker-compose build

docker-logs: ## Show Docker logs
	docker-compose logs -f

# Deployment Commands
deploy-staging: ## Deploy to staging
	@echo "Deploying to staging..."
	# Add staging deployment commands here

deploy-prod: ## Deploy to production
	@echo "Deploying to production..."
	# Add production deployment commands here

# Monitoring Commands
monitor: ## Start monitoring dashboard
	@echo "Starting monitoring..."
	# Add monitoring commands here

health-check: ## Check application health
	@echo "Checking application health..."
	php artisan health:check

# Backup Commands
backup-db: ## Backup database
	@echo "Creating database backup..."
	php artisan backup:run

restore-db: ## Restore database from backup
	@echo "Restoring database..."
	# Add restore commands here

# Documentation
docs: ## Generate documentation
	@echo "Generating documentation..."
	# Add documentation generation commands here

# Quick Development Workflow
dev: setup-dev ## Quick development setup
	@echo "Development environment ready!"
	@echo "Run 'make serve' to start the development server"
	@echo "Run 'make test-fast' for quick tests"
	@echo "Run 'make test-e2e' for full E2E tests"

# CI/CD Simulation
ci-local: ## Run CI pipeline locally
	@echo "Running CI pipeline locally..."
	make lint
	make test-fast
	make test-e2e
	make security-check
	@echo "CI pipeline completed successfully!"
