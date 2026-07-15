<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use App\Models\Contract;
use App\Support\VietnameseMoneyWords;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ContractContextProvider implements DocumentContextProvider
{
    public function slug(): string
    {
        return 'contract';
    }

    public function label(): string
    {
        return 'Hợp đồng';
    }

    /**
     * @return list<array{key: string, type: string, label: string}>
     */
    public function keys(): array
    {
        return [
            ['key' => 'contract_code', 'type' => 'string', 'label' => 'Mã hợp đồng'],
            ['key' => 'contract_title', 'type' => 'string', 'label' => 'Tiêu đề hợp đồng'],
            ['key' => 'contract_type_label', 'type' => 'string', 'label' => 'Loại hợp đồng'],
            ['key' => 'client_name', 'type' => 'string', 'label' => 'Tên khách hàng'],
            ['key' => 'total_value', 'type' => 'number', 'label' => 'Tổng giá trị'],
            ['key' => 'total_value_words', 'type' => 'string', 'label' => 'Giá trị bằng chữ'],
            ['key' => 'currency', 'type' => 'string', 'label' => 'Đơn vị tiền tệ'],
            ['key' => 'signed_at', 'type' => 'date', 'label' => 'Ngày ký'],
            ['key' => 'start_date', 'type' => 'date', 'label' => 'Ngày bắt đầu'],
            ['key' => 'end_date', 'type' => 'date', 'label' => 'Ngày kết thúc'],
            ['key' => 'project_name', 'type' => 'string', 'label' => 'Tên dự án'],
            ['key' => 'project_code', 'type' => 'string', 'label' => 'Mã dự án'],
            ['key' => 'tenant_name', 'type' => 'string', 'label' => 'Tên công ty'],
            ['key' => 'today', 'type' => 'date', 'label' => 'Hôm nay'],
            ['key' => 'boq_table_html', 'type' => 'html', 'label' => 'Bảng khối lượng'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Model $subject): array
    {
        /** @var Contract $contract */
        $contract = $subject;
        $contract->loadMissing('project', 'boq.lineItems');

        /** @var \App\Models\Project|null $project */
        $project = $contract->relationLoaded('project') ? $contract->getRelation('project') : null;
        /** @var \App\Models\Boq|null $boq */
        $boq = $contract->relationLoaded('boq') ? $contract->getRelation('boq') : null;
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\BoqLineItem> $boqLineItems */
        $boqLineItems = ($boq && $boq->relationLoaded('lineItems'))
            ? $boq->getRelation('lineItems')->sortBy('code')
            : collect();

        $totalValue = (float) $contract->total_value;

        return [
            'contract_code' => (string) ($contract->code ?? ''),
            'contract_title' => (string) ($contract->title ?? ''),
            'contract_type_label' => $contract->typeLabel(),
            'client_name' => (string) ($contract->client_name ?? ''),
            'total_value' => number_format($totalValue, 2, '.', ','),
            'total_value_words' => VietnameseMoneyWords::toWords($totalValue),
            'currency' => (string) ($contract->currency ?? 'VND'),
            'signed_at' => $contract->signed_at?->format('d/m/Y') ?? '',
            'start_date' => $contract->start_date?->format('d/m/Y') ?? '',
            'end_date' => $contract->end_date?->format('d/m/Y') ?? '',
            'project_name' => (string) ($project->name ?? ''),
            'project_code' => (string) ($project->code ?? ''),
            'tenant_name' => (string) (optional(Auth::user())->tenant->name ?? ''),
            'today' => now()->format('d/m/Y'),
            'boq_table_html' => $this->renderBoqTable($boqLineItems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sample(): array
    {
        return [
            'contract_code' => 'HD-2024-001',
            'contract_title' => 'Hợp đồng thi công dự án Golden Palace',
            'contract_type_label' => 'Thi công',
            'client_name' => 'Công ty TNHH Bất động sản Golden',
            'total_value' => '15,000,000,000',
            'total_value_words' => 'Mười lăm tỷ đồng',
            'currency' => 'VND',
            'signed_at' => '15/01/2024',
            'start_date' => '01/02/2024',
            'end_date' => '31/12/2024',
            'project_name' => 'Golden Palace',
            'project_code' => 'GP-2024',
            'tenant_name' => 'Công ty Xây dựng Zena',
            'today' => now()->format('d/m/Y'),
            'boq_table_html' => $this->sampleBoqTable(),
        ];
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\BoqLineItem> $lineItems
     */
    private function renderBoqTable($lineItems): string
    {
        if ($lineItems->isEmpty()) {
            return '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border:1px solid #ddd;">Chưa có khối lượng</td></tr></table>';
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
        foreach ($lineItems as $item) {
            $quantity = (float) $item->quantity;
            $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : null;
            $amount = $unitPrice !== null ? $quantity * $unitPrice : null;

            $html .= '<tr>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' . $index . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->code) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->name) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . htmlspecialchars((string) $item->unit) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . number_format($quantity, 2) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . ($unitPrice !== null ? number_format($unitPrice, 0, '.', ',') : '-') . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:right;">' . ($amount !== null ? number_format($amount, 0, '.', ',') : '-') . '</td>';
            $html .= '</tr>';

            $index++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function sampleBoqTable(): string
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
            . '<td style="padding:6px;border:1px solid #ddd;">A.01</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Đào đất móng</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">m3</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">500.00</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">150,000</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">75,000,000</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">2</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">A.02</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Thép cốt thép</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">kg</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">10,000.00</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">25,000</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:right;">250,000,000</td>'
            . '</tr>'
            . '</tbody></table>';
    }
}
