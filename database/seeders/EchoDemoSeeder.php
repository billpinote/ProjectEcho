<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Models\AuthAccount;
use App\Models\Operator;
use App\Models\User;
use Database\Seeders\Support\EchoDemoAccounts;
use Database\Seeders\Support\EchoDemoDocuments;
use Database\Seeders\Support\EchoDemoProfiles;
use Database\Seeders\Support\EchoDemoSignatures;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Throwable;

class EchoDemoSeeder extends Seeder
{
    public function run(EchoDemoProfiles $profiles, EchoDemoDocuments $documents, EchoDemoSignatures $signatures): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('EchoDemoSeeder is restricted to local and testing environments.');
        }
        $password = config('echo-demo.password');
        if (! is_string($password) || trim($password) === '') {
            throw new RuntimeException('Set ECHO_DEMO_PASSWORD explicitly before running EchoDemoSeeder.');
        }

        try {
            DB::transaction(function () use ($profiles, $documents, $signatures, $password): void {
                $operators = [];
                foreach (EchoDemoAccounts::operators() as $shortName => $name) {
                    $operators[$shortName] = Operator::query()->firstOrCreate(['name' => $name], [
                        'short_name' => $shortName, 'certificate_number' => 'TEST-ONLY-'.$shortName,
                        'email' => strtolower($shortName).'.operator@example.com', 'is_active' => true,
                        'remarks' => EchoDemoProfiles::REMARKS,
                    ]);
                }

                $demoUsers = [];
                foreach (EchoDemoAccounts::users() as $account) {
                    $user = User::query()->where('email', $account['email'])->lockForUpdate()->first();
                    if ($user !== null) {
                        if (! $user->auditLogs()->where('action', 'echo_demo_created')->where('source', 'echo_demo_seeder')->exists()) {
                            $this->command?->warn('Preserved existing non-demo account: '.$account['email']);

                            continue;
                        }
                    } else {
                        if (AuthAccount::query()->where('provider', 'password')->where(fn ($query) => $query
                            ->where('email', $account['email'])->orWhere('identifier', $account['email']))->exists()) {
                            throw new RuntimeException('A demo email is already used by another authentication account. Existing credentials were preserved.');
                        }
                        [$firstName, $lastName] = explode(' ', $account['name'], 2);
                        $user = User::query()->create([
                            'email' => $account['email'], 'username' => $account['email'],
                            'name' => $account['name'], 'display_name' => $account['name'],
                            'first_name' => $firstName, 'last_name' => $lastName,
                            'employee_id' => 'TEST-EMP-'.$account['number'], 'role' => $account['role'],
                            'station' => 'RPUS', 'wiresign' => $account['role'] === UserRole::Atmo ? 'TEST-'.$account['number'] : null,
                            'operator_id' => $account['operator'] === null ? null : $operators[$account['operator']]->id,
                            'password' => Hash::make($password), 'is_active' => true,
                        ]);
                        $user->authAccounts()->create([
                            'provider' => 'password', 'identifier' => $user->email, 'email' => $user->email,
                            'password_hash' => $user->password, 'email_verified_at' => now(),
                        ]);
                        $user->auditLogs()->create([
                            'action' => 'echo_demo_created', 'source' => 'echo_demo_seeder',
                            'description' => 'Fictional development account created by EchoDemoSeeder.',
                        ]);
                    }

                    if ($user->email_verified_at === null) {
                        $user->forceFill(['email_verified_at' => now()])->save();
                    }
                    $demoUsers[$user->email] = [$user, $account];
                }

                $reviewer = $demoUsers['admin1.echo@example.com'][0] ?? null;
                if ($reviewer === null || ! $reviewer->is_active || $reviewer->role !== UserRole::Admin) {
                    throw new RuntimeException('The active seeder-owned demo admin is required to verify demo fixtures.');
                }

                foreach ($demoUsers as [$user, $account]) {
                    $profile = $profiles->create($user, $account['license'], $account['number']);
                    if ($profile !== null) {
                        $documents->attach($user, $profile, $reviewer);
                        $signatures->create($user, $reviewer);
                    }
                }
            });
            $documents->finishRun();
            $signatures->finishRun();
        } catch (Throwable $exception) {
            $documents->cleanUpFailedRun();
            $signatures->cleanUpFailedRun();

            throw $exception;
        }
    }
}
