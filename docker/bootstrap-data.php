<?php

use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\Currency;
use Illuminate\Support\Facades\Hash;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    echo 'settings='.BusinessSetting::count().PHP_EOL;
    echo 'currencies='.Currency::count().PHP_EOL;
    echo 'landing='.(BusinessSetting::query()->where('key', 'landing_page')->value('value') ?? 'NULL').PHP_EOL;
    echo 'currency_setting='.(BusinessSetting::query()->where('key', 'currency')->value('value') ?? 'NULL').PHP_EOL;
    echo 'admins='.Admin::count().PHP_EOL;
} catch (Throwable $throwable) {
    echo 'DBERR '.$throwable->getMessage().PHP_EOL;
}

$email = getenv('ADMIN_EMAIL') ?: '';
$password = getenv('ADMIN_PASSWORD') ?: '';

try {
    if ($email !== '' && $password !== '' && Admin::count() === 0) {
        Admin::create([
            'f_name' => 'Maksab',
            'l_name' => 'Admin',
            'email' => $email,
            'phone' => '0000000000',
            'password' => Hash::make($password),
            'role_id' => 1,
            'is_logged_in' => 1,
        ]);
        echo "admin_created\n";
    }
} catch (Throwable $throwable) {
    echo 'ADMINERR '.$throwable->getMessage().PHP_EOL;
}
