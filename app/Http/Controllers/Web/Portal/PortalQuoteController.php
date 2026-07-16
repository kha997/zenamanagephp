<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Notification;
use App\Models\Quote;
use App\Models\Tenant;
use App\Services\DeliverablePdfExportService;
use App\Services\QuoteLifecycleService;
use App\Support\VietnameseMoneyWords;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class PortalQuoteController extends Controller
{
    private function findOwnedQuote(string $tenantId, string $accountId, string $id): Quote
    {
        /** @var Builder<Quote> $query */
        $query = Quote::query()
            ->join('opportunities', 'opportunities.id', '=', 'quotes.opportunity_id')
            ->where('quotes.tenant_id', $tenantId)
            ->where('opportunities.account_id', $accountId)
            ->where('quotes.status', '!=', Quote::STATUS_DRAFT)
            ->select('quotes.*');

        return $query->findOrFail($id);
    }

    public function show(string $tenantSlug, string $id): View
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $quote = $this->findOwnedQuote((string) $tenant->id, (string) $account->id, $id);
        $quote->load('lines');

        return view('portal.quote', [
            'tenant' => $tenant,
            'quote' => $quote,
            'amountInWords' => VietnameseMoneyWords::toWords((float) $quote->total),
        ]);
    }

    public function accept(string $tenantSlug, string $id): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $quote = $this->findOwnedQuote((string) $tenant->id, (string) $account->id, $id);

        if ($quote->status !== Quote::STATUS_SENT) {
            return back()->withErrors(['action' => 'Báo giá không còn ở trạng thái chờ phản hồi.']);
        }

        try {
            app(QuoteLifecycleService::class)->accept($quote, [
                'actor_account_id' => (string) $account->id,
                'source' => 'portal',
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->notifyCreator($quote, 'Khách đã chấp nhận báo giá');

        return back()->with('success', 'Bạn đã chấp nhận báo giá. Cảm ơn bạn!');
    }

    public function reject(string $tenantSlug, string $id): RedirectResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $quote = $this->findOwnedQuote((string) $tenant->id, (string) $account->id, $id);

        if ($quote->status !== Quote::STATUS_SENT) {
            return back()->withErrors(['action' => 'Báo giá không còn ở trạng thái chờ phản hồi.']);
        }

        $note = request()->input('note');

        try {
            app(QuoteLifecycleService::class)->reject($quote, [
                'actor_account_id' => (string) $account->id,
                'source' => 'portal',
                'note' => $note,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->notifyCreator($quote, 'Khách từ chối báo giá', $note);

        return back()->with('success', 'Đã ghi nhận phản hồi của bạn.');
    }

    public function pdf(string $tenantSlug, string $id, DeliverablePdfExportService $pdfService): SymfonyResponse
    {
        $tenant = Tenant::query()->where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $quote = $this->findOwnedQuote((string) $tenant->id, (string) $account->id, $id);
        $quote->load('opportunity');
        $lines = $quote->lines()->get();
        $opportunity = $quote->opportunity;

        $html = view('crm.quote-pdf', [
            'quote' => $quote,
            'lines' => $lines,
            'account' => $account,
            'opportunity' => $opportunity,
            'amountInWords' => VietnameseMoneyWords::toWords((float) $quote->total),
            'hidePriceNote' => true,
        ])->render();

        try {
            $pdfBytes = $pdfService->render($html);
        } catch (\App\Exceptions\DeliverablePdfExportUnavailableException) {
            return back()->with('error', 'Không thể tạo PDF báo giá vào lúc này.');
        }

        $filename = 'bao-gia-' . $quote->quote_number . '.pdf';

        return response($pdfBytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function notifyCreator(Quote $quote, string $actionLabel, ?string $body = null): void
    {
        try {
            if ($quote->created_by) {
                Notification::query()->create([
                    'tenant_id' => (string) $quote->tenant_id,
                    'user_id' => (string) $quote->created_by,
                    'type' => 'portal_client_action',
                    'title' => $actionLabel . ': ' . $quote->quote_number,
                    'body' => $body,
                    'link_url' => route('operator.crm.quotes.show', $quote->id),
                ]);
            }
        } catch (\Throwable) {
            // Notification failure must not break customer action
        }
    }
}
