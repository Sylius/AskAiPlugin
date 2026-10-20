// Does the timeline stay scrolled to the newest message: right after Enter, while the agent runs, after the reply?
import { openPanel, log } from './lib.mjs';

const ui = await openPanel();
const measure = async (label) => {
    const m = await ui.page.evaluate(() => {
        const el = document.querySelector('.ai-assistant__messages');
        return { top: Math.round(el.scrollTop), max: Math.round(el.scrollHeight - el.clientHeight), items: el.children.length };
    });
    log(label.padEnd(26), `scrollTop=${m.top} max=${m.max} items=${m.items}`, m.max - m.top <= 2 ? 'AT BOTTOM' : `!! ${m.max - m.top}px FROM BOTTOM`);
    return m;
};

const prompts = [
    'List the first five product names, one per line, with their codes.',
    'And the five after those, same format.',
    'Which of all those is the most expensive? Answer with name, code and price.',
];
for (const [i, prompt] of prompts.entries()) {
    await ui.page.fill('.ai-assistant input[data-model="draft"]', prompt);
    await ui.page.keyboard.press('Enter');
    await ui.running();
    await ui.page.waitForTimeout(300);
    await measure(`${i + 1}. after Enter`);
    await ui.snap(`${i + 1}-after-enter`);
    await ui.page.waitForTimeout(2500);
    await measure(`${i + 1}. while running`);
    await ui.idle();
    await ui.page.waitForTimeout(300);
    await measure(`${i + 1}. after reply`);
    await ui.snap(`${i + 1}-after-reply`);
}
// The same conversation after a page load with the panel open, and after closing and reopening the panel.
await ui.page.reload();
await ui.page.waitForSelector('.ai-assistant__panel');
await ui.page.waitForTimeout(300);
await measure('after page reload');
await ui.page.click('.ai-assistant__toggle');
await ui.page.waitForSelector('.ai-assistant__panel', { state: 'detached' });
await ui.page.click('.ai-assistant__toggle');
await ui.page.waitForSelector('.ai-assistant__panel');
await ui.page.waitForTimeout(300);
await measure('after close and reopen');
await ui.snap('reopened');
await ui.close();
