<?php

namespace App\Services\Assistant;

use App\Models\User;

class ConversationResponder
{
    public function respond(User $user, string $message): ?array
    {
        $lower = $this->normalize($message);

        if ($this->hasCrmContext($lower) && ! $this->isDirectConversation($lower)) {
            return null;
        }

        $intent = $this->intent($lower);

        if (! $intent && ! $this->looksConversational($lower)) {
            return null;
        }

        return [
            'intent' => $intent ?? 'conversation',
            'reply' => $this->reply($user, $intent, $lower),
            'action' => null,
            'filters' => [],
        ];
    }

    private function intent(string $lower): ?string
    {
        return match (true) {
            preg_match('/^(hi|hello|hey|good morning|good afternoon|good evening)\b/', $lower) === 1 => 'greeting',
            preg_match('/\b(what(?:\'s| is) today(?:\'s)? date|today(?:\'s)? date|current date|date today|what day is it|which day is it)\b/', $lower) === 1 => 'date',
            preg_match('/\b(current time|what time is it|time now)\b/', $lower) === 1 => 'time',
            preg_match('/\b(thanks|thank you|appreciate it|great thanks|okay thanks)\b/', $lower) === 1 => 'thanks',
            preg_match('/\b(bye|goodbye|see you|later)\b/', $lower) === 1 => 'goodbye',
            preg_match('/\b(who are you|what are you|your name|are you ai|are you a bot)\b/', $lower) === 1 => 'identity',
            preg_match('/\b(chatgpt|chat gpt|openai)\b/', $lower) === 1 => 'chatgpt',
            preg_match('/\b(what can you do|help me|assist me|how can you help)\b/', $lower) === 1 => 'capabilities',
            preg_match('/\b(how are you|how is it going|are you okay|are you ok)\b/', $lower) === 1 => 'status',
            preg_match('/\b(i am bored|i\'m bored|bored|tell me something|random question|make conversation)\b/', $lower) === 1 => 'bored',
            preg_match('/\b(joke|funny|make me laugh)\b/', $lower) === 1 => 'light_chat',
            preg_match('/\b(feel down|sad|depressed|stressed|anxious|not okay|not ok|overwhelmed)\b/', $lower) === 1 => 'wellbeing',
            default => null,
        };
    }

    private function reply(User $user, ?string $intent, string $lower): string
    {
        $name = $this->firstName($user);

        return match ($intent) {
            'greeting' => "Hi {$name}. I can chat briefly, but I am best at helping with the portal. Ask me about clients, suppliers, tenders, quotation requests, invoices, tasks, approvals, documents, or deadlines.",
            'date' => 'Today is '.now()->format('l, F j, Y').'.',
            'time' => 'The current system time is '.now()->format('H:i').' in the '.config('app.timezone').' timezone.',
            'thanks' => "You are welcome, {$name}. When you are ready, ask me to find a record, count something, or check what needs attention in the portal.",
            'goodbye' => "Goodbye, {$name}. I will be here when you need to check work, documents, deadlines, or approvals.",
            'identity' => 'I am MIS, the local operations helper built into this portal. I can have a short conversation, answer simple system questions, count records, search CRM data, and open filtered pages. I do not use an external AI service and I do not change records.',
            'chatgpt' => 'I know of ChatGPT as a general AI assistant. I am different: I run locally inside this portal and focus on Datamatics operations, so I can help with clients, suppliers, tenders, quotations, invoices, tasks, documents, approvals, and deadlines.',
            'capabilities' => $this->capabilitiesReply($user),
            'status' => 'I am running normally and ready to help. The useful thing I can do is turn plain-language requests into CRM answers, counts, searches, and filtered pages.',
            'bored' => "That happens. I can keep things moving: ask me to check overdue work, count suppliers, find last month's tender documents, show unpaid invoices, or tell you what needs approval.",
            'light_chat' => 'I can keep it light, but I will keep us close to work. A clean CRM is basically a quiet inbox: less hunting, fewer forgotten deadlines, and fewer surprise follow-ups.',
            'wellbeing' => "I am sorry you are feeling that way, {$name}. I am not a counsellor, but I can help reduce work pressure by finding urgent tasks, deadlines, approvals, and documents that need attention.",
            default => $this->offTopicReply($lower),
        };
    }

    private function capabilitiesReply(User $user): string
    {
        $visibility = $user->canViewReports()
            ? ' I can also help with company-wide reports, unpaid invoices, pending approvals, and department workload.'
            : ' I will keep answers limited to records your account is allowed to view.';

        return 'You can speak normally. I can answer simple questions, count records, search CRM content, explain workflows, and open filtered pages when the request is clearly about the portal.'.$visibility;
    }

    private function offTopicReply(string $lower): string
    {
        if (str_ends_with($lower, '?')) {
            return 'I can respond briefly, but I am not a general internet assistant. My strongest work is inside this portal. If you want, ask me about clients, finance, operations, suppliers, documents, tasks, approvals, or deadlines.';
        }

        return 'I understand. I will keep the conversation focused on helping you get work done in the portal. Ask me what you want to find, count, check, or open.';
    }

    private function isDirectConversation(string $lower): bool
    {
        return preg_match('/\b(who are you|what are you|your name|are you ai|are you a bot|chatgpt|chat gpt|openai|how are you|i am bored|i\'m bored|tell me something|joke|feel down|sad|stressed|anxious)\b/', $lower) === 1;
    }

    private function looksConversational(string $lower): bool
    {
        return str_ends_with($lower, '?')
            || preg_match('/\b(talk|chat|conversation|bored|joke|funny|hello|hi|thanks|thank you)\b/', $lower) === 1;
    }

    private function hasCrmContext(string $lower): bool
    {
        return preg_match('/\b(client|clients|customer|supplier|suppliers|tender|proposal|quotation|quote|requisition|invoice|payment|expense|finance|task|attendance|clock|document|file|approval|approve|notification|deadline|department|report|dashboard|user|staff)\b/', $lower) === 1;
    }

    private function firstName(User $user): string
    {
        return strtok($user->name, ' ') ?: $user->name;
    }

    private function normalize(string $message): string
    {
        return mb_strtolower(trim($message));
    }
}
