// An approved change takes the administrator to the edit page of what changed.
import { openPanel, log } from './lib.mjs';

const ui = await openPanel();
await ui.page.goto((process.env.BASE_URL ?? 'http://127.0.0.1:8000') + '/admin/');
await ui.page.waitForSelector('.ask-ai__panel');
await ui.ask('rename', "Rename Ethereal_Drift_T_Shirt (en_US) to 'Nav clicked'");
await ui.approveLast('**/admin/products/*/edit');
log('name field on the edit page:', await ui.page.inputValue('input[name*="[translations][en_US][name]"]').catch(() => '(not found)'));
await ui.close();
