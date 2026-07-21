<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * layouts/operator.blade.php không load Alpine.js — mọi directive Alpine
 * trong view render dưới layout này đều chết lặng (menu hiện thường trực,
 * template x-if không bao giờ render). Guard này chặn tái sinh lỗi đó:
 * quét mọi view @extends('layouts.operator') VÀ mọi partial được @include
 * từ chúng (một cấp), fail nếu còn cú pháp Alpine.
 *
 * Nếu test này fail: viết lại bằng vanilla JS (xem resources/js/ai-*.js,
 * resources/js/work-template-apply.js) hoặc dùng <details> native
 * (xem components/ui/template-dropdown.blade.php). ĐỪNG thêm Alpine vào
 * layout operator để "sửa" — đó là quyết định kiến trúc đã chốt.
 */
class OperatorLayoutAlpineGuardTest extends TestCase
{
    private const ALPINE_PATTERN = '/\bx-(data|init|show|if|for|model|text|cloak|transition)\b|@(click|change|input|submit)(\.[a-z]+)*="/';

    public function test_operator_layout_views_and_their_includes_are_alpine_free(): void
    {
        $viewsPath = resource_path('views');
        $operatorViews = [];

        foreach (File::allFiles($viewsPath) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $contents = $file->getContents();
            if (str_contains($contents, "@extends('layouts.operator')")) {
                $operatorViews[$file->getPathname()] = $contents;
            }
        }

        $this->assertNotEmpty($operatorViews, 'Sanity: expected at least one view extending layouts.operator');

        // Mở rộng một cấp @include: partial include từ view operator cũng render dưới layout đó.
        $toScan = $operatorViews;
        foreach ($operatorViews as $contents) {
            preg_match_all("/@include\\(\\s*'([^']+)'/", $contents, $matches);
            foreach ($matches[1] as $viewName) {
                $includePath = $viewsPath . '/' . str_replace('.', '/', $viewName) . '.blade.php';
                if (is_file($includePath) && !isset($toScan[$includePath])) {
                    $toScan[$includePath] = file_get_contents($includePath);
                }
            }
        }

        // Layout operator + mọi component x-ui.* nó dùng cũng thuộc phạm vi.
        $layoutPath = $viewsPath . '/layouts/operator.blade.php';
        $toScan[$layoutPath] = file_get_contents($layoutPath);
        foreach (File::allFiles($viewsPath . '/components/ui') as $file) {
            $toScan[$file->getPathname()] = $file->getContents();
        }

        $violations = [];
        foreach ($toScan as $path => $contents) {
            if (preg_match(self::ALPINE_PATTERN, $contents, $match)) {
                $violations[] = str_replace($viewsPath . '/', '', $path) . ' → "' . $match[0] . '"';
            }
        }

        $this->assertSame([], $violations, "Alpine directives found in operator-layout views (Alpine is NOT loaded there — see this test's docblock):\n" . implode("\n", $violations));
    }
}
