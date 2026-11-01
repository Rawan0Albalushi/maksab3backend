<?php

namespace Tests\Feature;

use App\Models\Vendor;
use Tests\TestCase;

class TempWidthProbeTest extends TestCase
{
    public function test_widths(): void
    {
        $v = Vendor::whereHas('stores')->orderBy('id')->first();

        foreach (['/vendor-panel/happy-hour', '/vendor-panel/bogo-offer'] as $url) {
            $html = $this->actingAs($v, 'vendor')
                ->withSession(['login_remember_token' => $v->login_remember_token])
                ->get($url)->getContent();

            preg_match('#<thead.*?</thead>#s', $html, $th);
            preg_match('#<tbody.*?</tr>#s', $html, $tb);

            preg_match_all('#<th[^>]*>(.*?)</th>#s', $th[0] ?? '', $heads);
            preg_match_all('#<td[^>]*>(.*?)</td>#s', $tb[0] ?? '', $cells);

            fwrite(STDERR, "\n== $url\n");
            foreach ($heads[1] as $i => $h) {
                $head = trim(preg_replace('/\s+/', ' ', strip_tags($h)));
                $cell = trim(preg_replace('/\s+/', ' ', strip_tags($cells[1][$i] ?? '')));
                // longest unbreakable run: table-nowrap means the cell cannot wrap
                $longest = max(array_map('strlen', preg_split('/\n/', $cell) ?: ['']));
                fwrite(STDERR, sprintf("   %-18s head=%2d  cell=%3d chars  %s\n",
                    $head, strlen($head), strlen($cell), substr($cell, 0, 46)));
            }
        }
        $this->assertTrue(true);
    }
}
