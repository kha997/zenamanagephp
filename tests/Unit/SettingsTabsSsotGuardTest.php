<?php declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class SettingsTabsSsotGuardTest extends TestCase
{
    public function test_frontend_settings_tab_routes_match_ssot(): void
    {
        $routesPath = dirname(__DIR__, 2) . '/frontend/src/routes/index.tsx';
        $content = file_get_contents($routesPath);

        $this->assertNotFalse($content, 'Unable to read frontend route file.');

        preg_match_all('/<Route\s+path="(settings\/[^"]+)"/', $content, $matches);

        $actualSettingsPaths = array_values(array_unique($matches[1] ?? []));
        sort($actualSettingsPaths);

        $expectedSettingsPaths = [
            'settings/general',
            'settings/notifications',
            'settings/security',
        ];
        sort($expectedSettingsPaths);

        $this->assertSame(
            $expectedSettingsPaths,
            $actualSettingsPaths,
            'Frontend settings tab routes must stay in sync with Settings tabs SSOT.'
        );
    }
}
