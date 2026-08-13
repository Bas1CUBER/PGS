<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\DeadlineControl;
use App\Models\User;
use App\Models\UserPageAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class UserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['email' => 'admin@trcdoh.ph', 'role' => Role::Admin, 'name' => 'Admin User', 'office' => 'ICT Division'],
            ['email' => 'focal@trcdoh.ph', 'role' => Role::Focal, 'name' => 'Focal User', 'office' => 'Planning'],
            ['email' => 'employee@trcdoh.ph', 'role' => Role::Employee, 'name' => 'Employee User', 'office' => 'Clinical Services'],
        ];

        foreach ($users as $u) {
            $user = User::query()->updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'office' => $u['office'],
                    'password' => Hash::make('password'),
                    'is_active' => true,
                    'email_verified_at' => now(), // internal LAN accounts: no email verification flow
                ],
            );

            UserPageAccess::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'roadmaps' => true,
                    'scorecard' => true,
                    'performance_assessment' => true,
                    'cascading' => true,
                    'governance' => true,
                ],
            );
        }

        foreach (['employee', 'focal'] as $role) {
            DeadlineControl::query()->firstOrCreate(
                ['role' => $role],
                [
                    'enabled' => false,
                    'end_time' => null,
                    'message' => 'Please comply with the submission requirements before the deadline.',
                ],
            );
        }
    }
}
