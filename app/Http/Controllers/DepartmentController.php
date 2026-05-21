<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DepartmentController extends Controller
{
    public function index(): View
    {
        return view('admin.departments.index', [
            'departments' => Department::query()
                ->withCount(['users', 'assignments'])
                ->orderBy('name')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('admin.departments.create', ['department' => new Department(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Department::query()->create($this->validated($request));

        return redirect()->route('departments.index')->with('success', 'Department created.');
    }

    public function show(Department $department): RedirectResponse
    {
        return redirect()->route('departments.edit', $department);
    }

    public function edit(Department $department): View
    {
        return view('admin.departments.edit', ['department' => $department]);
    }

    public function update(Request $request, Department $department): RedirectResponse
    {
        $department->update($this->validated($request, $department));

        return redirect()->route('departments.index')->with('success', 'Department updated.');
    }

    public function destroy(Department $department): RedirectResponse
    {
        if ($department->users()->exists() || $department->assignments()->exists()) {
            return back()->with('warning', 'Departments with users or assignments should be disabled instead of deleted.');
        }

        $department->delete();

        return redirect()->route('departments.index')->with('success', 'Department deleted.');
    }

    private function validated(Request $request, ?Department $department = null): array
    {
        $slug = Str::slug($request->string('name')->toString());
        $request->merge(['slug' => $slug]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('departments', 'name')->ignore($department)],
            'slug' => [Rule::unique('departments', 'slug')->ignore($department)],
            'email' => ['nullable', 'email', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'email' => $data['email'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }
}
