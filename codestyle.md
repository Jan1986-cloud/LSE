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

## Railway Deployment
Code quality is enforced through:
1. PSR-12 compliance standards for all PHP code.
2. PHPStan static analysis for type safety.
3. Database migrations validated during deployment.

All services deploy automatically to Railway.app with proper dependency management via Composer.
