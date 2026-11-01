<?php

namespace Tests\Unit;

use App\Rules\EmailAddress;
use App\Rules\ImageFile;
use App\Rules\PhoneNumber;
use App\Rules\StrongPassword;
use App\Rules\VideoFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ValidationRuleTest extends TestCase
{
    private function fails(string $field, array $rules, mixed $value): bool
    {
        return Validator::make([$field => $value], [$field => $rules])->fails();
    }

    public function test_phone_rule_accepts_real_numbers_and_rejects_malformed_ones(): void
    {
        foreach (['+8801700000000', '01700000000', '+880 17-00 (00)'] as $phone) {
            $this->assertFalse($this->fails('phone', PhoneNumber::rules(), $phone), "[$phone] should be accepted");
        }

        foreach (['0170000', 'abcdefghijk', str_repeat('9', 25), 'A1700000000', str_repeat('9', PhoneNumber::MAX_LENGTH + 1)] as $phone) {
            $this->assertTrue($this->fails('phone', PhoneNumber::rules(), $phone), "[$phone] should be rejected");
        }
    }

    public function test_email_rule_rejects_values_that_are_not_email_addresses(): void
    {
        $this->assertFalse($this->fails('email', EmailAddress::rules(), 'someone@example.com'));

        foreach (['notanemail', 'a@', '@b.com', str_repeat('a', 250).'@example.com'] as $email) {
            $this->assertTrue($this->fails('email', EmailAddress::rules(), $email), "[$email] should be rejected");
        }
    }

    public function test_image_rule_enforces_type_and_size(): void
    {
        $this->assertFalse($this->fails('image', ImageFile::rules(), UploadedFile::fake()->image('a.png')));
        $this->assertTrue($this->fails('image', ImageFile::rules(), UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')));
        $this->assertTrue($this->fails('image', ImageFile::rules(), UploadedFile::fake()->image('big.png')->size(MAX_FILE_SIZE * 1024 + 1)));
    }

    public function test_video_rule_accepts_only_the_configured_formats(): void
    {
        $this->assertFalse($this->fails('video', VideoFile::rules(), UploadedFile::fake()->create('a.mp4', 100, 'video/mp4')));
        $this->assertTrue($this->fails('video', VideoFile::rules(), UploadedFile::fake()->create('a.avi', 100, 'video/x-msvideo')));
    }

    public function test_rules_use_the_shared_constants(): void
    {
        $validator = Validator::make(
            ['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')],
            ['image' => ImageFile::rules()]
        );
        $validator->fails();

        $this->assertNotEmpty($validator->errors()->first('image'));
        $this->assertSame(2, MAX_FILE_SIZE, 'the image rule derives its limit from MAX_FILE_SIZE');
        $this->assertStringContainsString('mp4', VIDEO_FORMAT, 'the video rule derives its formats from VIDEO_FORMAT');
    }

    public function test_bounds_are_the_callers_choice_so_one_rule_covers_every_site(): void
    {
        $unbounded = PhoneNumber::rules('required', max: null);
        $this->assertFalse($this->fails('phone', $unbounded, str_repeat('9', 24)));
        $this->assertTrue($this->fails('phone', $unbounded, '0170000'));

        $narrow = PhoneNumber::rules('required', min: 9, max: 14);
        $this->assertTrue($this->fails('phone', $narrow, str_repeat('9', 15)));
        $this->assertFalse($this->fails('phone', $narrow, '017000000'));

        $this->assertTrue($this->fails('phone', PhoneNumber::rules(), str_repeat('9', PhoneNumber::MAX_LENGTH + 1)));
    }

    public function test_video_rule_defaults_to_the_constant_and_accepts_a_size_override(): void
    {
        $big = UploadedFile::fake()->create('a.mp4', PRODUCT_VIDEO_MAX_FILE_SIZE * 1024 - 100, 'video/mp4');

        $this->assertFalse($this->fails('video', VideoFile::rules(), $big));
        $this->assertTrue($this->fails('video', VideoFile::rules('nullable', 1024), $big));
    }

    public function test_formats_always_come_from_the_constants(): void
    {
        foreach (explode(',', IMAGE_FORMAT_FOR_VALIDATION) as $extension) {
            $this->assertFalse(
                $this->fails('image', ImageFile::rules(), UploadedFile::fake()->image("a.$extension")),
                "IMAGE_FORMAT_FOR_VALIDATION lists .$extension so it must be accepted"
            );
        }

        foreach (['mp4' => 'video/mp4', 'mov' => 'video/quicktime', '3gp' => 'video/3gpp', 'mkv' => 'video/x-matroska'] as $extension => $mime) {
            $this->assertSame(
                str_contains(VIDEO_FORMAT, $extension),
                ! $this->fails('video', VideoFile::rules(), UploadedFile::fake()->create("a.$extension", 10, $mime)),
                "VIDEO_FORMAT decides whether .$extension is accepted"
            );
        }

        $this->assertTrue($this->fails('image', ImageFile::rules(), UploadedFile::fake()->create('a.svg', 10, 'image/svg+xml')));
        $this->assertTrue($this->fails('video', VideoFile::rules(), UploadedFile::fake()->create('a.avi', 10, 'video/x-msvideo')));
    }

    public function test_presence_messages_come_from_the_rule(): void
    {
        $validator = Validator::make(['image' => null], ['image' => ImageFile::rules('required')]);
        $validator->fails();

        $this->assertSame(translate('messages.image_is_required'), $validator->errors()->first('image'));
        $this->assertFalse($this->fails('image', ImageFile::rules('nullable'), null));
    }

    public function test_required_is_enforced_when_the_key_is_absent(): void
    {
        $absent = Validator::make([], ['image' => ImageFile::rules('required')]);

        $this->assertTrue($absent->fails());
        $this->assertSame(translate('messages.image_is_required'), $absent->errors()->first('image'));

        $video = Validator::make([], ['video' => VideoFile::rules('required')]);

        $this->assertTrue($video->fails());
        $this->assertSame(translate('messages.video_is_required'), $video->errors()->first('video'));

        $this->assertFalse(Validator::make([], ['image' => ImageFile::rules('nullable')])->fails());
        $this->assertFalse(Validator::make([], ['video' => VideoFile::rules('nullable')])->fails());
    }

    public function test_presence_accepts_more_than_one_rule(): void
    {
        $rules = ['phone' => PhoneNumber::rules('nullable|required_if:verification_method,phone')];

        $this->assertFalse(Validator::make(['verification_method' => 'email'], $rules)->fails());
        $this->assertFalse(Validator::make(['verification_method' => 'phone', 'phone' => '01700000000'], $rules)->fails());
        $this->assertTrue(Validator::make(['verification_method' => 'phone'], $rules)->fails());
        $this->assertTrue(Validator::make(['verification_method' => 'phone', 'phone' => 'not a phone'], $rules)->fails());
    }

    public function test_an_explicit_null_is_not_a_format_failure(): void
    {
        $this->assertFalse($this->fails('email', EmailAddress::rules('nullable'), null));
        $this->assertFalse($this->fails('phone', PhoneNumber::rules('nullable'), null));
        $this->assertFalse($this->fails('password', StrongPassword::rules('nullable'), null));

        $this->assertTrue($this->fails('email', EmailAddress::rules('required'), null));
        $this->assertTrue($this->fails('password', StrongPassword::rules('required'), null));
    }

    public function test_unique_is_a_parameter_rather_than_an_array_merge(): void
    {
        $rules = PhoneNumber::rules('required', 'delivery_men,phone,7');

        $this->assertSame('required', $rules[0]);
        $this->assertInstanceOf(PhoneNumber::class, $rules[1]);
        $this->assertSame('unique:delivery_men,phone,7', $rules[2]);

        $email = EmailAddress::rules('nullable', 'vendors');
        $this->assertSame('unique:vendors', $email[2]);

        $withObject = EmailAddress::rules('required', \Illuminate\Validation\Rule::unique('users', 'email'));
        $this->assertInstanceOf(\Illuminate\Validation\Rules\Unique::class, $withObject[2]);
    }

    public function test_customer_passwords_only_have_to_be_long_enough(): void
    {
        $this->assertFalse($this->fails('password', StrongPassword::basicRules(), '12345678'));
        $this->assertFalse($this->fails('password', StrongPassword::basicRules(), 'password'));
        $this->assertTrue($this->fails('password', StrongPassword::basicRules(), '123456'));
        $this->assertTrue($this->fails('password', StrongPassword::basicRules(), null));
        $this->assertFalse($this->fails('password', StrongPassword::basicRules('nullable'), null));

        $this->assertTrue($this->fails('password', StrongPassword::rules(), '12345678'));
    }

    public function test_presence_is_the_callers_choice(): void
    {
        $this->assertFalse($this->fails('image', ImageFile::rules('nullable'), null));
        $this->assertTrue($this->fails('image', ImageFile::rules('required'), null));
    }
}
