<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

class S64ProjectTemplateDocumentationCoverageInvariantTest extends TestCase
{
    public function test_s64_first_slice_documentation_stays_narrow_and_canonical(): void
    {
        $coverageDoc = file_get_contents(base_path('docs/audits/2026-04-04-s6-4-project-template-walkthrough-coverage.md'));
        $backlog = file_get_contents(base_path('docs/roadmap/backlog.yaml'));
        $moduleOwnership = file_get_contents(base_path('docs/architecture/module-ownership-ssot.md'));
        $workTemplatesSsot = file_get_contents(base_path('docs/work-templates-ssot.md'));

        $this->assertIsString($coverageDoc);
        $this->assertIsString($backlog);
        $this->assertIsString($moduleOwnership);
        $this->assertIsString($workTemplatesSsot);

        $this->assertStringContainsString('owner anchor: `POST /api/zena/projects/{id}/apply-template`', $coverageDoc);
        $this->assertStringContainsString('cluster: `Work Templates -> Project Apply -> Work Instances`', $coverageDoc);
        $this->assertStringContainsString('workflow chain: `draft -> preview -> publish -> apply -> generated work-instance runtime`', $coverageDoc);

        $this->assertStringContainsString('`GET /api/zena/work-templates`', $coverageDoc);
        $this->assertStringContainsString('`POST /api/zena/work-templates/{id}/preview`', $coverageDoc);
        $this->assertStringContainsString('`POST /api/zena/work-templates/{id}/publish`', $coverageDoc);
        $this->assertStringContainsString('`POST /api/zena/projects/{id}/apply-template`', $coverageDoc);
        $this->assertStringContainsString('`GET /api/zena/projects/{project}/work-instances`', $coverageDoc);
        $this->assertStringContainsString('`GET /api/zena/work-instances`', $coverageDoc);

        $this->assertStringContainsString('canonical screen owner for Work Template / Work Instance UI is `UNKNOWN`', $coverageDoc);
        $this->assertStringContainsString('exact user-facing project-template walkthrough screen path is `UNKNOWN`', $coverageDoc);

        $this->assertStringContainsString('This document does not claim:', $coverageDoc);
        $this->assertStringContainsString('- repo-wide documentation coverage', $coverageDoc);
        $this->assertStringContainsString('- dashboard coverage', $coverageDoc);
        $this->assertStringContainsString('- notification coverage', $coverageDoc);
        $this->assertStringContainsString('- event-record coverage', $coverageDoc);
        $this->assertStringContainsString('- `/api/v1/*` compatibility ownership', $coverageDoc);

        $this->assertStringContainsString('The first `S6.4` proof slice is docs-only and stays anchored on one already-proved canonical `/api/zena/*` project-template walkthrough owner path: `POST /api/zena/projects/{id}/apply-template`.', $backlog);
        $this->assertStringContainsString('| Work Templates | `/api/zena/work-templates` | `App\\Http\\Controllers\\Api\\WorkTemplateController` | `App\\Models\\WorkTemplate` |', $moduleOwnership);
        $this->assertStringContainsString('| Work Instances | `/api/zena/work-instances` | `App\\Http\\Controllers\\Api\\WorkInstanceController` | `App\\Models\\WorkInstance` |', $moduleOwnership);
        $this->assertStringContainsString('UI for WT/WI management and execution.', $workTemplatesSsot);
    }
}
