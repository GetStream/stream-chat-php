# ♻️ Contributing

We welcome code changes that improve this library or fix a problem, please make sure to follow all best practices and add tests if applicable before submitting a Pull Request on Github. We are very happy to merge your code in the official repository. Make sure to sign our [Contributor License Agreement (CLA)](https://docs.google.com/forms/d/e/1FAIpQLScFKsKkAJI7mhCr7K9rEIOpqIDThrWxuvxnwUq2XkHyG154vQ/viewform) first. See our license file for more details.

## Getting started

### Restore dependencies

Installing dependencies into `./vendor` folder:

```bash
$ composer install
```

### Run tests

The tests we have are full fledged integration tests, meaning they will actually reach out to a Stream app. Hence the tests require at least two environment variables: `STREAM_KEY` and `STREAM_SECRET`.

```bash
$ export STREAM_KEY="<your-key>"
$ export STREAM_SECRET="<your-secret>"
$ vendor/bin/phpunit
```

> 💡 Note: On Unix systems you could use [direnv](https://direnv.net/) to set up these variables.

## IDE specific setup

If you use VS Code, you can pull up a Dockerized development environment with [Remote-Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers) extension. The proper configuration is already included in [.devcontainer](./.devcontainer/) folder. Once you're inside the container, just run the `composer install` command and you're good to go.

When you use the Remote-Container extension with VS Code, the recommended PHP extensions will already be installed (as defined in the [.devcontainer.json](.devcontainer/devcontainer.json)).

Recommended settings for VS Code:
```json
{
    "editor.formatOnSave": true,
    "php-cs-fixer.onsave": true,
    "php-cs-fixer.config": ".php-cs-fixer.dist.php",
}
```

## Commit message convention

Since we're autogenerating our [CHANGELOG](./CHANGELOG.md), we need to follow a specific commit message convention.
You can read about conventional commits [here](https://www.conventionalcommits.org/). Here's how a usual commit message looks like for a new feature: `feat: allow provided config object to extend other configs`. A bugfix: `fix: prevent racing of requests`.
