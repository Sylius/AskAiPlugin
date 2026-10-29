# CHANGELOG

## Unreleased

Proof of concept of an AI assistant inside the Sylius admin, built on the Admin MCP tools
and the Symfony AI components.

- Chat panel in the admin layout (Live Component) with conversations persisted per administrator.
- The agent is `ai.agent.sylius_ask_ai`, defined by `symfony/ai-bundle` from prepended
  configuration (platform `ai.platform.anthropic` with `ANTHROPIC_API_KEY`, model, prompt file, tool
  limits), so it is configured like any Symfony AI agent; turns run asynchronously through Messenger.
- The Admin MCP tools are the agent's toolbox, called in-process; `delete_*`/`refund_*` are never exposed.
- The write gate: every write the agent attempts becomes a proposed action with Approve/Reject in the panel,
  replayed as the approving administrator.
- Navigation: the agent opens admin pages (show, edit, list with grid search) instead of pasting data.
- The trace of the tools called under each reply.
- The agent continues and saves the conversation through its message store (`message_store` of the agent config, backed by the entities; a conversation has a uuid for it).
- Entities are Sylius resources (interfaces, XML mapping, `sylius_ask_ai.resources`), every service has an id and an interface.
- A failed turn ends in the `failed` state with a notice; a stuck turn can be cancelled from the panel.
- The system prompt is a text template (`config/prompts/system_prompt.txt`; `{now}`, `{pages}` and `{store_instructions}` rendered by Symfony AI); `prompt.file` of the agent points at your own copy.
- The suggested questions on an empty conversation come from `sylius_ask_ai.suggestions` (translation keys or plain text).
- The admin log: read-only grids of conversations and agent actions under the "Ask AI" menu section, with a conversation page replaying the chat and an action page with the audit details.
