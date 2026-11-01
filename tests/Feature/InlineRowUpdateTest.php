<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Category;
use Tests\TestCase;

/**
 * The two in-place row edits on the category lists: the status switch
 * (status-toggle.js) and the priority select (priority-select.js). Both go
 * through StatusToggleResponse, which turns the controller's back() into JSON
 * for a request carrying an inline-update header. A redirect coming back here
 * means the row would navigate the whole page instead of saving in place.
 */
class InlineRowUpdateTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function panel(string $url, array $headers): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url('/admin/category/add?position=0')],
            ])
            ->withHeaders($headers + [
                'Referer' => url('/admin/category/add?position=0'),
                'Accept' => 'application/json',
            ])
            ->get($url);
    }

    public function test_priority_answers_json_and_persists(): void
    {
        $category = Category::withoutGlobalScope('translate')->where('position', 0)->first();
        $original = $category->priority;
        $target = $original === 2 ? 1 : 2;

        $response = $this->panel(
            "/admin/category/update-priority/{$category->id}?priority={$target}",
            ['X-Inline-Update' => '1']
        );

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertSame($target, Category::withoutGlobalScope('translate')->find($category->id)->priority);

        $this->panel("/admin/category/update-priority/{$category->id}?priority={$original}", ['X-Inline-Update' => '1']);
        $this->assertSame($original, Category::withoutGlobalScope('translate')->find($category->id)->priority);
    }

    public function test_status_answers_json_and_persists(): void
    {
        $category = Category::withoutGlobalScope('translate')->where('position', 0)->first();
        $original = $category->status;
        $target = $original ? 0 : 1;

        $response = $this->panel("/admin/category/status/{$category->id}/{$target}", ['X-Status-Toggle' => '1']);

        $response->assertOk();
        $response->assertJson(['ok' => true]);
        $this->assertSame($target, Category::withoutGlobalScope('translate')->find($category->id)->status);

        $this->panel("/admin/category/status/{$category->id}/{$original}", ['X-Status-Toggle' => '1']);
        $this->assertSame($original, Category::withoutGlobalScope('translate')->find($category->id)->status);
    }

    public function test_without_the_header_the_endpoints_still_redirect(): void
    {
        $category = Category::withoutGlobalScope('translate')->where('position', 0)->first();

        $this->panel("/admin/category/update-priority/{$category->id}?priority={$category->priority}", [])
            ->assertRedirect();
        $this->panel("/admin/category/status/{$category->id}/{$category->status}", [])
            ->assertRedirect();
    }

    public function test_no_page_still_submits_a_priority_form(): void
    {
        $offenders = [];

        foreach (['resources/views', 'public/assets/admin/js', 'Modules'] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }

                $body = $this->stripComments(file_get_contents($file->getPathname()));

                if (! str_contains($body, 'priority-select') && ! str_contains($body, 'priority-form')) {
                    continue;
                }

                // A per-page change handler that submits the form navigates the whole
                // page, silently keeping that one screen off the shared ajax layer —
                // the same trap CLAUDE.md documents for status switches.
                if (preg_match('/priority-(?:select|form)(?:(?!priority-).){0,400}?(?:\.submit\(\)|submit\(\))/s', $body)) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders, 'these files still submit a priority form: '.implode(', ', $offenders));
    }

    /** Comments explain the shared layer by naming the old call — don't flag the prose. */
    private function stripComments(string $body): string
    {
        return preg_replace(
            ['~\{\{--.*?--\}\}~s', '~/\*.*?\*/~s', '~^\s*//.*$~m', '~^\s*\*.*$~m'],
            '',
            $body
        );
    }
}
