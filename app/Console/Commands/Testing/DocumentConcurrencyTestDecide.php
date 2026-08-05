<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Services\DocumentWorkflowService;
use Illuminate\Console\Command;

/**
 * Test-support command: invokes DocumentWorkflowService::decide() from a genuinely
 * separate OS process, so concurrency tests can prove real row-locking behavior
 * instead of simulating it with sequential calls in one PHPUnit process.
 */
class DocumentConcurrencyTestDecide extends Command
{
    protected $signature = 'document:concurrency-test-decide {tenant_id} {document_id} {actor_id} {decision}';

    protected $hidden = true;

    public function handle(DocumentWorkflowService $service): int
    {
        $decision = DocumentDecision::from($this->argument('decision'));

        try {
            $document = $service->decide(
                $this->argument('tenant_id'),
                $this->argument('document_id'),
                $this->argument('actor_id'),
                $decision,
                null
            );
            $this->line('OK ' . $document->status);

            return self::SUCCESS;
        } catch (DocumentWorkflowException $e) {
            $this->line('CONFLICT ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
