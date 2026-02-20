<?php declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invitation;
use Illuminate\Console\Command;

class BackfillInvitationTokenHash extends Command
{
    protected $signature = 'invitations:backfill-token-hash {--chunk=500} {--dry-run}';

    protected $description = 'Backfill token_hash and token_version for legacy invitation rows';

    public function handle(): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $baseQuery = Invitation::query()
            ->select(['id', 'token'])
            ->whereNull('token_hash')
            ->whereNotNull('token')
            ->where('token', '!=', '');

        $targetCount = (clone $baseQuery)->count();

        if ($targetCount === 0) {
            $this->info('No legacy invitations require backfill.');

            return Command::SUCCESS;
        }

        if ($dryRun) {
            $this->info("Dry run: {$targetCount} invitations would be updated.");

            return Command::SUCCESS;
        }

        $updatedCount = 0;
        $chunkNumber = 0;

        $baseQuery
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updatedCount, &$chunkNumber): void {
                $chunkNumber++;
                $updatedIds = [];

                foreach ($rows as $row) {
                    $token = (string) ($row->token ?? '');
                    if ($token === '') {
                        continue;
                    }

                    $updated = Invitation::query()
                        ->whereKey($row->id)
                        ->whereNull('token_hash')
                        ->update([
                            'token_hash' => hash('sha256', $token),
                            'token_version' => Invitation::TOKEN_VERSION_HASH_ONLY,
                        ]);

                    if ($updated > 0) {
                        $updatedCount += $updated;
                        $updatedIds[] = (string) $row->id;
                    }
                }

                if ($updatedIds !== []) {
                    $this->line(sprintf(
                        'Chunk %d updated ids: %s',
                        $chunkNumber,
                        implode(',', $updatedIds)
                    ));
                }
            });

        $this->info("Updated {$updatedCount} invitation rows.");

        return Command::SUCCESS;
    }
}
