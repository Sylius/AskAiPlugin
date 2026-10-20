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

// Live components dispatch no DOM event on re-render; the component's "render:finished" hook is the only signal.
const scrollDown = (root) => {
    const messages = root.querySelector('.ai-assistant__messages');
    if (messages) {
        messages.scrollTop = messages.scrollHeight;
    }
};

document.addEventListener('live:connect', (event) => {
    const root = event.target.closest ? event.target.closest('.ai-assistant') : null;
    if (!root) {
        return;
    }
    scrollDown(root);
    event.detail.component.on('render:finished', () => scrollDown(root));
}, true);
