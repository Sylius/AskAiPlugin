import { chromium } from 'playwright';
import { mkdirSync } from 'node:fs';

export const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:8000';
const SHOTS = new URL('./shots/', import.meta.url).pathname;
mkdirSync(SHOTS, { recursive: true });

export const log = (...a) => console.log(new Date().toISOString().slice(11, 19), ...a);

/** A logged-in admin page with the chat panel open on a fresh conversation. */
export async function openPanel({ newConversation = true } = {}) {
    const browser = await chromium.launch({
        headless: process.env.HEADED !== '1',
        // set when the installed Playwright has no matching browser, e.g. an older cached Chromium
        executablePath: process.env.PLAYWRIGHT_CHROMIUM || undefined,
    });
    const page = await browser.newPage({ viewport: { width: 1400, height: 900 } });
    await page.goto(BASE + '/admin/login');
    await page.fill('input[name="_username"]', process.env.ADMIN_USERNAME ?? 'sylius');
    await page.fill('input[name="_password"]', process.env.ADMIN_PASSWORD ?? 'sylius');
    await page.click('button[type="submit"]');
    await page.waitForURL('**/admin/**');
    await page.click('.ai-assistant__toggle');
    await page.waitForSelector('.ai-assistant__panel');
    // The id of the conversation the panel shows, from the live component's props: null before the first one.
    const conversationId = () => page.evaluate(() => JSON.parse(document.querySelector('.ai-assistant').dataset.livePropsValue).conversationId);
    let shot = 0;
    const api = {
        page,
        browser,
        /** Starts a new conversation and waits until the panel shows it (not its text: the intro reads the same before and after). */
        newConversation: async () => {
            const before = await conversationId();
            await page.click('.ai-assistant__panel button[data-live-action-param="startConversation"]');
            await page.waitForFunction((before) => JSON.parse(document.querySelector('.ai-assistant').dataset.livePropsValue).conversationId !== before, before);
        },
        snap: async (name) => {
            await page.screenshot({ path: `${SHOTS}${String(++shot).padStart(2, '0')}-${name}.png` });
        },
        running: () => page.waitForFunction(() => document.querySelector('.ai-assistant [data-model="draft"]')?.disabled === true, null, { timeout: 20000 }),
        idle: () => page.waitForFunction(() => { const i = document.querySelector('.ai-assistant [data-model="draft"]'); return i && !i.disabled; }, null, { timeout: 240000 }),
        lastReply: async () => (await page.locator('.ai-assistant__message--assistant .ai-assistant__bubble').last().innerText()).trim(),
        panelText: async () => (await page.locator('.ai-assistant__messages').innerText()).trim(),
        /** Sends a prompt; with expectUrl waits for the navigation the agent should trigger. Resolves to the reply. */
        ask: async (label, prompt, expectUrl = null) => {
            const before = page.url();
            await page.fill('.ai-assistant [data-model="draft"]', prompt);
            await page.keyboard.press('Enter');
            await api.running();
            if (expectUrl) {
                try { await page.waitForURL(expectUrl, { timeout: 240000 }); } catch { log('!!', label, 'did NOT navigate; still at', page.url()); }
                await page.waitForSelector('.ai-assistant__panel');
            }
            await api.idle();
            const reply = await api.lastReply();
            log(label, '| url:', page.url().replace(BASE, ''), '| navigated:', page.url() !== before, '| reply:', JSON.stringify(reply.slice(0, 140)), reply.length > 140 ? `(+${reply.length - 140})` : '');
            await api.snap(label.replace(/\W+/g, '-'));
            return reply;
        },
        /** Approves the last proposed action; with expectUrl waits for the navigation that follows execution. */
        approveLast: async (expectUrl = null) => {
            const approve = page.locator('.ai-assistant__action button[data-live-action-param="approve"]').last();
            if (!(await approve.count())) throw new Error('no proposal card to approve');
            const id = await approve.locator('xpath=ancestor::*[@data-action-id]').getAttribute('data-action-id');
            await approve.click();
            if (expectUrl) {
                try { await page.waitForURL(expectUrl, { timeout: 240000 }); } catch { log('!! after approve did NOT navigate; at', page.url()); }
                await page.waitForSelector('.ai-assistant__panel');
            }
            // Execution runs in the worker: wait for this card (not the last one: the follow-up turn may propose another) to leave proposed (warning) / approved (info).
            await page.waitForFunction((id) => { const badge = document.querySelector(`.ai-assistant__action[data-action-id="${id}"] .badge`); return badge && !badge.matches('.bg-warning-lt, .bg-info-lt'); }, id, { timeout: 240000 });
            await api.idle();
            log('after approve | url:', page.url().replace(BASE, ''), '| follow-up:', JSON.stringify((await api.lastReply()).slice(0, 140)));
        },
        close: () => browser.close(),
    };
    if (newConversation) {
        await api.newConversation();
    }
    return api;
}
