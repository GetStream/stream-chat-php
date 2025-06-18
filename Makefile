SHELL := bash
.ONESHELL:
.SHELLFLAGS := -eu -o pipefail -c
.DELETE_ON_ERROR:
MAKEFLAGS += --warn-undefined-variables
MAKEFLAGS += --no-builtin-rules

# Docker configuration
DOCKER_IMAGE := stream-chat-php
DOCKER_TAG := latest
DOCKER_CONTAINER := stream-chat-php-test
DOCKER_RUN := docker run --rm -v $(PWD):/app -w /app --add-host=host.docker.internal:host-gateway

# Environment variables
ENV_FILE ?= .env

# PHP configuration
PHP_VERSION ?= 8.2
COMPOSER_FLAGS ?= --prefer-dist --no-interaction

# Test configuration
PHPUNIT := vendor/bin/phpunit
PHPUNIT_FLAGS ?= --colors=always
PHPUNIT_FILTER ?=

# Find all .env files in the env directory (if it exists)
ENV_DIR := env
ENV_FILES := $(wildcard $(ENV_DIR)/*.env)
TEST_TARGETS := $(addprefix docker-test-, $(subst .env,,$(subst $(ENV_DIR)/,,$(ENV_FILES))))

# Default target
.PHONY: help
help:
	@echo "Stream Chat PHP SDK Makefile"
	@echo ""
	@echo "Usage:"
	@echo "  make docker-build           Build the Docker image"
	@echo "  make docker-test            Run all tests in Docker"
	@echo "  make docker-test-unit       Run unit tests in Docker"
	@echo "  make docker-test-integration Run integration tests in Docker"
	@echo "  make docker-lint            Run PHP CS Fixer in Docker"
	@echo "  make docker-lint-fix        Fix code style issues in Docker"
	@echo "  make docker-analyze         Run static analysis (Phan) in Docker"
	@echo "  make docker-shell           Start a shell in the Docker container"
	@echo "  make docker-clean           Remove Docker container and image"
	@echo "  make docker-install         Install dependencies in Docker"
	@echo "  make docker-update          Update dependencies in Docker"
	@echo ""
	@echo "Environment:"
	@echo "  ENV_FILE                  Path to .env file (default: .env)"
	@echo "  PHP_VERSION               PHP version to use (default: 8.2)"
	@echo "  PHPUNIT_FLAGS             Additional flags for PHPUnit"
	@echo "  PHPUNIT_FILTER            Filter for PHPUnit tests"
	@echo ""

# Docker image build
.PHONY: docker-build
docker-build:
	@echo "Building Docker image $(DOCKER_IMAGE):$(DOCKER_TAG)..."
	docker build -t $(DOCKER_IMAGE):$(DOCKER_TAG) \
		--build-arg PHP_VERSION=$(PHP_VERSION) \
		-f docker/Dockerfile .

# Ensure the Docker image is built
.PHONY: ensure-image
ensure-image:
	@if ! docker image inspect $(DOCKER_IMAGE):$(DOCKER_TAG) > /dev/null 2>&1; then \
		$(MAKE) docker-build; \
	fi

# Run all tests in Docker
.PHONY: docker-test
docker-test: ensure-image .env
	@echo "Running all tests in Docker..."
	$(DOCKER_RUN) --env-file $(ENV_FILE) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		$(PHPUNIT) $(PHPUNIT_FLAGS) $(PHPUNIT_FILTER)

# Run unit tests in Docker
.PHONY: docker-test-unit
docker-test-unit: ensure-image .env
	@echo "Running unit tests in Docker..."
	$(DOCKER_RUN) --env-file $(ENV_FILE) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		$(PHPUNIT) $(PHPUNIT_FLAGS) --testsuite "Unit Test Suite" $(PHPUNIT_FILTER)

# Run integration tests in Docker
.PHONY: docker-test-integration
docker-test-integration: ensure-image .env
	@echo "Running integration tests in Docker..."
	$(DOCKER_RUN) --env-file $(ENV_FILE) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		$(PHPUNIT) $(PHPUNIT_FLAGS) --testsuite "Integration Test Suite" $(PHPUNIT_FILTER)

# Run PHP CS Fixer in Docker
.PHONY: docker-lint
docker-lint: ensure-image
	@echo "Running PHP CS Fixer in Docker..."
	$(DOCKER_RUN) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		vendor/bin/php-cs-fixer fix --dry-run --diff

# Fix code style issues
.PHONY: docker-lint-fix
docker-lint-fix: ensure-image
	@echo "Fixing code style issues..."
	$(DOCKER_RUN) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		vendor/bin/php-cs-fixer fix

# Run static analysis
.PHONY: docker-analyze
docker-analyze: ensure-image
	@echo "Running static analysis..."
	$(DOCKER_RUN) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		vendor/bin/phan --color

# Start a shell in the Docker container
.PHONY: docker-shell
docker-shell: ensure-image
	@echo "Starting shell in Docker container..."
	$(DOCKER_RUN) -it $(DOCKER_IMAGE):$(DOCKER_TAG) bash

# Clean up Docker resources
.PHONY: docker-clean
docker-clean:
	@echo "Cleaning up Docker resources..."
	-docker rm -f $(DOCKER_CONTAINER) 2>/dev/null || true
	-docker rmi -f $(DOCKER_IMAGE):$(DOCKER_TAG) 2>/dev/null || true

# Create .env file if it doesn't exist by copying from .env.example
.env: .env.example
	@echo "Creating .env file from .env.example..."
	@cp .env.example .env
	@echo "Created .env file. Please edit it with your configuration."

# Create .env.test file for testing
.PHONY: .env.test
.env.test: .env.example
	@echo "Creating .env.test file from .env.example..."
	@cp .env.example .env.test
	@echo "Created .env.test file. Please edit it with your test configuration."

# Dynamic targets for environment-specific tests
.PHONY: $(TEST_TARGETS)
$(TEST_TARGETS): ensure-image .env
	$(eval TARGET := $(subst docker-test-,,$@))
	$(eval ENV_FILE := $(ENV_DIR)/$(TARGET).env)
	@echo "Running tests with environment $(TARGET)..."
	$(DOCKER_RUN) --env-file $(ENV_FILE) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		$(PHPUNIT) $(PHPUNIT_FLAGS) $(PHPUNIT_FILTER)

# Install dependencies
.PHONY: docker-install
docker-install: ensure-image
	@echo "Installing dependencies..."
	$(DOCKER_RUN) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		composer install $(COMPOSER_FLAGS)

# Update dependencies
.PHONY: docker-update
docker-update: ensure-image
	@echo "Updating dependencies..."
	$(DOCKER_RUN) $(DOCKER_IMAGE):$(DOCKER_TAG) \
		composer update $(COMPOSER_FLAGS)

# Run tests with test environment
.PHONY: docker-test-with-test-env
docker-test-with-test-env: ensure-image .env.test
	@echo "Running all tests in Docker with test environment..."
	$(DOCKER_RUN) --env-file .env.test $(DOCKER_IMAGE):$(DOCKER_TAG) \
		$(PHPUNIT) $(PHPUNIT_FLAGS) $(PHPUNIT_FILTER) 