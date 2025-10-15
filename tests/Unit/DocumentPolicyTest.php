<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\Document;
use App\Policies\DocumentPolicy;
use Tests\TestCase;
use Mockery;

class DocumentPolicyTest extends TestCase
{
    protected $user;
    protected $document;
    protected $policy;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock user
        $this->user = Mockery::mock(User::class)->makePartial();
        $this->user->id = 'test-user-1';
        $this->user->tenant_id = 'test-tenant-1';
        
        // Mock the roles relationship
        $rolesQuery = Mockery::mock(\Illuminate\Database\Eloquent\Relations\BelongsToMany::class);
        $rolesQuery->shouldReceive('whereIn')
            ->with('name', Mockery::any()) // Accept any role array
            ->andReturnSelf();
        $rolesQuery->shouldReceive('exists')
            ->andReturn(true);
        
        $this->user->shouldReceive('roles')
            ->andReturn($rolesQuery);
        
        // Create mock document
        $this->document = new Document();
        $this->document->id = 'test-document-1';
        $this->document->tenant_id = 'test-tenant-1';
        $this->document->created_by = 'test-user-1';
        $this->document->project_id = null; // No project dependency
        
        $this->policy = new DocumentPolicy();
    }

    /** @test */
    public function user_can_view_any_documents_with_tenant()
    {
        $this->assertTrue($this->policy->viewAny($this->user));
    }

    /** @test */
    public function user_cannot_view_any_documents_without_tenant()
    {
        $userWithoutTenant = new User();
        $userWithoutTenant->tenant_id = null;
        
        $this->assertFalse($this->policy->viewAny($userWithoutTenant));
    }

    /** @test */
    public function user_can_view_own_document()
    {
        $this->assertTrue($this->policy->view($this->user, $this->document));
    }

    /** @test */
    public function user_cannot_view_document_from_different_tenant()
    {
        $otherDocument = new Document();
        $otherDocument->tenant_id = 'other-tenant';
        
        $this->assertFalse($this->policy->view($this->user, $otherDocument));
    }

    /** @test */
    public function user_can_create_documents_with_tenant()
    {
        $this->assertTrue($this->policy->create($this->user));
    }

    /** @test */
    public function user_can_update_own_document()
    {
        $this->assertTrue($this->policy->update($this->user, $this->document));
    }

    /** @test */
    public function user_can_delete_own_document()
    {
        $this->assertTrue($this->policy->delete($this->user, $this->document));
    }

    /** @test */
    public function user_can_download_document_they_can_view()
    {
        $this->assertTrue($this->policy->download($this->user, $this->document));
    }

    /** @test */
    public function user_can_share_document_they_can_update()
    {
        $this->assertTrue($this->policy->share($this->user, $this->document));
    }

    /** @test */
    public function user_cannot_access_document_without_tenant()
    {
        $userWithoutTenant = new User();
        $userWithoutTenant->tenant_id = null;
        
        $this->assertFalse($this->policy->view($userWithoutTenant, $this->document));
        $this->assertFalse($this->policy->update($userWithoutTenant, $this->document));
        $this->assertFalse($this->policy->delete($userWithoutTenant, $this->document));
    }
}
