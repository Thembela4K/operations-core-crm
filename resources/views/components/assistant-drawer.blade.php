@php
    $assistantName = trim(auth()->user()?->name ?? '');
    $assistantFirstName = $assistantName !== '' ? preg_split('/\s+/', $assistantName)[0] : 'there';
    $assistantGreeting = "Hi {$assistantFirstName}, how can I assist you today?";
@endphp

<div
    class="assistant-shell"
    data-assistant
    data-assistant-message-url="{{ route('assistant.message') }}"
    data-assistant-new-url="{{ route('assistant.conversation') }}"
    data-assistant-conversations-url="{{ route('assistant.conversations') }}"
    data-assistant-history-url="{{ route('assistant.history') }}"
    data-assistant-welcome="{{ $assistantGreeting }}"
    hidden
>
    <button class="assistant-backdrop" type="button" data-assistant-close aria-label="Close MIS"></button>

    <section class="assistant-drawer" role="dialog" aria-modal="true" aria-label="Ask MIS">
        <header class="assistant-header">
            <div class="assistant-title">
                <span class="assistant-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 3v3" />
                        <path d="M8 3h8" />
                        <path d="M6 8h12a3 3 0 0 1 3 3v5a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-5a3 3 0 0 1 3-3Z" />
                        <path d="M8.5 13h.01M15.5 13h.01" />
                        <path d="M9 17h6" />
                    </svg>
                </span>
                <span class="assistant-title-copy">
                    <span>Ask MIS</span>
                    <strong>Operations helpdesk</strong>
                </span>
            </div>
            <div class="assistant-header-actions">
                <button class="assistant-history-button" type="button" data-assistant-history-toggle aria-label="Past conversations">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7" /><path d="M3 4v5h5" /><path d="M12 7v5l3 2" /></svg>
                </button>
                <button class="assistant-new-chat" type="button" data-assistant-new>
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
                    <span>New chat</span>
                </button>
                <button class="assistant-close" type="button" data-assistant-close aria-label="Close">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12" /></svg>
                </button>
            </div>
        </header>

        <div class="assistant-body">
            <section class="assistant-history-panel" data-assistant-history-panel hidden>
                <div class="assistant-history-heading">
                    <span>Past conversations</span>
                    <button type="button" data-assistant-history-toggle>Close</button>
                </div>
                <div class="assistant-history-list" data-assistant-history-list>
                    <p>Loading conversations...</p>
                </div>
            </section>

            <div class="assistant-messages" data-assistant-messages>
                <div class="assistant-message assistant-message-bot">
                    <span>
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M8 3h8M6 8h12a3 3 0 0 1 3 3v5a4 4 0 0 1-4 4H7a4 4 0 0 1-4-4v-5a3 3 0 0 1 3-3ZM8.5 13h.01M15.5 13h.01M9 17h6" /></svg>
                        MIS
                    </span>
                    <p>{{ $assistantGreeting }}</p>
                </div>
            </div>
        </div>

        <form class="assistant-form" data-assistant-form>
            <textarea data-assistant-input name="message" rows="3" placeholder="Ask in normal language..." maxlength="4000"></textarea>
            <div class="assistant-form-footer">
                <small data-assistant-status>Read-only. I can find and open records, not change them.</small>
                <button type="submit">Send</button>
            </div>
        </form>
    </section>
</div>
