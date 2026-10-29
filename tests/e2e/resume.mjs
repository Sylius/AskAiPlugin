// Resuming a conversation: a question is asked in one conversation, another one is started, then the first is resumed
// from the history (grid action and the show page) and the agent still remembers what was said in it.
import { openPanel, log, BASE } from './lib.mjs';

const ui = await openPanel();
const { page } = ui;
const conversationId = () => page.evaluate(() => JSON.parse(document.querySelector('.ask-ai').dataset.livePropsValue).conversationId);

await ui.ask('first', 'The code word for today is turquoise. Just remember it, no need to look anything up. Answer in plain text, without emoji.');
const first = await conversationId();
await ui.newConversation();
await ui.ask('second', 'What are the latest orders?');
const second = await conversationId();
log('conversations:', { first, second });
if (first === second) throw new Error('the second question should have gone to a new conversation');

// From the grid: only own conversations offer the play button; resuming reopens the panel on the first conversation.
await page.goto(`${BASE}/admin/ask-ai/conversations`);
await page.click('.ask-ai__toggle'); // the open panel covers the action column of the last rows
const buttons = await page.locator('form[action$="/resume"] button').count();
log('resume buttons in the grid:', buttons);
await page.click(`form[action$="/conversations/${first}/resume"] button`);
await page.waitForURL('**/admin/ask-ai/conversations');
await page.waitForSelector('.ask-ai__panel');
const shown = await conversationId();
log('after grid resume | panel open:', await page.locator('.ask-ai--open').count() === 1, '| conversation:', shown, '| expected:', first);
if (shown !== first) throw new Error('the panel should show the resumed conversation');
log('panel text starts with:', JSON.stringify((await ui.panelText()).slice(0, 80)));
await ui.snap('resumed-from-grid');

// The panel keeps the resumed conversation on other pages.
await page.goto(`${BASE}/admin/`);
await page.waitForSelector('.ask-ai__panel');
log('on the dashboard | conversation:', await conversationId());
if ((await conversationId()) !== first) throw new Error('the resumed conversation should survive navigation');

// The agent continues the old conversation.
const reply = await ui.ask('follow-up', 'What is the code word for today?');
log('remembers turquoise:', /turquoise/i.test(reply));
await ui.snap('follow-up');

// From the show page: the Resume button in the header brings the second conversation back.
await page.goto(`${BASE}/admin/ask-ai/conversations/${second}`);
await page.click('.ask-ai__toggle');
await page.click('[data-test-resume-conversation]');
await page.waitForURL(`**/conversations/${second}`);
await page.waitForSelector('.ask-ai__panel');
log('after show-page resume | conversation:', await conversationId(), '| expected:', second);
if ((await conversationId()) !== second) throw new Error('the show page should resume its conversation');
await ui.snap('resumed-from-show');
await ui.close();
