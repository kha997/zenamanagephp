<?php declare(strict_types=1);

namespace App\Services\DocumentContext;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ProjectContextProvider implements DocumentContextProvider
{
    public function slug(): string
    {
        return 'project';
    }

    public function label(): string
    {
        return 'Dự án';
    }

    /**
     * @return list<array{key: string, type: string, label: string}>
     */
    public function keys(): array
    {
        return [
            ['key' => 'project_name', 'type' => 'string', 'label' => 'Tên dự án'],
            ['key' => 'project_code', 'type' => 'string', 'label' => 'Mã dự án'],
            ['key' => 'project_status', 'type' => 'string', 'label' => 'Trạng thái'],
            ['key' => 'manager_name', 'type' => 'string', 'label' => 'Quản lý dự án'],
            ['key' => 'client_display', 'type' => 'string', 'label' => 'Khách hàng'],
            ['key' => 'tenant_name', 'type' => 'string', 'label' => 'Tên công ty'],
            ['key' => 'today', 'type' => 'date', 'label' => 'Hôm nay'],
            ['key' => 'design_items_table_html', 'type' => 'html', 'label' => 'Bảng hạng mục thiết kế'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function build(Model $subject): array
    {
        /** @var Project $project */
        $project = $subject;
        $project->loadMissing('designItems', 'manager');

        /** @var \App\Models\User|null $manager */
        $manager = $project->relationLoaded('manager') ? $project->getRelation('manager') : null;
        /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\DesignItem> $designItems */
        $designItems = $project->relationLoaded('designItems')
            ? $project->getRelation('designItems')->sortBy('name')
            : collect();

        return [
            'project_name' => (string) ($project->name ?? ''),
            'project_code' => (string) ($project->code ?? ''),
            'project_status' => $this->statusLabel($project->status),
            'manager_name' => $manager ? (string) $manager->name : '',
            'client_display' => (string) ($project->client_name ?? $project->client_display ?? ''),
            'tenant_name' => (string) (optional(Auth::user())->tenant->name ?? ''),
            'today' => now()->format('d/m/Y'),
            'design_items_table_html' => $this->renderDesignItemsTable($designItems),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function sample(): array
    {
        return [
            'project_name' => 'Golden Palace',
            'project_code' => 'GP-2024',
            'project_status' => 'Đang thi công',
            'manager_name' => 'Nguyễn Văn A',
            'client_display' => 'Công ty TNHH Bất động sản Golden',
            'tenant_name' => 'Công ty Xây dựng Zena',
            'today' => now()->format('d/m/Y'),
            'design_items_table_html' => $this->sampleDesignItemsTable(),
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'planning' => 'Lên kế hoạch',
            'in_progress' => 'Đang thi công',
            'on_hold' => 'Tạm dừng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Hủy bỏ',
            default => (string) $status,
        };
    }

    /**
     * @param \Illuminate\Database\Eloquent\Collection<int, \App\Models\DesignItem> $designItems
     */
    private function renderDesignItemsTable($designItems): string
    {
        if ($designItems->isEmpty()) {
            return '<table style="width:100%;border-collapse:collapse;"><tr><td style="padding:8px;border:1px solid #ddd;">Chưa có hạng mục thiết kế</td></tr></table>';
        }

        $html = '<table style="width:100%;border-collapse:collapse;font-size:12px;">';
        $html .= '<thead><tr>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Loại</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Trạng thái</th>';
        $html .= '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:center;">Phiên bản</th>';
        $html .= '</tr></thead><tbody>';

        $index = 1;
        foreach ($designItems as $item) {
            $html .= '<tr>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' . $index . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->name) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->item_type) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;">' . htmlspecialchars((string) $item->review_status) . '</td>';
            $html .= '<td style="padding:6px;border:1px solid #ddd;text-align:center;">' . $item->revision_count . '</td>';
            $html .= '</tr>';

            $index++;
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private function sampleDesignItemsTable(): string
    {
        return '<table style="width:100%;border-collapse:collapse;font-size:12px;">'
            . '<thead><tr>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">STT</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Hạng mục</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Loại</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:left;">Trạng thái</th>'
            . '<th style="padding:8px;border:1px solid #ddd;background:#f3f4f6;text-align:center;">Phiên bản</th>'
            . '</tr></thead><tbody>'
            . '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">1</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Mặt bằng tổng thể</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Sơ bộ</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Đã duyệt</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">3</td>'
            . '</tr>'
            . '<tr>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">2</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Mặt bằng tầng</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Kỹ thuật</td>'
            . '<td style="padding:6px;border:1px solid #ddd;">Đang duyệt</td>'
            . '<td style="padding:6px;border:1px solid #ddd;text-align:center;">2</td>'
            . '</tr>'
            . '</tbody></table>';
    }
}
