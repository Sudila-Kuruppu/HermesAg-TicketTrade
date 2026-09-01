<?php
/**
 * Phase 2 — SettingsTest
 *
 * Verifies the /settings Action renders the theme radios + logout
 * form. Since the Action delegates to the View, this is a thin
 * smoke test: the Action class is autoloadable and the View file
 * contains the required elements.
 */

declare(strict_types=1);

namespace App\Tests\Integration\Phase02\User;

use App\Tests\Integration\Phase02\Fixtures\Fixtures;

class SettingsTest extends Fixtures
{
    public function test_settings_view_has_theme_radios_and_logout(): void
    {
        $view = file_get_contents(APP_ROOT . '/src/User/View/settings.php');
        $this->assertStringContainsString('name="theme"', $view);
        $this->assertStringContainsString('value="light"', $view);
        $this->assertStringContainsString('value="dark"', $view);
        $this->assertStringContainsString('value="system"', $view);
        $this->assertStringContainsString('action="/logout"', $view);
        $this->assertStringContainsString('btn-outline-danger', $view);
        $this->assertStringContainsString('name="csrf_token"', $view);
    }

    public function test_settings_action_class_loads(): void
    {
        $this->assertTrue(class_exists('App\User\Action\SettingsAction'));
        $this->assertTrue(method_exists('App\User\Action\SettingsAction', 'handle'));
        $this->assertTrue(method_exists('App\User\Action\SettingsAction', 'handlePost'));
    }
}
