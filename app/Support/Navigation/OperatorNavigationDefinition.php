<?php declare(strict_types=1);

namespace App\Support\Navigation;

final class OperatorNavigationDefinition
{
    /**
     * @return OperatorNavItem[]
     */
    public static function items(): array
    {
        return [
            new OperatorNavItem('Hôm nay', 'app.today', 'Tổng quan', 'today'),
            new OperatorNavItem('Bảng điều hành', 'app.dashboard', 'Tổng quan', 'dashboard'),
            new OperatorNavItem('Nhật ký hoạt động', 'operator.activity-feed.index', 'Tổng quan', 'activity-feed'),
            new OperatorNavItem('Tiến độ dự án', 'operator.schedule.index', 'Tổng quan', 'schedule'),
            new OperatorNavItem('CRM', 'operator.crm.index', 'Kinh doanh', 'crm'),
            new OperatorNavItem('Báo cáo kinh doanh', 'operator.crm.reports', 'Kinh doanh', 'crm-reports'),
            new OperatorNavItem('Yêu cầu vật tư', 'operator.material-requests.index', 'Mua sắm', 'material-requests'),
            new OperatorNavItem('Phiếu nhập', 'operator.receipts.index', 'Mua sắm', 'receipts'),
            new OperatorNavItem('Vật tư', 'operator.materials.index', 'Mua sắm', 'materials'),
            new OperatorNavItem('Nhà cung cấp', 'operator.vendors.index', 'Mua sắm', 'vendors'),
            new OperatorNavItem('BOQ', 'operator.boqs.index', 'Thương mại', 'boqs'),
            new OperatorNavItem('Hợp đồng', 'operator.contracts.index', 'Thương mại', 'contracts'),
            new OperatorNavItem('Dự án', 'app.projects', 'Dự án', 'projects'),
            new OperatorNavItem('Công việc', 'app.tasks', 'Dự án', 'tasks'),
            new OperatorNavItem('Khối lượng', 'app.workload.index', 'Dự án', 'workload'),
            new OperatorNavItem('Việc của tôi', 'app.my-work.index', 'Dự án', 'my-work'),
            new OperatorNavItem('Công việc thiết kế', 'operator.design-items.index', 'Dự án', 'design-items'),
            new OperatorNavItem('Lịch', 'app.calendar', 'Dự án', 'calendar'),
            new OperatorNavItem('Nhóm', 'app.team.index', 'Dự án', 'team'),
            new OperatorNavItem('Nhật ký công trường', 'operator.site-diaries.index', 'Công trường', 'site-diaries'),
            new OperatorNavItem('Kiểm định', 'operator.inspections.index', 'Chất lượng', 'inspections'),
            new OperatorNavItem('Tri thức nội bộ', 'operator.knowledge.index', 'Tri thức', 'knowledge'),
            new OperatorNavItem('RFI', 'operator.rfis.index', 'Tài liệu', 'rfis'),
            new OperatorNavItem('Submittals', 'operator.submittals.index', 'Tài liệu', 'submittals'),
            new OperatorNavItem('Change Requests', 'operator.change-requests.index', 'Tài liệu', 'change-requests'),
            new OperatorNavItem('Xuất báo cáo', 'operator.reports.index', 'Hệ thống', 'reports'),
            new OperatorNavItem('Webhooks', 'operator.webhooks.index', 'Hệ thống', 'webhooks'),
            new OperatorNavItem('API Tokens', 'operator.api-tokens.index', 'Hệ thống', 'api-tokens'),
        ];
    }
}
