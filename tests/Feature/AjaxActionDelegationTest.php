<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Actions that live in ajax-loaded markup must be bound by DELEGATION.
 *
 * jQuery's `$('.thing').on('click', ...)` attaches to the elements that exist when it runs.
 * Markup fetched afterwards -- an offcanvas body, a re-rendered table -- carries buttons that
 * match the selector and have no handler, so clicking them does nothing at all: no console error,
 * no failed request, just an inert button. That silence is why it is worth a test.
 *
 * A browser test would be the honest way to catch it, and there is no browser test harness here.
 * So this reads the sources instead: for each shared handler class, the binding has to be
 * `$(document).on('click', '.class', ...)` rather than `$('.class').on('click', ...)`.
 */
class AjaxActionDelegationTest extends TestCase
{
    /**
     * Classes used by markup that arrives over ajax.
     *
     * form-alert is the platform's own confirm-then-submit control and is used on every panel,
     * which is why its binding lives in the layouts rather than beside any one screen.
     */
    private const DELEGATED_CLASSES = [
        'form-alert',
        'offcanvas-close',
        'reject-enrollment',
        'approve-enrollment',
        'edit-and-approve',
        'enrollment-detail',
        'promo-visibility-warning',
        'status_change_alert',
    ];

    public function test_shared_click_handlers_are_delegated(): void
    {
        $sources = $this->bladeSources();
        $offenders = [];

        foreach (self::DELEGATED_CLASSES as $class) {
            foreach ($sources as $path => $contents) {
                // $('.thing').on('click' ... -- bound to whatever exists right now.
                $direct = '/\$\(\s*[\'"][^\'"]*\.'.preg_quote($class, '/').'[\'"]\s*\)\s*\.\s*on\s*\(/';

                if (preg_match($direct, $contents)) {
                    $offenders[] = $class.' bound directly in '.$path;
                }
            }
        }

        $this->assertSame([], $offenders, implode("\n", array_merge(
            ['These handlers are bound directly, so the same control is inert once it arrives over ajax.'],
            ['Use $(document).on(\'click\', \'.class\', ...) instead.'],
            $offenders
        )));
    }

    /** And each one is actually bound somewhere, or the control is inert everywhere. */
    public function test_every_delegated_class_has_a_handler(): void
    {
        $blob = implode("\n", $this->bladeSources());
        $missing = [];

        foreach (self::DELEGATED_CLASSES as $class) {
            $delegated = '/\$\(\s*document\s*\)\s*\.\s*on\s*\(\s*[\'"][a-z]+[\'"]\s*,\s*[\'"][^\'"]*\.'
                .preg_quote($class, '/').'/';

            if (! preg_match($delegated, $blob)) {
                $missing[] = $class;
            }
        }

        $this->assertSame([], $missing, 'no delegated handler found for: '.implode(', ', $missing));
    }

    /** @return array<string, string> */
    private function bladeSources(): array
    {
        $roots = [
            resource_path('views/layouts'),
            resource_path('views/partials'),
            resource_path('views/admin-views/promotions'),
            resource_path('views/admin-views/partials'),
        ];

        $sources = [];

        foreach ($roots as $root) {
            if (! is_dir($root)) {
                continue;
            }

            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                    $sources[str_replace(base_path().'/', '', $file->getPathname())] = file_get_contents($file->getPathname());
                }
            }
        }

        return $sources;
    }
}
