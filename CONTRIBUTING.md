# Contributing

Development targets `6.1`. Use PHP 8.4 or newer.

```bash
composer fix
composer test
composer test-bc
```

Run `test` and `test-bc` sequentially: both replace the installed dependencies.
The integration test boots a real Symfony kernel, compiles the container, and resolves
synthetic settings without a database or production credentials.
CI also validates on PHP 8.5. Codecov upload uses the repository's `CODECOV_TOKEN` secret.
Keep implemented capabilities and planned work distinct in `FEATURES.md`.

[Report issues](https://github.com/softspring/config-bundle/issues) and [send Pull Requests](https://github.com/softspring/config-bundle/pulls).
