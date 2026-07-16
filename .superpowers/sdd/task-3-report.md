## Task 3 Report — 2026-07-16

- Added the `Lịch hẹn` card to `resources/views/crm/opportunity-show.blade.php` using the page's existing card, table, and form patterns.
- Rendered Vietnamese appointment type labels (`Tư vấn`, `Khảo sát`) instead of raw enum values.
- Added scheduled-only row action forms for `Hoàn thành`, `Hủy`, and `Dời lịch`; completed rows render `outcome_notes` and no action endpoints.
- Added render coverage to `tests/Feature/OpportunityAppointmentLifecycleTest.php` for the appointment card title, localized labels, scheduled action routes, completed outcome visibility, and completed-row action absence.
- Verification:
  - `php artisan test tests/Feature/OpportunityAppointmentLifecycleTest.php` ✅
  - `php artisan test tests/Feature/Architecture` ✅
  - `./vendor/bin/phpstan analyse --no-progress --error-format=table` ❌
- PHPStan classification: the remaining 215 errors are confined to the existing `WorkTemplate*` block (`app/Models/WorkTemplate*`, `app/Services/WorkTemplateCrudService.php`, plus dependent `WorkTemplateVersion/Step/Field` references). Per task instruction, that block was not modified.
