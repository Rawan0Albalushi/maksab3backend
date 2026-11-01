<?php

namespace Tests\Feature;

use App\Http\Middleware\AjaxActionResponse;
use App\Library\AjaxResponse;
use App\Models\Admin;
use App\Models\Unit;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AjaxFrameworkTest extends TestCase
{
    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = Admin::find(1);
        $this->assertNotNull($this->admin, 'admin id 1 must exist');
    }

    private function panelPost(string $url, array $payload, array $headers = []): TestResponse
    {
        return $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url('/admin/unit')],
            ])
            ->withHeaders($headers + ['Referer' => url('/admin/unit')])
            ->post($url, $payload);
    }

    private function unitName(): string
    {
        return 'ajaxtest-'.uniqid();
    }

    private function forget(string $unit): void
    {
        Unit::withoutGlobalScopes()->where('unit', $unit)->forceDelete();
    }

    public function test_a_flashed_redirect_answers_json_for_an_ajax_request(): void
    {
        $unit = $this->unitName();

        $response = $this->panelPost('/admin/unit/store', [
            'unit' => [$unit],
            'lang' => ['default'],
        ], [
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertNotEmpty($response->json('message'), 'the flashed Toastr line has to reach the client');

        $this->assertNotNull(
            Unit::withoutGlobalScopes()->where('unit', $unit)->first(),
            'the controller still has to do its work'
        );

        $this->forget($unit);
    }

    public function test_the_same_post_without_the_header_still_redirects(): void
    {
        $unit = $this->unitName();

        $response = $this->panelPost('/admin/unit/store', [
            'unit' => [$unit],
            'lang' => ['default'],
        ]);

        $response->assertRedirect();
        $this->assertNotNull(Unit::withoutGlobalScopes()->where('unit', $unit)->first());

        $this->forget($unit);
    }

    public function test_validation_failure_answers_422_with_a_field_map(): void
    {
        $response = $this->panelPost('/admin/unit/store', [
            'unit' => [''],
            'lang' => ['default'],
        ], [
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJson(['ok' => false]);

        $errors = $response->json('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey('unit.0', $errors, 'the client places each message under its own input');
        $this->assertNotEmpty($response->json('message'));
    }

    public function test_a_delete_answers_json_and_removes_the_record(): void
    {
        $unit = $this->unitName();
        $this->panelPost('/admin/unit/store', ['unit' => [$unit], 'lang' => ['default']]);

        $created = Unit::withoutGlobalScopes()->where('unit', $unit)->first();
        $this->assertNotNull($created);

        $response = $this->actingAs($this->admin, 'admin')
            ->withSession([
                'login_remember_token' => $this->admin->login_remember_token,
                '_previous' => ['url' => url('/admin/unit')],
            ])
            ->withHeaders([
                'Referer' => url('/admin/unit'),
                AjaxActionResponse::HEADER => '1',
                'Accept' => 'application/json',
            ])
            ->delete('/admin/unit/delete/'.$created->id);

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);
        $this->assertNull(Unit::withoutGlobalScopes()->where('unit', $unit)->first());
    }

    public function test_the_toastr_line_is_not_left_in_the_session(): void
    {
        $unit = $this->unitName();

        $this->panelPost('/admin/unit/store', [
            'unit' => [$unit],
            'lang' => ['default'],
        ], [
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
        ]);

        $this->assertEmpty(session('toastr::messages', []));

        $this->forget($unit);
    }

    public function test_a_fragment_request_is_left_as_html(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token])
            ->withHeaders([AjaxActionResponse::FRAGMENT_HEADER => '#unit-list'])
            ->get('/admin/unit');

        $response->assertOk();
        $this->assertStringContainsString('text/html', $response->headers->get('Content-Type'));
    }

    public function test_the_admin_layout_ships_the_layer_after_common_js(): void
    {
        $html = $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token])
            ->get('/admin/unit')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('css/ajax-framework.css', $html);
        $this->assertStringContainsString('window.appAjaxLang', $html);

        $common = strpos($html, 'view-pages/common.js');
        $framework = strpos($html, 'js/ajax-framework.js');

        $this->assertNotFalse($common);
        $this->assertNotFalse($framework);
        $this->assertGreaterThan($common, $framework, 'ajax-framework.js has to load after common.js');
    }

    public function test_the_vendor_layout_ships_the_layer_too(): void
    {
        $vendorId = DB::table('vendors')
            ->where('status', 1)
            ->whereNotNull('login_remember_token')
            ->value('id');

        if (! $vendorId) {
            $this->markTestSkipped('no active vendor with a session token');
        }

        $vendor = Vendor::find($vendorId);

        $html = $this->actingAs($vendor, 'vendor')
            ->withSession(['login_remember_token' => $vendor->login_remember_token])
            ->get('/vendor-panel')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('js/ajax-framework.js', $html);
        $this->assertStringContainsString('window.appAjaxLang', $html);
    }

    public function test_wants_fragment_reads_the_header(): void
    {
        $request = Request::create('/admin/unit');
        $this->assertFalse(AjaxResponse::wantsFragment($request));

        $request->headers->set(AjaxActionResponse::FRAGMENT_HEADER, '#unit-list, #itemCount');
        $this->assertTrue(AjaxResponse::wantsFragment($request));
        $this->assertTrue(AjaxResponse::wantsFragment($request, '#itemCount'));
        $this->assertFalse(AjaxResponse::wantsFragment($request, '#other'));
    }

    public function test_jquery_validate_hands_opted_in_forms_to_the_layer(): void
    {
        $source = file_get_contents(public_path('assets/admin/js/form-validate.js'));

        $handoff = strpos($source, 'window.AppAjax.submit(form');
        $native = strpos($source, 'form.submit()');

        $this->assertNotFalse(
            $handoff,
            'form-validate.js runs jQuery Validate over every .custom-validation form. Its '
            .'submitHandler must hand a data-ajax-form form to AppAjax: Validate preventDefaults '
            .'the submit event and calls the native form.submit(), which fires no jQuery handler, '
            .'so the delegated one in ajax-framework.js never sees it and the screen silently '
            .'stays on the full-reload path.'
        );
        $this->assertNotFalse($native);
        $this->assertLessThan(
            $native,
            $handoff,
            'the AppAjax hand-off has to come before the native form.submit() fallback'
        );
        $this->assertStringContainsString('data-ajax-form', $source);
    }

    public function test_the_category_add_form_opts_in_and_carries_the_validation_class(): void
    {
        $html = $this->actingAs($this->admin, 'admin')
            ->withSession(['login_remember_token' => $this->admin->login_remember_token])
            ->get('/admin/category/add?position=0&module_id=1')
            ->assertOk()
            ->getContent();

        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*\bid="category-add-form"[^>]*>/',
            $html
        );

        preg_match('/<form\b[^>]*\bid="category-add-form"[^>]*>/', $html, $m);
        $tag = $m[0];

        $this->assertStringContainsString('data-ajax-form', $tag);
        $this->assertStringContainsString(
            'custom-validation',
            $tag,
            'it is the combination that used to break: jQuery Validate swallowed the submit'
        );
    }

    public function test_a_200_carrying_errors_is_stamped_not_ok(): void
    {
        Route::middleware('web')->get('/__ajax_errors_probe', fn () => response()->json([
            'errors' => [['code' => 'invalid_upload', 'message' => 'Image upload failed.']],
        ], 200));

        $response = $this->withHeaders([
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
        ])->get('/__ajax_errors_probe');

        $response->assertOk();
        $response->assertJson(['ok' => false, 'type' => 'error']);
    }

    public function test_a_plain_200_is_stamped_ok(): void
    {
        Route::middleware('web')->get('/__ajax_plain_probe', fn () => response()->json(['data' => ['id' => 4]]));

        $response = $this->withHeaders([
            AjaxActionResponse::HEADER => '1',
            'Accept' => 'application/json',
        ])->get('/__ajax_plain_probe');

        $response->assertOk();
        $response->assertJson(['ok' => true, 'type' => 'success']);
    }

    public function test_the_builder_produces_the_envelope_the_client_reads(): void
    {
        $payload = AjaxResponse::success('Saved')
            ->fragment('#unit-list', '<tr></tr>')
            ->fragment('#itemCount', 12)
            ->remove('#unit-row-3')
            ->refresh('#sidebar')
            ->reset()
            ->close('#addUnitModal')
            ->with('id', 7)
            ->toArray();

        $this->assertSame(true, $payload['ok']);
        $this->assertSame('success', $payload['type']);
        $this->assertSame('Saved', $payload['message']);
        $this->assertSame('<tr></tr>', $payload['fragments']['#unit-list']);
        $this->assertSame('12', $payload['fragments']['#itemCount'], 'a count is as valid a fragment as a table body');
        $this->assertSame(['#unit-row-3'], $payload['remove']);
        $this->assertSame(['#sidebar'], $payload['refresh']);
        $this->assertTrue($payload['reset']);
        $this->assertSame('#addUnitModal', $payload['close']);
        $this->assertSame(['id' => 7], $payload['data']);
    }

    public function test_a_refusal_is_not_a_server_error(): void
    {
        $response = AjaxResponse::fail('Still in use')->toResponse(Request::create('/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(
            ['ok' => false, 'type' => 'error', 'message' => 'Still in use'],
            $response->getData(true)
        );
    }

    public function test_invalid_normalises_messages_to_lists(): void
    {
        $payload = AjaxResponse::invalid(['name' => 'Name is required'])->toArray();

        $this->assertSame(['name' => ['Name is required']], $payload['errors']);
        $this->assertFalse($payload['ok']);
    }
}
