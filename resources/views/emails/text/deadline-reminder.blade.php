Due Date Reminder

{{ $item['subject'] }}

{{ $item['reminder_note'] }} This reminder is sent only because the assigned department has not submitted a response.

Reference: {{ $item['reference'] }}
Title: {{ $item['title'] }}
Department: {{ $item['department'] }}
Assigned to: {{ $item['owner'] }}
Status: {{ $item['status'] }}
Priority: {{ $item['priority'] }}
{{ $item['due_label'] }}: {{ $item['due_on']->toDateString() }}

@if(! empty($item['portal_url']))
Portal link: {{ $item['portal_url'] }}
@endif
