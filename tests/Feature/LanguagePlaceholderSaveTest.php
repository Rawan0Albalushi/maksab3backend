<?php

namespace Tests\Feature;

use App\Models\Admin;
use Tests\TestCase;

class LanguagePlaceholderSaveTest extends TestCase
{
    private const LOCALE = 'en';

    private function asAdmin(): self
    {
        $admin = Admin::find(1);
        $this->assertNotNull($admin, 'admin id 1 must exist');

        return $this->actingAs($admin, 'admin')
            ->withSession(['login_remember_token' => $admin->login_remember_token]);
    }

    private function path(): string
    {
        return base_path('resources/lang/' . self::LOCALE . '/messages.php');
    }

    private function value(string $key)
    {
        return (include $this->path())[$key] ?? null;
    }

    private function placeholderKey(): string
    {
        foreach (array_keys(include $this->path()) as $key) {
            if (preg_match('/:[a-zA-Z_][a-zA-Z0-9_]*/', (string) $key)) {
                return (string) $key;
            }
        }

        $this->fail('no placeholder-bearing key in ' . self::LOCALE . '/messages.php');
    }

    public function test_a_single_save_that_drops_a_placeholder_is_refused(): void
    {
        $key = $this->placeholderKey();
        $before = $this->value($key);

        $this->asAdmin()
            ->post('admin/business-settings/language/translate-submit/' . self::LOCALE, [
                'key' => $key,
                'value' => 'no token in here at all',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);

        $this->assertSame($before, $this->value($key), 'the refused value must not reach the file');
    }

    public function test_a_single_save_that_keeps_the_placeholder_goes_through(): void
    {
        $key = $this->placeholderKey();
        $before = $this->value($key);

        $this->asAdmin()
            ->post('admin/business-settings/language/translate-submit/' . self::LOCALE, [
                'key' => $key,
                'value' => $before,
            ])
            ->assertOk();

        $this->assertSame($before, $this->value($key));
    }

    public function test_a_bulk_save_rejects_only_the_rows_that_lost_a_placeholder(): void
    {
        $key = $this->placeholderKey();
        $before = $this->value($key);

        $intact = null;
        foreach (array_keys(include $this->path()) as $candidate) {
            if (!preg_match('/:[a-zA-Z_][a-zA-Z0-9_]*/', (string) $candidate)) {
                $intact = (string) $candidate;
                break;
            }
        }
        $this->assertNotNull($intact);
        $intactBefore = $this->value($intact);

        $response = $this->asAdmin()
            ->postJson('admin/business-settings/language/translate-bulk-submit/' . self::LOCALE, [
                'translations' => [
                    $key => 'token removed',
                    $intact => $intactBefore,
                ],
            ])
            ->assertOk();

        $response->assertJsonPath('updated', 1);
        $response->assertJsonPath('skipped', 1);
        $this->assertArrayHasKey($key, $response->json('placeholder_errors'));
        $this->assertNotNull($response->json('placeholder_message'));

        $this->assertSame($before, $this->value($key), 'the refused row must not reach the file');
        $this->assertSame($intactBefore, $this->value($intact));
    }

    public function test_the_translate_screen_marks_every_placeholder_in_the_source_column(): void
    {
        $key = $this->placeholderKey();

        $this->asAdmin()
            ->get('admin/business-settings/language/translate/' . self::LOCALE . '?search=' . urlencode($key))
            ->assertOk()
            ->assertSee('lang-tr-ph', false)
            ->assertSee('data-placeholders', false);
    }
}
