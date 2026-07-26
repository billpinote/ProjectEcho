<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\AuthAccount;
use Illuminate\Support\Facades\Hash;

$password = Hash::make('BillPinote!2026');

$user = User::create([
    'name' => 'Bill Pinote',
    'first_name' => 'Bill',
    'last_name' => 'Pinote',
    'display_name' => 'Bill Pinote',
    'email' => 'bill.pinote@gmail.com',
    'username' => 'bill.pinote',
    'role' => 'Artisan',
    'is_active' => 1,
    'password' => $password,
]);

AuthAccount::create([
    'user_id' => $user->id,
    'provider' => 'password',
    'identifier' => 'bill.pinote@gmail.com',
    'password_hash' => $password,
    'email' => 'bill.pinote@gmail.com',
    'email_verified_at' => now(),
]);

echo "Created user {$user->id} for bill.pinote@gmail.com\n";
