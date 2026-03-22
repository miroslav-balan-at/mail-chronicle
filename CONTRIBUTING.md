# Contributing to Mail Chronicle

Thank you for considering contributing to Mail Chronicle!

## Getting Started

```bash
git clone https://github.com/miroslav-balan-at/mail-chronicle.git
cd mail-chronicle
composer install
npm ci && npm run build
```

## Development Workflow

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Ensure all checks pass (see below)
5. Submit a pull request against `main`

## Before Submitting

All of the following must pass:

```bash
# Tests
composer test

# Static analysis
composer phpstan

# Code standards
composer phpcs
```

## Code Standards

- PHP 8.1+ — use native types, enums, and named arguments where appropriate
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/) enforced via PHPCS
- PHPStan level 10 — no errors, no suppressions without justification
- PSR-4 autoloading — class names must match file names exactly
- Vertical Slice Architecture — new features go in `src/Features/<FeatureName>/`

## Reporting Bugs

Open an issue at [github.com/miroslav-balan-at/mail-chronicle/issues](https://github.com/miroslav-balan-at/mail-chronicle/issues).

Please include:
- WordPress version
- PHP version
- Steps to reproduce
- Expected vs actual behaviour

## License

By contributing you agree that your code will be licensed under [GPL v2 or later](LICENSE).
