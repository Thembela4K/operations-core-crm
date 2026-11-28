<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The registry is split into record attachments and company documents
 * (report-adjacent requirement from the business).
 */
class DocumentRegistrySplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_a_business_admin_can_upload_a_company_document(): void
    {
        $admin = $this->user('Admin', 'admin.docs@example.com', User::ROLE_BUSINESS_ADMIN);

        $this->actingAs($admin)->post(route('documents.company.store'), [
            'category' => Document::CATEGORY_TRADING_LICENSE,
            'title' => 'Trading Licence 2026',
            'document' => UploadedFile::fake()->create('licence.pdf', 80, 'application/pdf'),
        ])->assertRedirect(route('documents.index', ['tab' => 'company']));

        $document = Document::query()->firstOrFail();

        $this->assertTrue($document->is_company_document);
        $this->assertNull($document->documentable_id);
        $this->assertSame(Document::CATEGORY_TRADING_LICENSE, $document->category);
    }

    public function test_a_director_can_upload_a_company_document(): void
    {
        $director = $this->user('Director', 'director.docs@example.com', User::ROLE_DIRECTOR);

        $this->actingAs($director)->post(route('documents.company.store'), [
            'category' => Document::CATEGORY_COMPANY_REGISTRATION,
            'title' => 'Certificate of Incorporation',
            'document' => UploadedFile::fake()->create('registration.pdf', 60, 'application/pdf'),
        ])->assertRedirect();

        $this->assertSame(1, Document::query()->where('is_company_document', true)->count());
    }

    public function test_a_department_user_cannot_upload_a_company_document(): void
    {
        $department = $this->department('MIS Department');
        $staff = $this->user('Staff Member', 'staff.docs@example.com', User::ROLE_DEPARTMENT_USER, $department);

        $this->actingAs($staff)->post(route('documents.company.store'), [
            'category' => Document::CATEGORY_TRADING_LICENSE,
            'title' => 'Should not exist',
            'document' => UploadedFile::fake()->create('licence.pdf', 80, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame(0, Document::query()->count());
    }

    public function test_every_employee_can_download_a_company_document(): void
    {
        $department = $this->department('GIS Department');
        $staff = $this->user('GIS Officer', 'gis.docs@example.com', User::ROLE_DEPARTMENT_USER, $department);

        Storage::disk('local')->put('documents/company/licence.pdf', 'licence contents');

        $document = Document::query()->create([
            'category' => Document::CATEGORY_TRADING_LICENSE,
            'title' => 'Trading Licence 2026',
            'is_company_document' => true,
            'original_name' => 'licence.pdf',
            'stored_name' => 'licence.pdf',
            'path' => 'documents/company/licence.pdf',
            'mime_type' => 'application/pdf',
            'size' => 16,
            'uploaded_by' => $this->user('Admin', 'admin.docs@example.com', User::ROLE_BUSINESS_ADMIN)->id,
        ]);

        // The registry lists it and the file downloads, even though someone else uploaded it.
        $this->actingAs($staff)->get(route('documents.index', ['tab' => 'company']))
            ->assertOk()
            ->assertSee('Trading Licence 2026');

        $this->actingAs($staff)->get(route('documents.download', $document))->assertOk();
    }

    public function test_the_two_tabs_list_only_their_own_documents(): void
    {
        $admin = $this->user('Admin', 'admin.docs@example.com', User::ROLE_BUSINESS_ADMIN);

        $this->actingAs($admin)->post(route('documents.company.store'), [
            'category' => Document::CATEGORY_POLICY,
            'title' => 'Leave Policy',
            'document' => UploadedFile::fake()->create('policy.pdf', 20, 'application/pdf'),
        ]);

        $this->actingAs($admin)->get(route('documents.index', ['tab' => 'company']))
            ->assertOk()
            ->assertSee('Leave Policy');

        $this->actingAs($admin)->get(route('documents.index', ['tab' => 'attachments']))
            ->assertOk()
            ->assertDontSee('Leave Policy');
    }

    public function test_a_department_user_cannot_delete_a_company_document(): void
    {
        $department = $this->department('MIS Department');
        $staff = $this->user('Staff Member', 'staff.docs@example.com', User::ROLE_DEPARTMENT_USER, $department);

        Storage::disk('local')->put('documents/company/policy.pdf', 'contents');

        $document = Document::query()->create([
            'category' => Document::CATEGORY_POLICY,
            'title' => 'Leave Policy',
            'is_company_document' => true,
            'original_name' => 'policy.pdf',
            'stored_name' => 'policy.pdf',
            'path' => 'documents/company/policy.pdf',
            'mime_type' => 'application/pdf',
            'size' => 8,
            'uploaded_by' => $this->user('Admin', 'admin.docs@example.com', User::ROLE_BUSINESS_ADMIN)->id,
        ]);

        $this->actingAs($staff)->delete(route('documents.destroy', $document))->assertForbidden();
        $this->assertSame(1, Document::query()->count());
    }

    private function department(string $name): Department
    {
        return Department::query()->firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => $name, 'is_active' => true],
        );
    }

    private function user(string $name, string $email, string $role, ?Department $department = null): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'role' => $role,
                'department_id' => $department?->id,
                'is_active' => true,
            ],
        );
    }
}
