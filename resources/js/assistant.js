let assistantConversationId = null;

const assistantElements = () => {
    const shell = document.querySelector('[data-assistant]');

    if (! shell) {
        return null;
    }

    return {
        shell,
        messageUrl: shell.dataset.assistantMessageUrl,
        newUrl: shell.dataset.assistantNewUrl,
        conversationsUrl: shell.dataset.assistantConversationsUrl,
        historyUrl: shell.dataset.assistantHistoryUrl,
        welcome: shell.dataset.assistantWelcome || 'Hi, how can I assist you today?',
        messages: shell.querySelector('[data-assistant-messages]'),
        historyPanel: shell.querySelector('[data-assistant-history-panel]'),
        historyList: shell.querySelector('[data-assistant-history-list]'),
        form: shell.querySelector('[data-assistant-form]'),
        input: shell.querySelector('[data-assistant-input]'),
        status: shell.querySelector('[data-assistant-status]'),
    };
};

const csrfToken = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

const scrollAssistant = (elements) => {
    elements.messages.scrollTop = elements.messages.scrollHeight;
};

const renderAssistantWelcome = (elements) => {
    elements.messages.replaceChildren();
    appendAssistantMessage(
        elements,
        'bot',
        elements.welcome,
    );
};

const appendAssistantMessage = (elements, role, text) => {
    const message = document.createElement('div');
    const visualRole = role === 'assistant' ? 'bot' : role;
    message.className = `assistant-message assistant-message-${visualRole}`;
    message.innerHTML = visualRole === 'user'
        ? '<span>You</span><p></p>'
        : '<span><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M8 3h8M6 8h12a3 3 0 0 1 3 3v5a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-5a3 3 0 0 1 3-3ZM8.5 13h.01M15.5 13h.01M9 17h6" /></svg>MIS</span><p></p>';
    message.querySelector('p').textContent = text;
    elements.messages.appendChild(message);
    scrollAssistant(elements);
};

const renderAssistantHistory = (elements, messages) => {
    if (! Array.isArray(messages) || messages.length === 0) {
        renderAssistantWelcome(elements);
        return;
    }

    elements.messages.replaceChildren();
    messages.forEach((message) => {
        appendAssistantMessage(elements, message.role, message.content);
    });
};

const setAssistantBusy = (elements, busy) => {
    elements.form.querySelector('button[type="submit"]').disabled = busy;
    elements.input.disabled = busy;
    elements.status.textContent = busy ? 'Reading the CRM and preparing the next step...' : 'Read-only. I can find and open records, not change them.';
};

