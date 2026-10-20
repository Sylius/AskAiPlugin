// "Show me X" opens pages instead of listing data: list, filtered list, edit page, a resource found with the tools.
import { openPanel } from './lib.mjs';

const ui = await openPanel();
await ui.ask('list groups', 'Pokaż mi grupy klientów', '**/admin/customer-groups/**');
await ui.ask('search products', 'Show me the products with "jeans" in the name', '**/admin/products/?criteria**');
await ui.ask('edit product', 'Edit the product Ethereal_Drift_T_Shirt', '**/admin/products/*/edit');
await ui.ask('plain question', 'How many customer groups are there?');
await ui.close();
