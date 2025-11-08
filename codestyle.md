# Project Aurora Code Style

## PHP
- Follow [PSR-12](https://www.php-fig.org/psr/psr-12/) for formatting.
- Static analysis with [PHPStan](https://phpstan.org/) level 1 (configured via `phpstan.neon`).
- Run the automated checks locally with:
  ```bash
  composer install
  vendor/bin/phpcs --standard=PSR12 services scripts
  vendor/bin/phpstan analyse
  ```

## JavaScript / TypeScript
- Use [ESLint](https://eslint.org/) and [Prettier](https://prettier.io/) defaults when front-end packages are introduced.

## Continuous Integration
The GitHub Actions workflow `.github/workflows/ci.yml` enforces:
1. `composer install` for each service and the shared toolchain.
2. PSR-12 compliance via PHP_CodeSniffer.
3. PHPStan static analysis.
4. Execution of `scripts/migration_logic.php` against an ephemeral PostgreSQL instance.

Commits that fail any gate must be corrected before merging to `main`.
