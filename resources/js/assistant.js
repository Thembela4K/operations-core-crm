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
        suggestionsUrl: shell.dataset.assistantSuggestionsUrl,
        historyUrl: shell.dataset.assistantHistoryUrl,
        messages: shell.querySelector('[data-assistant-messages]'),
        suggestions: shell.querySelector('[data-assistant-suggestions]'),
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
        'Tell me what you want to find. I can open filtered CRM pages for tenders, quotation requests, requisitions, documents, invoices, tasks, approvals, and notifications.',
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
        return;
    }

    elements.messages.replaceChildren();
    messages.forEach((message) => {
        appendAssistantMessage(elements, message.role, message.content);
    });
};

const renderAssistantSuggestions = (elements, suggestions) => {
    if (! Array.isArray(suggestions) || suggestions.length === 0) {
        return;
    }

    elements.suggestions.replaceChildren(...suggestions.slice(0, 7).map((suggestion) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.dataset.assistantPrompt = suggestion;
        button.textContent = suggestion;

        return button;
    }));
};

const setAssistantBusy = (elements, busy) => {
    elements.form.querySelector('button[type="submit"]').disabled = busy;
    elements.input.disabled = busy;
    elements.status.textContent = busy ? 'Reading the CRM and preparing the next step...' : 'Read-only. I can find and open records, not change them.';
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
        renderAssistantSuggestions(elements, data.suggestions);
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
            renderAssistantSuggestions(elements, data.suggestions);
            elements.shell.dataset.historyLoaded = '1';
        } catch {
            elements.shell.dataset.historyLoaded = '1';
        }
    }

    if (! elements.shell.dataset.suggestionsLoaded && elements.suggestionsUrl) {
        try {
            const response = await fetch(elements.suggestionsUrl, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            renderAssistantSuggestions(elements, data.suggestions);
            elements.shell.dataset.suggestionsLoaded = '1';
        } catch {
            elements.shell.dataset.suggestionsLoaded = '1';
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
        renderAssistantSuggestions(elements, data.suggestions);

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

    const suggestion = event.target.closest('[data-assistant-prompt]');

    if (suggestion) {
        submitAssistantMessage(suggestion.dataset.assistantPrompt);
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
