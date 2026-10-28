<p align="center">
    <a href="https://sylius.com" target="_blank">
        <picture>
          <source media="(prefers-color-scheme: dark)" srcset="https://media.sylius.com/sylius-logo-800-dark.png">
          <source media="(prefers-color-scheme: light)" srcset="https://media.sylius.com/sylius-logo-800.png">
          <img alt="Sylius Logo." src="https://media.sylius.com/sylius-logo-800.png">
        </picture>
    </a>
</p>

<h1 align="center">Sylius AI Assistant Plugin</h1>

<p align="center">AI assistant panel inside the Sylius admin, working the store through the Admin MCP tools with human approval of every write.</p>

## About

AI assistant panel inside the Sylius admin. The assistant works the store through the
tools of [`sylius/admin-mcp-server-plugin`](https://github.com/Sylius/AdminMcpServerPlugin)
as the logged-in administrator; write actions are proposed and need human approval.

Status: proof of concept. Working today: chat panel (Live Component hooked into the
admin layout), conversation/message persistence, asynchronous turns via Messenger, a real
agent loop over the Admin MCP tools, and the write gate: every write the agent attempts is
held as a proposed action with Approve/Reject in the panel.

## Requirements

- Sylius 2.1+, PHP 8.2+, Symfony 7.4+.
- [`sylius/admin-mcp-server-plugin`](https://github.com/Sylius/AdminMcpServerPlugin) installed and
  configured: the assistant works the store through its tools and the Admin API it talks to.
- A working Admin API: the agent calls it as the administrator who is chatting, so the Lexik JWT
  keypair must exist (`php bin/console lexik:jwt:generate-keypair`; a fresh Sylius-Standard
  does not ship one) and that administrator needs `ROLE_API_ACCESS` (Step 6).
- A Messenger worker: turns run asynchronously, never in the web request.
- A model: an Anthropic API key (the default, `ai.platform.anthropic`) or any other Symfony AI
  platform (OpenAI, Gemini, Ollama, …). For development without paying for tokens, see
  [Developing without an API bill](#developing-without-an-api-bill).

## Installation

There is no Flex recipe yet; the steps are manual.

### Step 1 - Add the plugin via Composer

```bash
composer require sylius/ai-assistant-plugin
```

The Admin MCP plugin comes with it as a dependency. If it was not installed before, follow
its own installation steps too (OAuth keys, `SYLIUS_ADMIN_MCP_SERVER_*` variables, bundle
registration, configuration and routes import).

With Symfony Flex active (the default in Sylius-Standard) two things happen during this command:

- The `league/oauth2-server-bundle` recipe is applied and creates
  `config/packages/league_oauth2_server.yaml` and `config/routes/league_oauth2_server.yaml`, which
  conflict with the configuration the Admin MCP plugin ships. Replace both with empty stubs, as its
  README says:

  ```bash
  echo "# Managed by sylius/admin-mcp-server-plugin" > config/packages/league_oauth2_server.yaml
  echo "# Routes provided by sylius/admin-mcp-server-plugin" > config/routes/league_oauth2_server.yaml
  ```

- Flex registers the plugin's bundle in `config/bundles.php` but, as there is no recipe, does not
  import its configuration, so the `cache:clear` that runs at the end of `composer require` fails
  with `bus "sylius_ai_assistant.command_bus" ... does not exist`. This is expected: the error goes
  away after Step 3.

### Step 2 - Register the bundle

Skip this step when Flex has already added the plugin to `config/bundles.php`. Otherwise add it
after the Admin MCP plugin:

```php
return [
    // ...
    Symfony\AI\AiBundle\AiBundle::class => ['all' => true],
    Symfony\AI\McpBundle\McpBundle::class => ['all' => true],
    Sylius\AdminMcpServerPlugin\SyliusAdminMcpServerPlugin::class => ['all' => true],
    Sylius\AiAssistantPlugin\SyliusAiAssistantPlugin::class => ['all' => true],
];
```

`AiBundle` (`symfony/ai-bundle`, installed with the plugin) builds the platform the agent talks
to; the plugin configures its Anthropic platform with `ANTHROPIC_API_KEY`, so nothing else is
needed to start.

### Step 3 - Import the configuration

```yaml
# config/packages/sylius_ai_assistant.yaml
imports:
    - { resource: "@SyliusAiAssistantPlugin/config/config.yaml" }
```

This registers the resources, the Messenger bus and transport (`sylius_ai_assistant`, on
Doctrine by default; override with `SYLIUS_AI_ASSISTANT_TRANSPORT_DSN`) and the twig hook
that renders the panel in the admin layout.

The panel's styles and behaviour ship in `assets/admin/entrypoint.js`. Import it from your admin
entrypoint and rebuild the admin assets:

```js
// assets/admin/entrypoint.js
import '@vendor/sylius/ai-assistant-plugin/assets/admin/entrypoint';
```

(`@vendor` is the alias Sylius-Standard's Webpack configuration defines; a relative
`../../vendor/sylius/ai-assistant-plugin/assets/admin/entrypoint.js` works too.)

```bash
yarn encore prod
```

Then import the routes of the admin log (read-only pages listing conversations and agent actions):

```yaml
# config/routes/sylius_ai_assistant.yaml
sylius_ai_assistant:
    resource: "@SyliusAiAssistantPlugin/config/routes.yaml"
```

### Step 4 - Run database migrations

```bash
php bin/console doctrine:migrations:migrate -n
```

### Step 5 - Run the worker

```bash
php bin/console messenger:consume sylius_ai_assistant
```

In production keep it under a process manager (Supervisor, systemd) like any other Messenger
worker. The worker is where the model is called: on the default platform (the Anthropic API) it
needs `ANTHROPIC_API_KEY` in its environment.

### Step 6 - Give administrators API access

The agent calls the Admin API as the administrator who is chatting, so that administrator
needs `ROLE_API_ACCESS`. The panel then appears on every admin page, for the administrators
with that role only (`sylius_ai_assistant.access.role` names another role when the assistant
should be narrower than the API, see Configuration).

## Demo in Docker

The repository ships a Compose stack that builds the test application with the plugin, loads
the Sylius fixtures and runs the Messenger worker, so the assistant can be shown without any
local PHP setup:

```bash
echo "ANTHROPIC_API_KEY=sk-ant-..." > tests/TestApplication/.env.local
docker compose up -d --build
```

The admin is at http://localhost:8000/admin (`sylius` / `sylius`, with `ROLE_API_ACCESS`
already granted). `APP_PORT` changes the published port, `docker compose down -v` resets the
database.

To run it on a server, publish the image and use `compose.deploy.yaml`, which pulls it instead
of building and serves the domain over HTTPS (Caddy obtains the certificate):

```bash
docker buildx build --platform linux/amd64,linux/arm64 \
    -t ghcr.io/mpysiak/sylius-ai-assistant-demo:latest --push .

# on the server, with DNS pointing at it and ports 80/443 open
SERVER_NAME=demo.example.com ANTHROPIC_API_KEY=sk-ant-... APP_SECRET=$(openssl rand -hex 16) \
    docker compose -f compose.deploy.yaml up -d
```

## Configuration

Everything lives under `sylius_ai_assistant` (`bin/console config:dump-reference
sylius_ai_assistant` prints the full tree):

```yaml
sylius_ai_assistant:
    tools:                          # which Admin MCP tools the agent gets, by name prefix
        read_only_prefixes: [list_, get_, search_, count_, open_]
        write_prefixes: [create_, update_, add_, remove_, set_, archive_, restore_, resend_, cancel_, ship_, complete_, accept_, reject_, generate_]
        exclude: []                 # single tools taken away by name, e.g. [update_channel]
    access:
        role: ROLE_API_ACCESS       # who sees and uses the panel; a role of your own must imply ROLE_API_ACCESS
    prompt:
        additional_instructions: ~  # rules of the store appended to the system prompt, see The agent
    poll_interval: 2000             # ms, how often the panel polls during a turn
    suggestions: [ ... ]            # the questions offered on an empty conversation, see The chat panel
    navigation:
        pages: { ... }              # the admin pages the agent may open, see Navigation
    resources:                      # Sylius resources: conversation, message, agent_action
        conversation:
            classes:
                model: App\Entity\Conversation
```

### The agent

The agent is `ai.agent.sylius_ai_assistant`, defined by `symfony/ai-bundle` from
configuration the plugin prepends; the plugin only adds the Admin MCP tools to its toolbox and
tags its processors for it. So the agent is configured where every Symfony AI agent is, and the
application overrides any of it:

```yaml
# config/packages/ai.yaml
ai:
    agent:
        sylius_ai_assistant:
            platform: ai.platform.anthropic       # any PlatformInterface service id
            model: claude-haiku-4-5
            prompt:
                file: '%kernel.project_dir%/config/ai/system_prompt.txt'
            max_tool_calls: 50
            exclude_tool_messages: true           # tool messages stay out of the stored history, the trace shows them
            fault_tolerant_toolbox: false
```

The system prompt is [`config/prompts/system_prompt.txt`](config/prompts/system_prompt.txt), a
Symfony AI string template: `{pages}` is rendered with the admin pages the agent may open and
`{store_instructions}` with what the store adds (`AdminPagesInputProcessor` and
`StoreInstructionsInputProcessor` set the template variables). A few rules of your own go in
without touching the file:

```yaml
sylius_ai_assistant:
    prompt:
        additional_instructions: |
            Always answer in Polish.
            Our SKUs look like FW-1234; a "collection" is a taxon under /collections.
```

To rewrite the prompt, copy the file into your application, edit the copy and point
`prompt.file` at it; the rules about `open_admin_page` and `awaiting_approval` are what the chat
panel relies on, keep them unless you change the panel too. The other `prompt` options of the
bundle (`include_tools`, translation) work as documented in the
[AI bundle](https://symfony.com/doc/current/ai/bundles/ai-bundle.html).

## The admin log

The "AI assistant" section of the admin menu lists every conversation (`/admin/ai-assistant/conversations`,
with the first question as its topic, the administrator, state and dates) and every action the agent
proposed (`/admin/ai-assistant/agent-actions`: tool, request, state, who decided and when). A conversation's
page replays it with the same bubbles and action cards as the panel, read-only; an action's page shows
the tool arguments, request body, the snapshot taken before the change and the result. Both grids are
Sylius grids (`sylius_ai_assistant_admin_conversation`, `sylius_ai_assistant_admin_agent_action`) and
can be extended the usual way; the pages are built from twig hooks under `sylius_ai_assistant.conversation.show`
and `sylius_ai_assistant.agent_action.show`.

## How a turn runs

The agent is built on the Symfony AI components. `symfony/ai-agent` runs the turn: the model
of your choice through `symfony/ai-platform`, with the Admin MCP tools as its toolbox,
called in-process (the same services the MCP server exposes; `delete_*` and `refund_*` are
never exposed). `symfony/ai-chat` holds the conversation on a message store backed by the
`Conversation`/`Message` entities. The tools the assistant called are stored with its reply
and shown under it in the panel.

### Choosing the model

`ai.platform.anthropic` (the default) works without installing anything:

- The Anthropic API, built by `symfony/ai-bundle`. Set
  `ANTHROPIC_API_KEY` where the worker runs and it works; the default model is `claude-haiku-4-5`.
  `model` takes any name from the Anthropic bridge's model catalog
  (`symfony/ai-anthropic-platform`); a model newer than the installed bridge needs a bridge update.
  The plugin only prepends the key; everything else about the platform is the bundle's own
  configuration, override it under `ai.platform.anthropic` (`http_client`, `cache_retention`, …):

  ```yaml
  # config/packages/ai.yaml
  ai:
      platform:
          anthropic:
              api_key: '%env(ANTHROPIC_API_KEY)%'
              http_client: app.ai_http_client
              cache_retention: long
  ```

Any other Symfony AI platform works the same way once the vendor's platform package is
installed. For OpenAI:

```bash
composer require symfony/ai-open-ai-platform
```

```yaml
# config/packages/ai.yaml
ai:
    platform:
        openai:
            api_key: '%env(OPENAI_API_KEY)%'
    agent:
        sylius_ai_assistant:
            platform: ai.platform.openai
            model: gpt-4o-mini
```

| Vendor | Package                       | `ai.platform` key | `platform`           |
|--------|-------------------------------|-------------------|----------------------|
| OpenAI | `symfony/ai-open-ai-platform` | `openai`          | `ai.platform.openai` |
| Gemini | `symfony/ai-gemini-platform`  | `gemini`          | `ai.platform.gemini` |
| Ollama | `symfony/ai-ollama-platform`  | `ollama`          | `ai.platform.ollama` |

The `symfony/ai-bundle` recipe has already created `config/packages/ai.yaml` with an `ai:` key
(and the vendor's platform recipe adds its `ai.platform.<vendor>` entry and `<VENDOR>_API_KEY`
there): put the `agent` part under that existing key rather than pasting the snippet at the end
of the file, which gives `Duplicate key "ai"`.

Ollama takes `endpoint` instead of `api_key`. The full list of platforms and their options is in
the [Symfony AI documentation](https://symfony.com/doc/current/ai/index.html); any other service
implementing `PlatformInterface` works too, point `platform` at its id.

### Developing without an API bill

The functional tests run on a scripted platform and cost nothing. For clicking through the panel
during development, two platforms with tool calling cost nothing either; both are the same agent
on a different `platform`, nothing in the plugin changes:

- **Ollama**, a local model. Install [Ollama](https://ollama.com), pull a model that supports
  tools (`ollama pull qwen3`), then:

  ```bash
  composer require symfony/ai-ollama-platform
  ```

  ```yaml
  # config/packages/dev/ai.yaml
  ai:
      platform:
          ollama:
              endpoint: 'http://127.0.0.1:11434'
      agent:
          sylius_ai_assistant:
              platform: ai.platform.ollama
              model: 'qwen3?num_ctx=32768'
  ```

  `num_ctx` matters: Ollama defaults to a 4096-token window and silently drops the front of
  a longer prompt, and the tool definitions alone are ~22k tokens, so without it the model sees
  only the tail of the tool list and claims the tools do not exist.

  Local models follow the system prompt less reliably than Claude: expect the simple flows
  (lists, navigation, a single proposal) to work and long tool chains to be hit and miss. A
  turn also costs real time: on a 16 GB laptop a 4B model reads the cold prompt for over a
  minute and thinks for another before every tool call, so a machine with more memory
  bandwidth (a Max-class Apple chip or a discrete GPU) is what makes Ollama comfortable.
- **Gemini** on the free tier of [Google AI Studio](https://aistudio.google.com): a free API key
  with a per-minute quota.

  ```bash
  composer require symfony/ai-gemini-platform
  ```

  ```yaml
  # config/packages/dev/ai.yaml
  ai:
      platform:
          gemini:
              api_key: '%env(GEMINI_API_KEY)%'
      agent:
          sylius_ai_assistant:
              platform: ai.platform.gemini
              model: gemini-2.5-flash
  ```

## Customization

Everything below is either configuration or the usual Symfony and Sylius extension points; no
fork and no copy of a service definition is needed for:

| To                                                        | Use                                                                                          |
|-----------------------------------------------------------|----------------------------------------------------------------------------------------------|
| Another vendor or model                                   | `ai.agent.sylius_ai_assistant.platform` / `model` ([Choosing the model](#choosing-the-model)) |
| A few rules of the store in the prompt                    | `sylius_ai_assistant.prompt.additional_instructions` ([The agent](#the-agent))               |
| A prompt of your own                                      | `ai.agent.sylius_ai_assistant.prompt.file`                                                   |
| Fewer tools, or one tool less                             | `tools.read_only_prefixes`, `tools.write_prefixes`, `tools.exclude` ([The write gate](#the-write-gate)) |
| A tool of your own                                        | `#[AsTool]` + the `sylius_ai_assistant.tool` tag, or `tools:` in `ai.yaml` ([Your own tools](#your-own-tools)) |
| A resource of your own the agent may open                 | `sylius_ai_assistant.navigation.pages` ([Navigation](#navigation))                           |
| Who sees and uses the panel                               | `sylius_ai_assistant.access.role`                                                            |
| The store's own suggested questions                       | `sylius_ai_assistant.suggestions` ([The chat panel](#the-chat-panel))                        |
| Something added to, removed from or reordered in the panel | the `sylius_ai_assistant.chat_panel.*` twig hooks ([The chat panel](#the-chat-panel))         |
| Another look for a bubble, the action card, the log pages | `templates/bundles/SyliusAiAssistantPlugin/…` ([Models and services](#models-and-services)) |
| Extra fields on the entities, another repository          | `sylius_ai_assistant.resources.<name>.classes` ([Models and services](#models-and-services)) |
| Different behaviour of a service                          | Decorate it by id or interface ([Models and services](#models-and-services))                 |

## Models and services

The entities (`Conversation`, `Message`, `AgentAction`) are Sylius resources: each has an
interface, is mapped in `config/doctrine` as a mapped superclass, and can be replaced under
`sylius_ai_assistant.resources.<name>.classes.model`; the resource bundle provides
`sylius_ai_assistant.repository.<name>` and `sylius_ai_assistant.factory.<name>`; the message
and agent action factories are decorated with `createForConversation()` and `createProposal()`, the only
ways these two are created (`MessageFactoryInterface`, `AgentActionFactoryInterface`). Every
service has an id (`sylius_ai_assistant.*`) and an interface aliased to it, so a service can be
decorated or swapped by id. A turn that fails for any reason leaves the conversation in the
`failed` state with a notice in the panel; a turn that never comes back can be cancelled there.

Templates live under the bundle's `@SyliusAiAssistantPlugin` namespace, so the application
overrides any of them the Symfony way: copy the file to
`templates/bundles/SyliusAiAssistantPlugin/<same path>` (for example
`templates/bundles/SyliusAiAssistantPlugin/component/action_card.html.twig` for the proposed
action card, `component/message.html.twig` for a bubble). The panel itself is put together
from twig hooks, so most changes to it need no copy at all.

### The chat panel

The open panel renders the `sylius_ai_assistant.chat_panel` hook with the live component as
`panel` in the context (`hookable_metadata.context.panel`: `conversation`, `timeline`,
`running`). Its hookables are themselves hooks, with the templates under
`templates/component/chat_panel/`:

| Hook                                              | Hookables (priority)                                          |
|---------------------------------------------------|---------------------------------------------------------------|
| `sylius_ai_assistant.chat_panel`                  | `header` (200), `body` (100), `footer` (0)                    |
| `sylius_ai_assistant.chat_panel.header`           | `title` (200), `history` (100), `new_chat` (0)                |
| `sylius_ai_assistant.chat_panel.body`             | `empty_state` (100), `timeline` (0); the context also carries `timeline` |
| `sylius_ai_assistant.chat_panel.body.empty_state` | `intro` (100), `suggestions` (0)                              |
| `sylius_ai_assistant.chat_panel.footer`           | `composer` (0)                                                |

The suggested questions on an empty conversation are a list in the configuration, each a
translation key or plain text; the list replaces the default one, so give the whole set, in
order, and an empty list shows none:

```yaml
sylius_ai_assistant:
    suggestions:
        - app.ai_assistant.suggestion.unpaid_orders      # translated in your messages.<locale>.yaml
        - Which coupons expire this month?               # or plain text, as is (a literal % is written %%)
        - sylius_ai_assistant.ui.suggestion.create_a_customer_group_called_vip   # one of the defaults kept
```

For anything else, the usual `sylius_twig_hooks` configuration adds, replaces, reorders or
disables a part, for example a notice above the composer or suggestions rendered your own way:

```yaml
sylius_twig_hooks:
    hooks:
        'sylius_ai_assistant.chat_panel.footer':
            notice:
                template: 'admin/ai_assistant/notice.html.twig'
                priority: 10
        'sylius_ai_assistant.chat_panel.body.empty_state':
            suggestions:
                template: 'admin/ai_assistant/suggestions.html.twig'
```

A suggestion is any element with `data-ai-assistant-suggestion="<the question>"`: clicking it
sends the question; the configured list is `panel.suggestions` in the hook context.
The composer's textarea keeps the `ai-assistant__input` class and the `draft` live model, which
the keyboard handling and the browser tests rely on.

## The write gate

`ProposingApiClient` decorates the Admin MCP API client. Inside a chat turn, `POST/PUT/PATCH/
DELETE` are not sent: an `AgentAction` (tool name, arguments, method, path, body, snapshot of
the current resource) is stored as *proposed* and the tool returns an `awaiting_approval`
notice the model relays. Approving in the panel dispatches `ExecuteAgentAction`: the worker
replays the call as the approving administrator, stores the result, and runs a follow-up
turn so the assistant reports the outcome. Rejections are passed to the model with the next
message. Reads always pass through, as does everything outside a chat turn (OAuth clients).

Requirements: the administrator using the chat needs `ROLE_API_ACCESS` (the agent calls the
Admin API as them). Which tools the agent gets: `sylius_ai_assistant.tools.read_only_prefixes`
and `tools.write_prefixes`, minus the names listed in `tools.exclude`.

### Your own tools

A tool of your own is a Symfony AI tool (`#[AsTool]`, or a service with a name, a description
and a method) that joins the Admin MCP tools in the agent's toolbox; it is not served by the
MCP server, and the write gate does not apply to it (it is called in-process, not through the
Admin API). Either tag it for this agent only:

```php
#[AsTool(name: 'count_open_tickets', description: 'count_open_tickets() → the number of open support tickets.')]
final class CountOpenTickets
{
    public function __invoke(): int { /* ... */ }
}
```

```yaml
services:
    App\Ai\Tool\CountOpenTickets:
        tags: [sylius_ai_assistant.tool]
```

or list it under the agent in `ai.yaml`, as for any Symfony AI agent (`tools: [{ service: ... }]`,
with `name`, `description` and `method` when the class has no `#[AsTool]`); the Admin MCP
tools are added to that list. Tools tagged `ai.tool` for every agent of the application join as
well. The plugin's own `open_admin_page` is registered this way.

## Navigation

Showing things means opening the page, not pasting data into the chat. The agent's own tool
`open_admin_page(resource, identifier?, page?, search?)` opens the show page of one resource
("open the most expensive product"), its edit page ("edit product X"), or the list page,
filtered through the grid's search when a term is given ("show me the products with 'mug' in
the name", "show me the customer groups"). The system prompt tells the model to open pages
for see/show/open/edit requests and to answer with one confirming sentence, and lists the
pages it can open; data goes to the chat only for questions. After an approved action is
executed, the panel goes to the page of what was created (show page) or changed (edit page)
on its own, from the `@id` of the API response; nothing happens when the administrator is
already there. The pages the agent may open are `sylius_ai_assistant.navigation.pages`, a
whitelist keyed by the Admin API collection segment (the defaults are in the plugin's
`config/config.yaml`); the panel performs the navigation on its next poll and keeps itself
open across the page load (cookie). Add a resource of your own, or switch a default one off:

```yaml
sylius_ai_assistant:
    navigation:
        pages:
            tickets:                                  # /api/v2/admin/tickets/{id}
                repository: app.repository.ticket     # where the identifier is looked up
                field: id                             # the field the Admin API identifies it by
                show: app_admin_ticket_show           # optional, "show" falls back to edit
                edit: app_admin_ticket_update
                index: app_admin_ticket_index         # optional, no list page without it
                search: search                        # optional, the grid filter a search term goes to
                # parent: { param: customerId, path: customer.id }   for a nested resource
            exchange-rates: false
```

## Development

```bash
composer install
vendor/bin/console doctrine:database:create
vendor/bin/console doctrine:migrations:migrate -n
vendor/bin/console sylius:fixtures:load default -n
(cd vendor/sylius/test-application && yarn install && yarn build)
symfony server:start -d --document-root=vendor/sylius/test-application/public --no-tls
vendor/bin/console messenger:consume sylius_ai_assistant --sleep=0.25 -vv
```

Admin: http://127.0.0.1:8000/admin/ (`sylius` / `sylius`). Database and Admin MCP plugin
settings live in `tests/TestApplication/.env` (override in `.env.local`).

Browser click-through of the panel (Playwright, against the running app): `tests/e2e`.

Dev tooling: Symfony AI Mate with the Sylius extension is installed (`vendor/bin/mate`);
it boots `Sylius\TestApplication\Kernel` from `tests/TestApplication/.env`.

## Contributing

Would like to help us build a more capable Sylius admin? Start from reading our
[Contribution Guide](https://docs.sylius.com/en/latest/contributing/).

## Bug Tracking

If you want to report a bug or suggest an idea, please use
[GitHub issues](https://github.com/Sylius/AiAssistantPlugin/issues).

## Security Issues

If you think that you have found a security issue, please do not use the issue tracker and do
not post it publicly. Instead, all security issues must be sent to `security@sylius.com`.

## Community Support

Get Sylius support on [Slack](https://sylius.com/slack), [Forum](https://forum.sylius.com/) or
[Stack Overflow](https://stackoverflow.com/questions/tagged/sylius).

## MIT License

The Sylius AI Assistant Plugin is released under the terms of the [MIT License](LICENSE).
