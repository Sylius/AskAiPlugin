# License FAQ

This FAQ applies to Sylius Ask AI Plugin (`sylius/ask-ai-plugin`) version **0.1.0** and is shipped
unchanged with that release. It only explains the [LICENSE](LICENSE); it does not add or change any
obligation. If this FAQ and the LICENSE conflict, the LICENSE prevails.

## Which license applies?

The plugin is released under the Business Source License 1.1 (BUSL-1.1). It is source-available,
not open source: the source code is publicly accessible, but production use is subject to the
conditions set out in the license. Each version converts to the MIT license four years after it is
published.

Sylius core and its libraries remain under the MIT license. BUSL-1.1 applies to this plugin only.

## What can I do without a commercial license?

The following rights are not conditional on telemetry being enabled. You may:

- copy, modify and create derivative works of the plugin,
- redistribute it, together with this license,
- use it in development, testing, staging and demonstration environments.

You may also use it in production for free, provided that:

- you do not deliberately disable, remove, block or modify the Telemetry Functionality so as to
  prevent it from operating as defined in the LICENSE, and
- you do not use the plugin to provide a Competing Offering.

If you use the plugin in production without meeting these conditions, all your rights under the
license terminate, including the rights listed above (see
[What happens if I disable telemetry in production?](#what-happens-if-i-disable-telemetry-in-production)).

## What is production use?

Running the plugin in an online store that is available to its end users. Development, testing,
staging and demonstration environments are non-production use.

## What does the telemetry condition require?

The free production use requires Sylius **2.2 or later**, with the Sylius telemetry feature and the
`sylius/telemetry` integration required by the plugin, with the following settings of
`sylius_core.telemetry`:

| Setting     | Environment variable         | Required value |
|-------------|------------------------------|----------------|
| `enabled`   | `SYLIUS_TELEMETRY_ENABLED`   | `true` / `1`   |
| `business`  | `SYLIUS_TELEMETRY_BUSINESS`  | `true` / `1`   |
| `technical` | `SYLIUS_TELEMETRY_TECHNICAL` | `true` / `1`   |
| `plugins`   | `SYLIUS_TELEMETRY_PLUGINS`   | `true` / `1`   |
| `url`       | -                            | default value  |

These are the defaults, so a standard installation meets the requirement without any changes.

Telemetry is described in the [Sylius telemetry documentation](https://docs.sylius.com/the-book/configuration/telemetry).

Temporary technical failures, such as a network outage or an unavailable Sylius endpoint, do not
breach the condition. Only a deliberate action that stops telemetry from working does.

## What happens if I disable telemetry in production?

Setting any of the required settings above to `false` or `0`, or pointing `url` to a different
endpoint, is a deliberate action that breaches the telemetry condition.

The shop keeps working, but as a consequence, production use without telemetry falls outside the
free license. Under BUSL-1.1, use in violation of the license terminates your rights under it, for
this and all other versions of the plugin, including non-production use.

You then need to either restore telemetry or purchase a commercial license. To give you time to fix
the situation, Sylius has committed, in a separate [licensor statement](LICENSOR-STATEMENT.md), not
to enforce the termination if you do so within 30 days of receiving notice from Sylius.

## What is a Competing Offering?

A paid product or service, offered to third parties, that copies, embeds or hosts the plugin or a
substantial portion of it, and that provides functionality substantially similar to the plugin.

You remain free to build and sell your own AI assistant for the Sylius admin independently, without
using the plugin's code.

## I am an agency or a hosting provider. Can I implement or host the plugin for my clients?

Yes. Implementation, customization, maintenance and support services, as well as hosting an online
store on behalf of its operator, do not constitute a Competing Offering when provided to a store
that uses the plugin.

## Who can sell me a commercial license?

Only Sylius sp. z o.o.

Contact: contact@sylius.com

## What telemetry data is collected?

Sylius collects the following telemetry data:

- **Technical data:** such as software versions, server environment, database configuration and
  installed public plugins.
- **Business data:** such as store configuration (locales, currencies, countries and channels),
  revenue and order statistics (mainly as ranges), and payment and shipping methods.
- **Installation identifier:** a unique identifier derived from the store's hostname and a salt.
  The hostname itself is not transmitted.

Telemetry is designed not to collect personal data. No customer data, admin data, product names,
order contents or exact monetary amounts are transmitted.

For the complete list of data collected, please refer to the
[Sylius telemetry documentation](https://docs.sylius.com/the-book/configuration/telemetry).
