<?php

namespace Tests\Feature;

use Tests\TestCase;

class BundleSettingsPageTest extends TestCase
{
    private string $page = 'resources/views/admin-views/business-settings/settings/order-index.blade.php';

    private string $card = 'resources/views/admin-views/business-settings/settings/partials/_product-bundle.blade.php';

    private function page(): string
    {
        return file_get_contents(base_path($this->page));
    }

    public function test_the_toggle_script_runs_where_jquery_exists(): void
    {
        $source = $this->page();

        $scriptTwo = strpos($source, "@push('script_2')");
        $handler = strpos($source, "\$('#product_bundle_status').on('change'");

        $this->assertNotFalse($handler, 'The toggle handler is missing.');
        $this->assertNotFalse($scriptTwo);
        $this->assertGreaterThan(
            $scriptTwo,
            $handler,
            'jQuery ships in vendor.min.js, which loads after @stack(\'script\') — the handler must live in script_2.',
        );

        $ready = strpos($source, '$(document).ready(function () {');
        $this->assertNotFalse($ready);
        $this->assertGreaterThan($ready, $handler, 'The handler must be inside the ready block.');
    }

    public function test_the_toggle_shows_and_hides_the_module_list(): void
    {
        $source = $this->page();

        $this->assertStringContainsString("\$('#product_bundle_options').slideDown();", $source);
        $this->assertStringContainsString("\$('#product_bundle_options').slideUp();", $source);
        $this->assertStringContainsString("if (\$('#product_bundle_status').is(':checked'))", $source, 'The initial state must be applied on load, not just on change.');
    }

    public function test_the_card_sits_inside_the_form_that_saves_it(): void
    {
        $source = $this->page();

        $formOpen = strpos($source, "route('admin.business-settings.update-order')");
        $include = strpos($source, '_product-bundle');
        $formClose = strpos($source, '</form>');

        $this->assertNotFalse($include, 'The card is not included.');
        $this->assertGreaterThan($formOpen, $include);
        $this->assertLessThan($formClose, $include, 'Outside the form the checkboxes would never submit.');
    }

    public function test_the_inputs_do_not_collide_with_the_extra_packaging_inputs(): void
    {
        $card = file_get_contents(base_path($this->card));

        $this->assertStringContainsString('name="product_bundle_modules[', $card);
        $this->assertStringNotContainsString('name="grocery"', $card, 'Extra Packaging already posts bare module-type names on this form.');
        $this->assertStringNotContainsString('name="food"', $card);
    }

    public function test_the_section_is_reachable_from_the_page_quick_nav(): void
    {
        $source = $this->page();

        $this->assertStringContainsString('href="#product_bundle_section"', $source);
        $this->assertStringContainsString('id="product_bundle_guide"', $source);
    }

    public function test_the_card_follows_the_house_markup(): void
    {
        $card = file_get_contents(base_path($this->card));

        $this->assertStringContainsString('p-xxl-20 p-3 shadow-sm bg-white rounded mb-20', $card, 'Same card shell as the sibling sections.');
        $this->assertStringContainsString('toggle-switch h--45px toggle-switch-sm', $card, 'Same toggle as the sibling sections.');
        $this->assertStringContainsString('col-lg-3 col-sm-6', $card, 'Same checkbox grid as Extra Packaging.');
        $this->assertStringNotContainsString('@php', $card, 'No @php in Blade.');
    }
}
