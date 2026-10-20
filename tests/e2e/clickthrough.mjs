// The core loop: a read question with live progress, a write that becomes a proposal, approve, navigation, follow-up.
import { openPanel, log } from './lib.mjs';

const ui = await openPanel();
await ui.ask('read question', 'How many products and how many orders are there? Two numbers.');
log('trace:', await ui.page.locator('.ai-assistant__tools summary').last().innerText().catch(() => '(no trace)'));
await ui.ask('write', "Set the en_US name of product code Ethereal_Drift_T_Shirt to 'Clicked through'.");
log('proposal card:', (await ui.page.locator('.ai-assistant__action').last().innerText()).replace(/\s+/g, ' ').slice(0, 120));
await ui.approveLast('**/admin/products/*/edit');
log('action badge:', await ui.page.locator('.ai-assistant__action .badge').last().innerText());
log('panel still open after navigation:', (await ui.page.locator('.ai-assistant--open').count()) === 1);
await ui.close();
