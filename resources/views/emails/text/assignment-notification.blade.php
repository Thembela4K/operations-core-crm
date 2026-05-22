New Assignment

{{ $recordType }} {{ $reference }} has been assigned to {{ $assignment->department->name }}.

Reference: {{ $reference }}
Title: {{ $title }}
Assigned to: {{ $assignment->assignee_name }}
Status: {{ $status }}
Priority: {{ $priority }}
{{ $dueLabel }}: {{ $dueDate }}
Documents: {{ $documentCount }} available in the portal

@if($assignment->instructions)
Instructions: {{ $assignment->instructions }}

@endif
@if($importantDates)
Important dates:
@foreach($importantDates as $date)
- {{ $date }}
@endforeach

@endif
@if($documents)
Attached documents:
@foreach($documents as $document)
- {{ $document['name'] }} ({{ $document['category'] }}): {{ $document['download_url'] }}
@endforeach

@endif
Portal link: {{ $portalUrl }}

Document links are protected by portal login. If you are signed out, the link will open the login page first.
