# Config Bundle Features

Functional contract for `softspring/config-bundle`.

## Purpose

Let projects and bundles declare application settings and credentials that authorized
administrators can change without a deployment. Keep configuration consumers independent
from storage and secret providers.

## Implemented capabilities

- Register `SfsConfigBundle` with the `sfs_config` configuration namespace.
- Accept named defaults from project or bundle configuration, including env placeholders.
- Preserve false, zero, null, strings, numbers, and arrays when read from the provider.
- Fail explicitly when a configuration key is not declared.
- Expose an injectable `ConfigProviderInterface` and replaceable `sfs_config.provider` service.
- Support scalar and array injection through `%env(sfs_config:group:key)%`.
- Map processor names such as `mailchimp:api_key` to the provider key `mailchimp.api_key`.
- Inject a key-bound `ConfigValueInterface` into autoconfigured constructors with `#[SfsConfig('key')]` or a standard Symfony inline service in YAML/PHP.
- Provide `ConfigValueInterface` and `ConfigValue` for consumers that request a value
  on each operation. This does not imply automatic refresh of downstream clients.
- Boot and resolve values in a compiled Symfony container without database access.
- Include Composer maintenance commands, regular and lowest dependency validation,
  coverage reporting, and a separate PHPMD Code Scanning workflow.

## Configuration definitions and administration

- Register typed settings with labels, help, groups, defaults, sensitivity and per-key read/update/reset permissions.
- Support string, multiline text, boolean, integer, float and JSON administration forms with validation and CSRF.
- Register configuration blocks by stable keys, with translated titles and descriptions, a translation domain and priority.
- Associate each setting with a registered block; default ungrouped settings to General and reject unknown groups.
- Show settings in their blocks, ordered by priority and stable key, hiding blocks without readable settings.
- Reuse admin route imports for fixed-group pages, retaining scoped links, breadcrumbs and save/reset redirects; reject keys outside the page group and preserve per-key permissions.
- Allow project overrides of group metadata without changing stored setting values or permissions.
- Persist non-sensitive overrides through Doctrine and prefer them over defaults, preserving explicit null, false, zero and empty values.
- Use CRUDL list, read, update and delete/reset actions with configurable global and per-key permissions.
- Include translated breadcrumbs for list, read, edit and reset screens through the shared admin layout block.
- Reset a stored override to its default; show the active value source and reject sensitive settings from the editing UI.
- Read the current override on each provider call. Defaults-only use does not require database access.

## Planned capabilities

- Optional secret-manager fallback adapters and richer per-definition validation.
- Authenticated encryption of sensitive overrides, external key management and key rotation.
- Secret-safe replacement forms and explicit handling of unreadable ciphertext.
- Actor audits without storing secret values.

## Runtime limits

The env processor resolves values when consuming services are initialized. It cannot
update a scalar already held by a service. Long-running consumers need explicit refresh,
service recreation, or the provider/value-object API. Definitions used to build the
container cannot be made dynamic through this processor.
