# Docker-based Testing for Stream Chat PHP SDK

This directory contains Docker configuration for testing the Stream Chat PHP SDK in an isolated environment.

## Requirements

- Docker installed on your system
- Make (usually pre-installed on macOS and Linux)

## Quick Start

1. Build the Docker image:
   ```
   make docker-build
   ```

2. Run all tests:
   ```
   make docker-test
   ```
   This will automatically create a `.env` file from `.env.example` if it doesn't exist.

3. Run tests with test environment:
   ```
   make docker-test-with-test-env
   ```
   This will use `.env.test` for environment variables.

4. Run only unit tests:
   ```
   make docker-test-unit
   ```

5. Run only integration tests:
   ```
   make docker-test-integration
   ```

## Available Commands

All Docker-related commands are prefixed with `docker-` to distinguish them from local commands:

- `make docker-build` - Build the Docker image
- `make docker-test` - Run all tests
- `make docker-test-with-test-env` - Run all tests with test environment
- `make docker-test-unit` - Run unit tests
- `make docker-test-integration` - Run integration tests
- `make docker-lint` - Check code style with PHP CS Fixer
- `make docker-lint-fix` - Fix code style issues
- `make docker-analyze` - Run static analysis with Phan
- `make docker-shell` - Start a shell in the Docker container
- `make docker-clean` - Remove Docker container and image
- `make docker-install` - Install dependencies
- `make docker-update` - Update dependencies

## Environment Variables

The project includes a `.env.example` file with commented example values. When you run tests, the Makefile will:

1. Create a `.env` file from `.env.example` if it doesn't exist
2. Create a `.env.test` file from `.env.example` when running `make docker-test-with-test-env`

You can also use environment-specific files in the `env/` directory:

```
make docker-test-[environment_name]
```

### Environment File Hierarchy

- `.env` - Default environment file for general testing
- `.env.test` - Environment file specifically for test environments
- `env/*.env` - Environment files for specific test scenarios

## Accessing Host Machine from Docker

When running tests in Docker, the container's `localhost` refers to the container itself, not the host machine. To access services running on your host machine (like a local server on port 3030), use `host.docker.internal` instead of `localhost`.

For example, in your `.env` file:
```
STREAM_HOST=http://host.docker.internal:3030
```

The Makefile includes the `--add-host=host.docker.internal:host-gateway` flag to ensure this works across different Docker environments.

## Customizing PHP Version

You can specify a different PHP version when building the Docker image:
```
make docker-build PHP_VERSION=8.3
```

## Additional PHPUnit Options

You can pass additional options to PHPUnit:
```
make docker-test PHPUNIT_FLAGS="--verbose --filter=testSpecificMethod"
```

Or use the filter directly:
```
make docker-test PHPUNIT_FILTER="testSpecificMethod"
```
