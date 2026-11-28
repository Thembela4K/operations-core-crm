<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Models\UserProfile;
use App\Services\AuditLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * User Information: personal details, contact, addresses, emergency contact and
 * banking information.
 *
 * Each employee owns their record and is the only person who can change it. The
 * business admin and directors may read every record, because they maintain the
 * accounts, but editing someone else's details is refused.
 */
class UserProfileController extends Controller
{
    public function own(Request $request): View
    {
        $user = $request->user();

        return view('profile.information', [
            'subject' => $user,
            'profile' => $user->profileOrCreate(),
            'canEdit' => true,
            'canViewAll' => $user->canViewAllUserProfiles(),
        ]);
    }

    public function updateOwn(Request $request, AuditLogService $audit): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->profileOrCreate();

        $before = $profile->only(['phone', 'personal_email']);
        $profile->update($this->validated($request));
        $audit->record('profile_updated', $profile, "Personal information updated for {$user->name}.", $before, $profile->only(['phone', 'personal_email']));

        return back()->with('success', 'Your information has been saved.');
    }

    public function index(Request $request): View
    {
        $this->authorizeReader($request);

        $users = User::query()
            ->with(['department', 'profile'])
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = $request->string('search')->toString();
                $query->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('department_id'), fn ($query) => $query->where('department_id', $request->integer('department_id')))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('profile.index', [
            'users' => $users,
            'departments' => Department::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, User $user): View
    {
        $actor = $request->user();

        if (! $actor->canViewUserProfile($user)) {
            abort(403);
        }

        return view('profile.information', [
            'subject' => $user,
            'profile' => $user->profileOrCreate(),
            // Read-only unless you are looking at your own record.
            'canEdit' => $actor->canEditUserProfile($user),
            'canViewAll' => $actor->canViewAllUserProfiles(),
        ]);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'alternate_phone' => ['nullable', 'string', 'max:40'],
            'personal_email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:60'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(UserProfile::GENDERS)],
            'nationality' => ['nullable', 'string', 'max:60'],
            'marital_status' => ['nullable', Rule::in(UserProfile::MARITAL_STATUSES)],
            'residential_address' => ['nullable', 'string', 'max:500'],
            'postal_address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:80'],
            'region' => ['nullable', 'string', 'max:80'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:60'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:60'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'bank_branch_code' => ['nullable', 'string', 'max:30'],
            'bank_account_type' => ['nullable', Rule::in(UserProfile::BANK_ACCOUNT_TYPES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function authorizeReader(Request $request): void
    {
        if (! $request->user()->canViewAllUserProfiles()) {
            abort(403);
        }
    }
}
