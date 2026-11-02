# License FAQ

This FAQ applies to Sylius Ask AI Plugin (`sylius/ask-ai-plugin`) version **0.1.0** and is shipped
unchanged with that release. It only explains the [LICENSE](LICENSE); it does not add or change any
obligation. If this FAQ and the LICENSE conflict, the LICENSE prevails.

## Which license applies?

The plugin is released under the Business Source License 1.1 (BUSL-1.1). It is source-available,
not open source. Each version converts to the MIT license four years after it is published.

Sylius core and its libraries remain under the MIT license. BUSL-1.1 applies to this plugin only.

## What can I do without a commercial license?

Regardless of telemetry, you may always:

- copy, modify and create derivative works of the plugin,
- redistribute it,
- use it in development, testing, staging and demonstration environments.

You may also use it in production for free, provided that:

- you do not deliberately disable, remove, block or modify the telemetry functionality so as to
  prevent it from operating as defined in the LICENSE, and
- you do not use the plugin to provide a competing offering.

## What is production use?

Running the plugin in an online store that is available to its customers. Development, testing,
staging and demonstration environments are non-production use.

## What does telemetry require?

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

The shop keeps working, but production use without telemetry falls outside the free license.
Under BUSL-1.1, use in violation of the license terminates your rights under it, for this and all
other versions of the plugin, including non-production use.

You then need to either restore telemetry or purchase a commercial license. Sylius has published a
[licensor statement](LICENSOR-STATEMENT.md) giving you 30 days from notice to do so before it
enforces the termination.

Setting any of the required settings above to `false` or `0`, or pointing `url` to a different
endpoint, is such a deliberate action.

## What is a competing offering?

A paid product or service, offered to third parties, that copies, embeds or hosts the plugin or a
substantial portion of it, and that provides functionality substantially similar to the plugin.

You remain free to build and sell your own AI assistant for the Sylius admin independently, without
using the plugin's code.

## I am an agency. Can I implement the plugin for my clients?

Yes. Implementation, customization, maintenance and support services provided to a store that uses
the plugin are not a competing offering.

## Who can sell me a commercial license?

Only Sylius sp. z o.o.

Contact: contact@sylius.com
