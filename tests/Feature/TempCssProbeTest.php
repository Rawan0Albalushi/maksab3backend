<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Tests\TestCase;

class TempCssProbeTest extends TestCase
{
    public function test_css(): void
    {
        $v = Vendor::whereHas('stores')->orderBy('id')->first();
        $html = $this->actingAs($v, 'vendor')
            ->withSession(['login_remember_token' => $v->login_remember_token])
            ->get('/vendor-panel/happy-hour')->getContent();

        foreach (['td.promo-name-cell', 'promo-name-cell"', 'promo-name__text', '.promo-name {'] as $needle) {
            fwrite(STDERR, sprintf("\n  %-22s %s", $needle, substr_count($html, $needle)));
        }
        fwrite(STDERR, "\n");
        $this->assertTrue(true);
    }
}
