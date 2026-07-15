<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use App\Models\PaymentCertificate;
use App\Services\PaymentCertificateSummaryService;
use App\Support\VietnameseMoneyWords;
use Illuminate\Database\Eloquent\Model;

class CertificateContextProvider implements DocumentContextProvider
{
    public function __construct(
        private readonly ContractContextProvider $contractProvider,
        private readonly PaymentCertificateSummaryService $summaryService,
    ) {
    }

    public function slug(): string
    {
        return 'certificate';
    }

    public function label(): string
    {
        return 'Chứng chỉ nghiệm thu';
    }

    /**
     * @return list<array{key: string, type: string, label: string}>
     */
    public function keys(): array
    {
        $contractKeys = $this->contractProvider->keys();

        $certificateKeys = [
            ['key' => 'period_no', 'type' => 'string', 'label' => 'Kỳ'],
            ['key' => 'period_from', 'type' => 'date', 'label' => 'Từ ngày'],
            ['key' => 'period_to', 'type' => 'date', 'label' => 'Đến ngày'],
            ['key' => 'total_this_period', 'type' => 'number', 'label' => 'Tổng kỳ này'],
            ['key' => 'retention_amount', 'type' => 'number', 'label' => 'Tiền giữ lại'],
            ['key' => 'advance_deduction', 'type' => 'number', 'label' => 'Trừ tạm ứng'],
            ['key' => 'net_payable', 'type' => 'number', 'label' => 'Thực nhận'],
            ['key' => 'net_payable_words', 'type' => 'string', 'label' => 'Thực nhận bằng chữ'],
            ['key' => 'approved_at', 'type' => 'date', 'label' => 'Ngày duyệt'],
            ['key' => 'lines_table_html', 'type' => 'html', 'label' => 'Bảng hạng mục'],
        ];

        return array_merge($contractKeys, $certificateKeys);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Model $subject): array
    {
        /** @var PaymentCertificate $certificate */
        $certificate = $subject;
        $certificate->loadMissing('contract', 'lines.boqLineItem');

        $contractContext = $this->contractProvider->build($certificate->contract);

        $lineSummaries = $this->summaryService->lineSummaries($certificate);
        $linesTableHtml = $this->renderLinesTable($certificate, $lineSummaries);

        $netPayable = (float) $certificate->net_payable;

        return array_merge($contractContext, [
            'period_no' => (string) $certificate->period_no,
            'period_from' => $certificate->period_from?->format('d/m/Y') ?? '',
            'period_to' => $certificate->period_to?->format('d/m/Y') ?? '',
            'total_this_period' => number_format((float) $certificate->total_this_period, 2, '.', ','),
            'retention_amount' => number_format((float) $certificate->retention_amount, 2, '.', ','),
            'advance_deduction' => number_format((float) $certificate->advance_deduction, 2, '.', ','),
            'net_payable' => number_format($netPayable, 2, '.', ','),
            'net_payable_words' => VietnameseMoneyWords::toWords($netPayable),
            'approved_at' => $certificate->approved_at?->format('d/m/Y') ?? '',
            'lines_table_html' => $linesTableHtml,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function sample(): array
    {
        $contractSample = $this->contractProvider->sample();

        return array_merge($contractSample, [
            'period_no' => '3',
            'period_from' => '01/06/2024',
            'period_to' => '30/06/2024',
            'total_this_period' => '2,500,000,000',
            'retention_amount' => '250,000,000',
            'advance_deduction' => '125,000,000',
            'net_payable' => '2,125,000,000',
            'net_payable_words' => 'Hai tỷ một trăm hai mươi lăm triệu đồng',
            'approved_at' => '05/07/2024',
            'lines_table_html' => $this->sampleLinesTable(),
        ]);
    }

    /**
     * @param array<string, array{contract_qty: float, unit_price: float|null, prev_qty: float, this_qty: float, remaining_qty: float, percent_done: float, over_quantity: bool, amount_this_period: float}> $lineSummaries
     */
    private function renderLinesTable(PaymentCertificate $certificate, array $lineSummaries): string
    {
        if (empty($lineSummaries)) {
            return '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border:1px solid #ddd;">Chưa có hạng mục</td></tr></table>';
        }

        // Build a map of boq_line_item_id => name from the loaded lines
        /** @var array<string, string> $itemNameMap */
        $itemNameMap = [];
        /** @var \App\Models\PaymentCertificateLine $line */
        foreach ($certificate->lines as $line) {
            /** @var \App\Models\BoqLineItem|null $boqLineItem */
            $boqLineItem = $line->relationLoaded('boqLineItem') ? $line->getRelation('boqLineItem') : null;
            if ($boqLineItem) {
                $itemNameMap[(string) $line->boq_line_item_id] = (string) $boqLineItem->name;
            }
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">SL HĐ</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn giá</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Lũy kế</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Kỳ này</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Thành tiền</th>';
        $html .= '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Tiến độ %</th>';
        $html .= '</tr></thead><tbody>';

        $index = 1;
        foreach ($lineSummaries as $boqLineItemId => $summary) {
            $amountThisPeriod = $summary['amount_this_period'];
            $itemName = $itemNameMap[$boqLineItemId] ?? 'Hạng mục #' . $index;

            $html .= '<tr>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:center;">' . $index . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;">' . htmlspecialchars($itemName) . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . number_format($summary['contract_qty'], 2) . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . ($summary['unit_price'] !== null ? number_format($summary['unit_price'], 0, '.', ',') : '-') . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . number_format($summary['prev_qty'] + $summary['this_qty'], 2) . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . number_format($summary['this_qty'], 2) . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . number_format($amountThisPeriod, 0, '.', ',') . '</td>';
            $html .= '<td style="padding:4px;border:1px solid #ddd;text-align:right;">' . number_format($summary['percent_done'], 1) . '%</td>';
            $html .= '</tr>';

            $index++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function sampleLinesTable(): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:11px;">'
            . '<thead><tr>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">SL HĐ</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn giá</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Lũy kế</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Kỳ này</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Thành tiền</th>'
            . '<th style="padding:6px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Tiến độ %</th>'
            . '</tr></thead><tbody>'
            . '<tr>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:center;">1</td>'
            . '<td style="padding:4px;border:1px solid #ddd;">Đào đất móng</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">500.00</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">150,000</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">300.00</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">200.00</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">30,000,000</td>'
            . '<td style="padding:4px;border:1px solid #ddd;text-align:right;">100.0%</td>'
            . '</tr>'
            . '</tbody></table>';
    }
}
