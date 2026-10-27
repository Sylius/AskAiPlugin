// The AI assistant panel: remembers whether it is open across page loads, follows the
// navigation the agent asks for, and keeps the timeline scrolled to the newest message.
// The panel itself is a live component (see templates/component/chat_panel.html.twig).

const COOKIE = 'sylius_ai_assistant_open';

const rememberOpen = (open) => {
    document.cookie = `${COOKIE}=${open ? '1' : '0'};path=/;SameSite=Lax`;
};

document.addEventListener('click', (event) => {
    const toggle = event.target.closest('.ai-assistant__toggle');
    if (toggle) {
        rememberOpen(!toggle.closest('.ai-assistant').classList.contains('ai-assistant--open'));
    }
});

document.addEventListener('ai-assistant:navigate', (event) => {
    if (event.detail && event.detail.url) {
        rememberOpen(true);
        window.location.assign(event.detail.url);
    }
});

// Live components dispatch no DOM event on re-render; the component's "render:started" and "render:finished" hooks are the only signals.
const STICK_DISTANCE = 48;
let sent = false;

const messagesOf = (root) => root.querySelector('.ai-assistant__messages');

const isAtBottom = (messages) => messages.scrollHeight - messages.scrollTop - messages.clientHeight <= STICK_DISTANCE;

const scrollDown = (root) => {
    const messages = messagesOf(root);
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
};

document.addEventListener('submit', (event) => {
    if (event.target.closest('.ai-assistant')) {
        sent = true;
    }
}, true);

document.addEventListener('click', (event) => {
    if (event.target.closest('.ai-assistant [data-live-action-param]')) {
        sent = true;
    }
}, true);

document.addEventListener('live:connect', (event) => {
    const root = event.target.closest ? event.target.closest('.ai-assistant') : null;
    if (!root) {
        return;
    }
    let stick = true;
    scrollDown(root);
    event.detail.component.on('render:started', () => {
        const messages = messagesOf(root);
        stick = sent || !messages || isAtBottom(messages);
    });
    event.detail.component.on('render:finished', () => {
        if (stick) {
            scrollDown(root);
        }
        sent = false;
        autosize(root.querySelector('.ai-assistant__input'));
    });
}, true);

const autosize = (input) => {
    if (!input || CSS.supports('field-sizing', 'content')) {
        return;
    }
    input.style.height = 'auto';
    input.style.height = `${Math.min(input.scrollHeight, 160)}px`;
};

document.addEventListener('input', (event) => {
    if (event.target.matches && event.target.matches('.ai-assistant__input')) {
        autosize(event.target);
    }
});

document.addEventListener('keydown', (event) => {
    const toggle = event.key === 'Escape' && event.target.closest ? event.target.closest('.ai-assistant--open')?.querySelector('.ai-assistant__toggle') : null;
    if (toggle) {
        toggle.click();
    }
});

document.addEventListener('keydown', (event) => {
    const input = event.target.closest ? event.target.closest('.ai-assistant__input') : null;
    if (!input || event.key !== 'Enter' || event.shiftKey || event.isComposing) {
        return;
    }
    event.preventDefault();
    input.form.requestSubmit();
});

document.addEventListener('click', (event) => {
    const suggestion = event.target.closest('[data-ai-assistant-suggestion]');
    const input = suggestion ? suggestion.closest('.ai-assistant').querySelector('.ai-assistant__input') : null;
    if (!input || input.disabled) {
        return;
    }
    input.value = suggestion.dataset.aiAssistantSuggestion;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.form.requestSubmit();
});
