<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TempDashProbeTest extends TestCase
{
    public function test_one(): void
    {
        $this->ensureMemoryLimit('512M');
        DB::statement('SET SESSION MAX_EXECUTION_TIME = 120000');
        $url = '/'.ltrim((string) getenv('RURL'), '/');
        $admin = Admin::query()->orderBy('id')->firstOrFail();
        $top = [];
        $marks = []; DB::listen(function ($q) use (&$top, &$marks) { $top[] = [$q->time, $q->sql]; $marks[] = [microtime(true), $q->time, $q->sql.'  BINDINGS='.json_encode($q->bindings)]; });

        $t = microtime(true);
        $status = 'THREW'; $note = '';
        try {
            $res = $this->actingAs($admin, 'admin')
                ->withSession(['login_remember_token' => $admin->login_remember_token])
                ->get($url);
            $status = (string) $res->getStatusCode();
            $body = $res->getContent();
            fwrite(STDERR, sprintf("\n  html=%.1fMB  <option>=%d  <tr>=%d  translate_misses_written=%s\n",
                strlen($body)/1048576, substr_count($body, '<option'), substr_count($body, '<tr'),
                filemtime(resource_path('lang/en/messages.php')) > (time() - 300) ? 'YES (messages.php just rewritten)' : 'no'));
        } catch (\Throwable $e) {
            $note = get_class($e).': '.substr(str_replace("\n", ' ', $e->getMessage()), 0, 130);
        }
        $elapsed = microtime(true) - $t;
        usort($top, fn ($a, $b) => $b[0] <=> $a[0]);
        $sum = array_sum(array_column($top, 0));
        $over1s = count(array_filter($top, fn ($q) => $q[0] > 1000));
        fwrite(STDERR, sprintf("\nRESULT %s -> %s in %.2fs (%d q, sql=%.1fs, %d over 1s) %s\n", $url, $status, $elapsed, count($top), $sum/1000, $over1s, $note));
        $gaps = [];
        for ($i = 1; $i < count($marks); $i++) {
            $gap = ($marks[$i][0] - $marks[$i-1][0]) * 1000 - $marks[$i][1];
            $gaps[] = [$gap, $marks[$i-1][2], $marks[$i][2]];
        }
        usort($gaps, fn ($a, $b) => $b[0] <=> $a[0]);
        fwrite(STDERR, "  --- largest PHP gaps between queries ---\n");
        foreach (array_slice($gaps, 0, 5) as $g) {
            fwrite(STDERR, sprintf("   %7.0fms\n     BEFORE: %s\n     AFTER : %s\n", $g[0], substr(preg_replace('/\s+/', ' ', $g[1]), 0, 105), substr(preg_replace('/\s+/', ' ', $g[2]), 0, 105)));
        }
        $this->assertTrue(true);
    }
}
