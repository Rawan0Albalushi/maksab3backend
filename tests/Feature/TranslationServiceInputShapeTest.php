<?php

namespace Tests\Feature;

use App\Models\StoreCategory;
use App\Models\Translation;
use App\Services\System\TranslationService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * addOrUpdate() reached for $request->lang, but its callers pass three different shapes: a Request
 * from the panels, a cast stdClass from StoreCategoryService, and a plain array from the services
 * typed `array|Request $data`. An array hit "Attempt to read property lang on array", which the
 * catch swallowed — translations were silently never written, and the only trace was a `line___16`
 * info line naming nothing.
 */
class TranslationServiceInputShapeTest extends TestCase
{
    use DatabaseTransactions;

    private ?StoreCategory $category = null;

    private array $payload = [
        'lang' => ['default', 'bn'],
        'name' => ['Base Name', 'Bangla Name'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = StoreCategory::withoutGlobalScopes()->first();

        if (! $this->category) {
            $this->markTestSkipped('dataset has no store category');
        }
    }

    private function save(mixed $input): bool
    {
        Translation::where('translationable_type', StoreCategory::class)
            ->where('translationable_id', $this->category->id)
            ->where('locale', 'bn')
            ->delete();

        return app(TranslationService::class)->addOrUpdate(
            $input,
            'name',
            'name',
            StoreCategory::class,
            $this->category->id,
            $this->category->getRawOriginal('name'),
            true,
        );
    }

    private function stored(): ?string
    {
        return Translation::where('translationable_type', StoreCategory::class)
            ->where('translationable_id', $this->category->id)
            ->where('locale', 'bn')
            ->value('value');
    }

    public function test_a_plain_array_is_accepted(): void
    {
        $this->assertTrue($this->save($this->payload));
        $this->assertSame('Bangla Name', $this->stored(),
            'the services typed array|Request pass an array, and it must write a translation');
    }

    public function test_a_cast_object_is_accepted(): void
    {
        $this->assertTrue($this->save((object) $this->payload));
        $this->assertSame('Bangla Name', $this->stored());
    }

    public function test_a_request_is_accepted(): void
    {
        $this->assertTrue($this->save(new Request($this->payload)));
        $this->assertSame('Bangla Name', $this->stored());
    }

    public function test_a_payload_carrying_no_locales_is_refused_rather_than_thrown(): void
    {
        $this->assertFalse($this->save(['name' => ['Base Name']]));
        $this->assertNull($this->stored());
    }

    public function test_a_locale_with_no_value_writes_nothing_for_that_locale(): void
    {
        $this->assertTrue($this->save(['lang' => ['default', 'bn'], 'name' => ['Base Name', '']]));
        $this->assertNull($this->stored(), 'an empty box must not store an empty translation');
    }

    public function test_no_debug_info_logging_remains(): void
    {
        $source = file_get_contents(base_path('app/Services/System/TranslationService.php'));

        $this->assertStringNotContainsString('line___', $source,
            'a swallowed failure has to name the model and field, not a bare line number');
        $this->assertStringNotContainsString('info(', $source);
    }
}
