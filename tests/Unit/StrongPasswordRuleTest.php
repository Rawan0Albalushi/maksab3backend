<?php

namespace Tests\Unit;

use App\Rules\StrongPassword;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StrongPasswordRuleTest extends TestCase
{
    private function fails(string $password): bool
    {
        return Validator::make(['password' => $password], ['password' => StrongPassword::rules()])->fails();
    }

    public function test_it_rejects_every_kind_of_blank_or_control_character(): void
    {
        $blank = [
            'space' => "Str0ng! Pass",
            'tab' => "Str0ng!\tPass",
            'newline' => "Str0ng!\nPass",
            'carriage return' => "Str0ng!\rPass",
            'vertical tab' => "Str0ng!\x0BPass",
            'form feed' => "Str0ng!\x0CPass",
            'backspace' => "Str0ng!\x08Pass",
            'null byte' => "Str0ng!\x00Pass",
            'delete' => "Str0ng!\x7FPass",
            'non-breaking space' => "Str0ng!\u{00A0}Pass",
            'zero-width space' => "Str0ng!\u{200B}Pass",
            'ideographic space' => "Str0ng!\u{3000}Pass",
            'right-to-left override' => "Str0ng!\u{202E}Pass",
            'invalid utf-8' => "Str0ng!\xC3\x28Pass",
        ];

        foreach ($blank as $label => $password) {
            $this->assertTrue($this->fails($password), "a password containing a $label must be rejected");
        }
    }

    public function test_it_accepts_legitimate_passwords(): void
    {
        foreach (["Str0ng!Pass", "Str0ngé!Pass", "Str0ng!Pass\u{1F600}"] as $password) {
            $this->assertFalse($this->fails($password), "expected [$password] to be accepted");
        }
    }

    public function test_it_enforces_each_strength_requirement(): void
    {
        $expectations = [
            'Ab1!' => 'Minimum characters: 8',
            'str0ng!pass' => 'uppercase and lowercase',
            'STR0NG!PASS' => 'uppercase and lowercase',
            'StrongPass!' => 'numbers',
            'Str0ngPass1' => 'symbols',
        ];

        foreach ($expectations as $password => $expected) {
            $validator = Validator::make(['password' => $password], ['password' => StrongPassword::rules()]);
            $validator->fails();

            $this->assertStringContainsString(
                $expected,
                $validator->errors()->first('password'),
                "[$password] should report the $expected requirement"
            );
        }
    }

    public function test_messages_follow_the_attribute_name(): void
    {
        $validator = Validator::make(
            ['confirm_password' => 'abc'],
            ['confirm_password' => StrongPassword::rules()]
        );
        $validator->fails();

        $this->assertStringContainsString('Minimum characters: 8', $validator->errors()->first('confirm_password'));
    }

    public function test_uncompromised_is_opt_in(): void
    {
        $rules = StrongPassword::rules('nullable', [], true);

        $this->assertSame('nullable', $rules[0]);
        $this->assertInstanceOf(StrongPassword::class, $rules[1]);
    }
}
