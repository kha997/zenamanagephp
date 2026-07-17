<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use App\Models\Quote;
use App\Support\VietnameseMoneyWords;
use Illuminate\Database\Eloquent\Model;

class QuoteContextProvider implements DocumentContextProvider
{
    public function slug(): string
    {
        return 'quote';
    }

    public function label(): string
    {
        return 'Báo giá';
    }

    /**
     * @return list<array{key: string, type: string, label: string}>
     */
    public function keys(): array
    {
        return [
            ['key' => 'quote_number', 'type' => 'string', 'label' => 'Số báo giá'],
            ['key' => 'revision_no', 'type' => 'string', 'label' => 'Bản chào số'],
            ['key' => 'status_label', 'type' => 'string', 'label' => 'Trạng thái'],
            ['key' => 'account_name', 'type' => 'string', 'label' => 'Tên khách hàng'],
            ['key' => 'opportunity_name', 'type' => 'string', 'label' => 'Tên cơ hội'],
            ['key' => 'valid_until', 'type' => 'date', 'label' => 'Hiệu lực đến'],
            ['key' => 'subtotal', 'type' => 'number', 'label' => 'Tạm tính'],
            ['key' => 'discount_percent', 'type' => 'number', 'label' => 'Chiết khấu (%)'],
            ['key' => 'discount_amount', 'type' => 'number', 'label' => 'Số tiền chiết khấu'],
            ['key' => 'vat_percent', 'type' => 'number', 'label' => 'VAT (%)'],
            ['key' => 'vat_amount', 'type' => 'number', 'label' => 'Số tiền VAT'],
            ['key' => 'total', 'type' => 'number', 'label' => 'Tổng cộng'],
            ['key' => 'total_words', 'type' => 'string', 'label' => 'Tổng bằng chữ'],
            ['key' => 'payment_terms', 'type' => 'string', 'label' => 'Điều khoản thanh toán'],
            ['key' => 'today', 'type' => 'date', 'label' => 'Hôm nay'],
            ['key' => 'lines_table_html', 'type' => 'html', 'label' => 'Bảng dòng báo giá'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Model $subject): array
    {
        /** @var Quote $quote */
        $quote = $subject;
        $quote->loadMissing('lines', 'opportunity.account');

        return [
            'quote_number' => (string) $quote->quote_number,
            'revision_no' => (string) $quote->revision_no,
            'status_label' => $this->statusLabel($quote->status),
            'account_name' => (string) ($quote->opportunity?->account->display_name ?? ''),
            'opportunity_name' => (string) ($quote->opportunity->opportunity_name ?? ''),
            'valid_until' => $quote->valid_until?->format('d/m/Y') ?? '',
            'subtotal' => number_format((float) $quote->subtotal, 2, '.', ','),
            'discount_percent' => number_format((float) $quote->discount_percent, 2, '.', ','),
            'discount_amount' => number_format((float) $quote->discount_amount, 2, '.', ','),
            'vat_percent' => number_format((float) $quote->vat_percent, 2, '.', ','),
            'vat_amount' => number_format((float) $quote->vat_amount, 2, '.', ','),
            'total' => number_format((float) $quote->total, 2, '.', ','),
            'total_words' => VietnameseMoneyWords::toWords((float) $quote->total),
            'payment_terms' => (string) ($quote->payment_terms ?? ''),
            'today' => now()->format('d/m/Y'),
            'lines_table_html' => $this->renderLinesTable($quote->lines),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sample(): array
    {
        return [
            'quote_number' => 'BG-2026-0001',
            'revision_no' => '1',
            'status_label' => 'Đã gửi',
            'account_name' => 'Công ty TNHH Golden',
            'opportunity_name' => 'Cải tạo văn phòng Golden',
            'valid_until' => '31/12/2026',
            'subtotal' => '27,500,000.00',
            'discount_percent' => '10.00',
            'discount_amount' => '2,750,000.00',
            'vat_percent' => '8.00',
            'vat_amount' => '1,980,000.00',
            'total' => '26,730,000.00',
            'total_words' => 'Hai mươi sáu triệu bảy trăm ba mươi nghìn đồng',
            'payment_terms' => '50% tạm ứng, 50% khi bàn giao',
            'today' => now()->format('d/m/Y'),
            'lines_table_html' => $this->sampleLinesTable(),
        ];
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Quote::STATUS_DRAFT => 'Nháp',
            Quote::STATUS_SENT => 'Đã gửi',
            Quote::STATUS_ACCEPTED => 'Đã chấp nhận',
            Quote::STATUS_REJECTED => 'Đã từ chối',
            Quote::STATUS_SUPERSEDED => 'Đã thay thế',
            default => $status,
        };
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteLineItem> $lines
     */
    private function renderLinesTable(\Illuminate\Database\Eloquent\Collection $lines): string
    {
        if ($lines->isEmpty()) {
            return '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border:1px solid #ddd;">Chưa có dòng báo giá</td></tr></table>';
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Mã</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn vị</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Khối lượng</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn giá</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Thành tiền</th>';
        $html .= '</tr></thead><tbody>';

        $index = 1;
        foreach ($lines as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = (float) $item->unit_price;
            $amount = $quantity * $unitPrice;

            $html .= '<tr>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' . $index . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->code) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->name) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . htmlspecialchars((string) $item->unit) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format($quantity, 2) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format($unitPrice, 0, '.', ',') . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format($amount, 0, '.', ',') . '</td>';
            $html .= '</tr>';

            $index++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function sampleLinesTable(): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Mã</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn vị</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Khối lượng</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Đơn giá</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:right;">Thành tiền</th>'
            . '</tr></thead><tbody>'
            . '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">1</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">L001</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Sơn hoa văn</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">m2</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">100.00</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">200,000</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">20,000,000</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">2</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">L002</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Keo dán</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">kg</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">5.00</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">1,500,000</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">7,500,000</td>'
            . '</tr>'
            . '</tbody></table>';
    }
}
