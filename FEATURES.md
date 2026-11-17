# Config Bundle Features

Functional contract for `softspring/config-bundle`.

## Purpose

Let projects and bundles declare application settings and credentials that authorized
administrators can change without a deployment. Keep configuration consumers independent
from storage and secret providers.

## Implemented skeleton

- Register `SfsConfigBundle` with the `sfs_config` configuration namespace.
- Accept named defaults from project or bundle configuration, including env placeholders.
- Preserve false, zero, null, strings, numbers, and arrays when read from the provider.
- Fail explicitly when a configuration key is not declared.
- Expose an injectable `ConfigProviderInterface` and replaceable `sfs_config.provider` service.
- Support scalar and array injection through `%env(sfs_config:group:key)%`.
- Map processor names such as `mailchimp:api_key` to the provider key `mailchimp.api_key`.
- Provide `ConfigValueInterface` and `ConfigValue` for consumers that request a value
  on each operation. This does not imply automatic refresh of downstream clients.
- Boot and resolve values in a compiled Symfony container without database access.
- Include Composer maintenance commands, regular and lowest dependency validation,
  coverage reporting, and a separate PHPMD Code Scanning workflow.

## Planned capabilities

The following capabilities define the intended package scope. They are not available yet.

### Definitions and integration

- Let applications and installed bundles register typed definitions with labels, groups,
  validation constraints, defaults, sensitivity, and administration permissions.
- Allow project overrides of bundle definitions through a documented precedence rule.
- Support strings, multiline text, booleans, integers, floats, and structured values.

### Persistence and resolution

- Persist administrative overrides through Doctrine, preserving the distinction between
  an absent override and explicit null, false, zero, or empty values.
- Resolve a database override first, then an explicitly configured fallback source.
- Reset an override to restore its fallback without copying external secrets into storage.
- Read fallbacks from literals, environment variables, or an optional Google Secret Manager adapter.
- Define refresh and invalidation behavior for HTTP requests, workers, and multiple instances.
- Keep database connection credentials and encryption bootstrap independent from this store.

### Credentials

- Encrypt sensitive overrides with authenticated encryption and an external dedicated key.
- Support key identification and rotation; reject tampered or unreadable ciphertext explicitly.
- Keep secrets out of rendered forms, logs, audit values, profiler output, and compiled containers.
- Replacing a credential must not require revealing the stored value to the browser.

### Administration

- Provide a Symfony administration screen with translated labels and help text.
- Enforce a configurable super-admin permission or each definition's edit permission on the server.
- Validate changes and protect mutations against CSRF.
- Show the active source and allow save and reset operations.
- Audit who changed a setting and when without recording secret values.

## Runtime limits

The env processor resolves values when consuming services are initialized. It cannot
update a scalar already held by a service. Long-running consumers need explicit refresh,
service recreation, or the provider/value-object API. Definitions used to build the
container cannot be made dynamic through this processor.
