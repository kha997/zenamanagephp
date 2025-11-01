<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Document;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class DocumentManagementTest extends DuskTestCase
{
    use DatabaseMigrations;

    protected $tenant;
    protected $user;
    protected $project;
    protected $document;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'test@example.com',
            'password' => bcrypt('password')
        ]);
        
        $this->project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
            'manager_id' => $this->user->id
        ]);
        
        $this->document = Document::factory()->create([
            'project_id' => $this->project->id,
            'created_by' => $this->user->id
        ]);
    }

    /** @test */
    public function user_can_upload_new_document()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('Upload Document')
                    ->assertPathIs('/documents/create')
                    ->type('name', 'Test Document')
                    ->type('description', 'Test Description')
                    ->select('project_id', $this->project->id)
                    ->select('type', 'pdf')
                    ->attach('file', __DIR__ . '/test-file.pdf')
                    ->press('Upload Document')
                    ->assertPathIs('/documents')
                    ->assertSee('Test Document');
        });
    }

    /** @test */
    public function user_can_view_document_details()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('View')
                    ->assertPathIs('/documents/' . $this->document->id)
                    ->assertSee($this->document->name)
                    ->assertSee($this->document->description);
        });
    }

    /** @test */
    public function user_can_download_document()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('Download')
                    ->assertDownloaded($this->document->name);
        });
    }

    /** @test */
    public function user_can_delete_document()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('Delete')
                    ->acceptDialog()
                    ->assertPathIs('/documents')
                    ->assertDontSee($this->document->name);
        });
    }

    /** @test */
    public function user_can_filter_documents_by_type()
    {
        Document::factory()->create(['type' => 'pdf', 'project_id' => $this->project->id]);
        
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->select('type_filter', 'pdf')
                    ->press('Filter')
                    ->assertSee('pdf');
        });
    }

    /** @test */
    public function user_can_filter_documents_by_project()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->select('project_filter', $this->project->id)
                    ->press('Filter')
                    ->assertSee($this->project->name);
        });
    }

    /** @test */
    public function user_can_search_documents()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->type('search', $this->document->name)
                    ->press('Search')
                    ->assertSee($this->document->name);
        });
    }

    /** @test */
    public function user_can_approve_document()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('Approve')
                    ->assertSee('approved');
        });
    }

    /** @test */
    public function user_can_reject_document()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->clickLink('Reject')
                    ->type('reason', 'Incomplete information')
                    ->press('Reject')
                    ->assertSee('rejected');
        });
    }

    /** @test */
    public function document_page_is_responsive()
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->user)
                    ->visit('/documents')
                    ->resize(375, 667) // Mobile size
                    ->assertSee('Documents')
                    ->resize(1920, 1080) // Desktop size
                    ->assertSee('Documents');
        });
    }
}
