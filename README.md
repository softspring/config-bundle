# Config Bundle

[![Latest Stable](https://img.shields.io/packagist/v/softspring/config-bundle?label=stable&style=flat-square)](https://github.com/softspring/config-bundle/releases)
[![Latest Unstable](https://img.shields.io/packagist/v/softspring/config-bundle?label=unstable&style=flat-square&include_prereleases)](https://github.com/softspring/config-bundle/releases)
[![License](https://img.shields.io/packagist/l/softspring/config-bundle?style=flat-square)](https://github.com/softspring/config-bundle/blob/6.1/LICENSE)
[![PHP Version](https://img.shields.io/packagist/dependency-v/softspring/config-bundle/php?style=flat-square)](https://github.com/softspring/config-bundle/blob/6.1/composer.json)
[![Downloads](https://img.shields.io/packagist/dt/softspring/config-bundle?style=flat-square)](https://packagist.org/packages/softspring/config-bundle)
[![CI](https://img.shields.io/github/actions/workflow/status/softspring/config-bundle/ci.yml?branch=6.1&style=flat-square&label=CI)](https://github.com/softspring/config-bundle/actions/workflows/ci.yml)
[![Coverage](https://img.shields.io/codecov/c/github/softspring/config-bundle?branch=6.1&style=flat-square)](https://app.codecov.io/gh/softspring/config-bundle/tree/6.1)

Config Bundle provides a shared configuration entry point for Symfony projects and bundles.

## Status

Experimental skeleton on the `6.1` development line. Requires PHP 8.4 or newer and
supports Symfony 6.4, 7.4, and 8.x.

The current implementation resolves configured defaults through an injectable provider
and a Symfony env processor. It also provides a value object that calls the provider
on each read. It does not yet include database storage, encryption, an administration
screen, or a Google Secret Manager adapter. See [FEATURES.md](FEATURES.md) for the contract.
Do not use this skeleton to store production credentials.

## Installation

Require `softspring/config-bundle:6.1.*@dev` from its Composer VCS repository until the
package is registered on Packagist. Register
`Softspring\Bundle\ConfigBundle\SfsConfigBundle` in `config/bundles.php`.
There is no Flex recipe yet; no application configuration is required to boot the bundle.

Configure defaults under `sfs_config.defaults`, using dotted keys. Read a key through
`ConfigProviderInterface::get('mailchimp.api_key')`, or inject its value using
`%env(sfs_config:mailchimp:api_key)%`. Colons in the processor key map to dots in the
provider key. Defaults may reference Symfony `%env(...)%` placeholders.

Injected scalar values are snapshots for the lifetime of their consuming service.
`ConfigValue` calls its provider on every `get()`, but freshness depends on that provider;
the initial defaults provider is immutable. Applications can replace or decorate
`sfs_config.provider` to implement another source.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

[Report issues](https://github.com/softspring/config-bundle/issues) and [send Pull Requests](https://github.com/softspring/config-bundle/pulls)

## Security

See [SECURITY.md](SECURITY.md).

## License

This package is free and released under the [AGPL-3.0 license](LICENSE).
