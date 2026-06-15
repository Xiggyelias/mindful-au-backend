<?php

namespace Tests\Feature;

use App\Models\InstitutionAccount;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PeerCounselorRoleConstraintTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sqlite_user_roles_accept_peer_counselor(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());

        $user = User::factory()->create();

        try {
            UserRole::query()->create([
                'user_id' => $user->id,
                'role' => 'peer_counselor',
                'approved' => true,
            ]);
        } catch (QueryException $exception) {
            $this->fail('peer_counselor should be allowed in user_roles: '.$exception->getMessage());
        }

        $this->assertTrue($user->fresh()->hasRole('peer_counselor'));
    }

    #[Test]
    public function sqlite_institution_accounts_accept_peer_counselor(): void
    {
        $this->assertSame('sqlite', DB::getDriverName());

        try {
            $account = InstitutionAccount::query()->create([
                'email' => 'peer.counselor@example.com',
                'role' => 'peer_counselor',
                'approved' => true,
                'is_active' => true,
                'full_name' => 'Peer Counselor',
                'id_number' => 'PEER001',
            ]);
        } catch (QueryException $exception) {
            $this->fail('peer_counselor should be allowed in institution_accounts: '.$exception->getMessage());
        }

        $this->assertSame('peer_counselor', $account->role);
    }
}
