<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class BillPinoteSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Bill Pinote',
            'email' => 'bill.pinote@gmail.com',
            'username' => 'billpinote',
            'employee_id' => '9445',
            'wiresign' => 'PI',
            'password' => 'Rpc8249*',
            'role' => 'ARTISAN',
            'station' => 'RPUS',
            'is_active' => true,
        ]);
    }
}
