// The empty-state suggestions: each one is offered on a fresh conversation, a click sends it as the first message and the agent answers it; a write gets approved.
import { openPanel, log } from './lib.mjs';

const expected = ['What are the latest orders?', 'Which orders are waiting to be shipped?', 'Create a customer group called VIP'];

const ui = await openPanel();
for (const [i, text] of expected.entries()) {
    if (i > 0) {
        await ui.newConversation();
    }
    const offered = (await ui.page.locator('[data-ai-assistant-suggestion]').allInnerTexts()).map((b) => b.trim());
    log('offered:', JSON.stringify(offered), '| matches expected:', JSON.stringify(offered) === JSON.stringify(expected));
    await ui.snap(`empty-state-${i + 1}`);

    const t0 = Date.now();
    await ui.page.locator('[data-ai-assistant-suggestion]', { hasText: text }).click();
    await ui.running();
    const sent = (await ui.page.locator('.ai-assistant__message--user .ai-assistant__bubble').last().innerText()).trim();
    log(`#${i + 1} clicked:`, JSON.stringify(text), '| sent as user message:', sent === text, '| suggestions gone:', (await ui.page.locator('[data-ai-assistant-suggestion]').count()) === 0);
    await ui.idle();
    const notices = await ui.page.locator('.ai-assistant__notice').allInnerTexts();
    const reply = notices.length ? `NOTICE ${notices.join(' | ')}` : await ui.lastReply();
    const trace = await ui.page.locator('.ai-assistant__tools summary').allInnerTexts();
    log(`#${i + 1} reply (${Math.round((Date.now() - t0) / 1000)}s) | tools:`, JSON.stringify(trace.map((t) => t.trim())), '| reply:', JSON.stringify(reply.slice(0, 300)), reply.length > 300 ? `(+${reply.length - 300})` : '');
    await ui.snap(`reply-${i + 1}`);
    if (await ui.page.locator('.ai-assistant__action').count()) {
        log(`#${i + 1} proposal card:`, JSON.stringify((await ui.page.locator('.ai-assistant__action').last().innerText()).replace(/\s+/g, ' ').slice(0, 200)));
        await ui.approveLast();
        log(`#${i + 1} action badge:`, await ui.page.locator('.ai-assistant__action .badge').last().innerText());
        await ui.snap(`approved-${i + 1}`);
    }
}
await ui.close();
