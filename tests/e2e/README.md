# Browser click-through

Playwright scripts that drive the chat panel in a real browser against the running test
application (`symfony server:start` + the Messenger worker, see the main README). They are
not assertions-first tests: they print what happened at each step and take screenshots into
`shots/`, which is what a reviewer wants to look at.

```bash
cd tests/e2e && npm install && npx playwright install chromium
npm run clickthrough   # read question, write → proposal, approve, navigation, follow-up
npm run navigation     # "show me…" requests open admin pages instead of listing data
npm run approve-edit   # an approved change opens the edit page of what changed
npm run scroll         # the timeline stays scrolled to the newest message (send, run, reply, reload, reopen)
npm run suggestions  # the empty-state suggestions are offered and a click sends each one as the first message
npm run resume       # an old conversation is resumed from the history (grid and show page) and the agent continues it
```

Environment: `BASE_URL` (default `http://127.0.0.1:8000`), `ADMIN_USERNAME`/`ADMIN_PASSWORD`
(default `sylius`/`sylius`), `HEADED=1` to watch, `PLAYWRIGHT_CHROMIUM=<path>` to use a
browser binary other than the one Playwright installed. The scripts rename the product
`Ethereal_Drift_T_Shirt` from the default fixtures and `suggestions` creates the customer group `vip`;
reset them afterwards if it matters.
