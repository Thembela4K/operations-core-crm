<div
    class="assistant-shell"
    data-assistant
    data-assistant-message-url="{{ route('assistant.message') }}"
    data-assistant-new-url="{{ route('assistant.conversation') }}"
    data-assistant-suggestions-url="{{ route('assistant.suggestions') }}"
    data-assistant-history-url="{{ route('assistant.history') }}"
    hidden
>
    <button class="assistant-backdrop" type="button" data-assistant-close aria-label="Close MIS"></button>

    <section class="assistant-drawer" role="dialog" aria-modal="true" aria-label="Ask MIS">
        <header class="assistant-header">
            <div class="assistant-title">
                <span>Ask MIS</span>
                <strong>Operations helpdesk</strong>
                <p>Find records, open filtered pages, check deadlines, and review document trails.</p>
            </div>
            <div class="assistant-header-actions">
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
            <div class="assistant-messages" data-assistant-messages>
                <div class="assistant-message assistant-message-bot">
                    <span>MIS</span>
                    <p>Tell me what you want to find. I can open filtered CRM pages for tenders, quotation requests, requisitions, documents, invoices, tasks, approvals, and notifications.</p>
                </div>
            </div>

            <section class="assistant-suggestion-panel" aria-label="Suggested MIS prompts">
                <div class="assistant-panel-heading">
                    <span>Suggested actions</span>
                    <small>Use these when you want a quick result.</small>
                </div>
                <div class="assistant-suggestions" data-assistant-suggestions>
                    <button type="button" data-assistant-prompt="Show overdue tender proposals">Overdue tenders</button>
                    <button type="button" data-assistant-prompt="Show quotation requests due next 5 days">Due soon requests</button>
                    <button type="button" data-assistant-prompt="Show last month submitted tender documents">Submitted documents</button>
                    <button type="button" data-assistant-prompt="Open unpaid invoices">Unpaid invoices</button>
                </div>
            </section>
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
