<?php declare(strict_types=1);

namespace App\Console\Commands\Testing;

use App\Http\Controllers\Web\CrmPageController;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-048 CONCURRENCY-2 test-support command: invokes the REAL
 * CrmPageController::sendQuote() from a genuinely separate OS process. This
 * is the actual production controller method, not a reimplementation of its
 * gate logic.
 */
class OpportunityConcurrencyTestSendQuote extends Command
{
    protected $signature = 'opportunity:concurrency-test-send-quote {quote_id} {actor_id}';

    protected $hidden = true;

    public function handle(CrmPageController $controller): int
    {
        $actor = User::on('mysql')->findOrFail($this->argument('actor_id'));
        Auth::login($actor);

        $controller->sendQuote(new Request(), (string) $this->argument('quote_id'));

        $fresh = Quote::on('mysql')->find($this->argument('quote_id'));
        $status = $fresh !== null ? $fresh->status : 'not-found';

        if ($status === Quote::STATUS_SENT) {
            $this->line('OK SENT');

            return self::SUCCESS;
        }

        $this->line('CONFLICT ' . $status);

        return self::FAILURE;
    }
}