const formatAssistantDate = (value) => {
    if (! value) {
        return 'No activity yet';
    }

    return new Intl.DateTimeFormat(undefined, {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
};

const renderConversationList = (elements, conversations) => {
    if (! elements.historyList) {
        return;
    }

    elements.historyList.replaceChildren();

    if (! Array.isArray(conversations) || conversations.length === 0) {
        const empty = document.createElement('p');
        empty.textContent = 'No previous MIS conversations yet.';
        elements.historyList.appendChild(empty);
        return;
    }

    conversations.forEach((conversation) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.assistantConversation = conversation.id;

        const title = document.createElement('strong');
        title.textContent = conversation.title || 'MIS conversation';

        const meta = document.createElement('span');
        meta.textContent = `${conversation.message_count || 0} messages - ${formatAssistantDate(conversation.updated_at)}`;

        button.append(title, meta);
        elements.historyList.appendChild(button);
    });
};

const loadConversationList = async (elements) => {
    if (! elements.conversationsUrl || ! elements.historyList) {
        return;
    }

    elements.historyList.replaceChildren();
    const loading = document.createElement('p');
    loading.textContent = 'Loading conversations...';
    elements.historyList.appendChild(loading);

    try {
        const response = await fetch(elements.conversationsUrl, { headers: { Accept: 'application/json' } });

        if (! response.ok) {
            throw new Error('Conversation list failed.');
        }

        const data = await response.json();
        renderConversationList(elements, data.conversations);
    } catch {
        elements.historyList.replaceChildren();
        const error = document.createElement('p');
        error.textContent = 'Conversation history could not be loaded.';
        elements.historyList.appendChild(error);
    }
};

const toggleAssistantHistory = async () => {
    const elements = assistantElements();

    if (! elements?.historyPanel) {
        return;
    }

    const shouldOpen = elements.historyPanel.hidden;
    elements.historyPanel.hidden = ! shouldOpen;

    if (shouldOpen) {
        await loadConversationList(elements);
    }
};

const loadAssistantConversation = async (conversationId) => {
    const elements = assistantElements();

    if (! elements?.historyUrl || ! conversationId) {
        return;
    }

    const url = new URL(elements.historyUrl, window.location.origin);
    url.searchParams.set('conversation_id', conversationId);
    elements.status.textContent = 'Loading previous MIS conversation...';

    try {
        const response = await fetch(url.toString(), { headers: { Accept: 'application/json' } });

        if (! response.ok) {
            throw new Error('Conversation load failed.');
        }

        const data = await response.json();
        assistantConversationId = data.conversation_id || null;
        renderAssistantHistory(elements, data.messages);
        elements.historyPanel.hidden = true;
        elements.status.textContent = 'Previous MIS conversation loaded.';
        elements.input.focus();
    } catch {
        elements.status.textContent = 'That MIS conversation could not be loaded.';
    }
};

const startAssistantConversation = async () => {
    const elements = assistantElements();

    if (! elements) {
        return;
    }

    assistantConversationId = null;
    renderAssistantWelcome(elements);
    elements.status.textContent = 'New MIS chat ready.';
    elements.input.value = '';
    if (elements.historyPanel) {
        elements.historyPanel.hidden = true;
    }
    elements.input.focus();

    if (! elements.newUrl) {
        return;
    }

    try {
        const response = await fetch(elements.newUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        if (! response.ok) {
            return;
        }

        const data = await response.json();
        assistantConversationId = data.conversation_id || null;
    } catch {
        assistantConversationId = null;
    }
};

const openAssistant = async () => {
    const elements = assistantElements();

    if (! elements) {
        return;
    }

    elements.shell.hidden = false;
    document.body.classList.add('assistant-open');
    setTimeout(() => elements.input?.focus(), 50);

    if (! elements.shell.dataset.historyLoaded && elements.historyUrl) {
        try {
            const response = await fetch(elements.historyUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            assistantConversationId = data.conversation_id || assistantConversationId;
            renderAssistantHistory(elements, data.messages);
            elements.shell.dataset.historyLoaded = '1';
        } catch {
            elements.shell.dataset.historyLoaded = '1';
        }
    }
};

const closeAssistant = () => {
    const elements = assistantElements();

    if (! elements) {
        return;
    }

    elements.shell.hidden = true;
    document.body.classList.remove('assistant-open');
};

const submitAssistantMessage = async (prompt = null) => {
    const elements = assistantElements();
    const message = (prompt || elements?.input?.value || '').trim();

    if (! elements || ! message) {
        return;
    }

    appendAssistantMessage(elements, 'user', message);
    elements.input.value = '';
    setAssistantBusy(elements, true);

    try {
        const response = await fetch(elements.messageUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                message,
                conversation_id: assistantConversationId,
            }),
        });

        if (! response.ok) {
            throw new Error('Assistant request failed.');
        }

        const data = await response.json();
        assistantConversationId = data.conversation_id || assistantConversationId;
        appendAssistantMessage(elements, 'bot', data.reply || 'I found a matching CRM action.');

        if (data.action?.type === 'navigate' && data.action.url && data.action.auto) {
            elements.status.textContent = 'Opening the filtered CRM page...';
            setTimeout(() => {
                window.location.href = data.action.url;
            }, 850);
        } else {
            setAssistantBusy(elements, false);
        }
    } catch {
        appendAssistantMessage(elements, 'bot', 'I could not complete that request. Try a specific module, record type, or date range.');
        setAssistantBusy(elements, false);
    }
};

document.addEventListener('click', (event) => {
    if (event.target.closest('[data-assistant-toggle]')) {
        openAssistant();
        return;
    }

    if (event.target.closest('[data-assistant-close]')) {
        closeAssistant();
        return;
    }

    if (event.target.closest('[data-assistant-new]')) {
        startAssistantConversation();
        return;
    }

    if (event.target.closest('[data-assistant-history-toggle]')) {
        toggleAssistantHistory();
        return;
    }

    const conversation = event.target.closest('[data-assistant-conversation]');

    if (conversation) {
        loadAssistantConversation(conversation.dataset.assistantConversation);
    }
});

document.addEventListener('submit', (event) => {
    if (! event.target.matches('[data-assistant-form]')) {
        return;
    }

    event.preventDefault();
    submitAssistantMessage();
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        closeAssistant();
    }
});
