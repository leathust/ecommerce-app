<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\PaymentAttempt;
use App\Models\Role;
use App\Models\SePayTransaction;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::where('name', 'admin')->firstOrFail());
        $this->actingAs($this->admin);
    }

    public function test_payment_reconciliation_list_and_detail_are_permission_protected_and_mask_account(): void
    {
        $transaction = SePayTransaction::factory()->create([
            'external_transaction_id' => 'ADMIN-1001',
            'account_number' => '123456789',
            'match_status' => 'unmatched',
        ]);

        $this->get(route('admin.payments.reconciliation', ['match_status' => 'unmatched']))
            ->assertOk()
            ->assertSee('ADMIN-1001')
            ->assertSee(route('admin.payments.transactions.show', $transaction), false);

        $this->get(route('admin.payments.transactions.show', $transaction))
            ->assertOk()
            ->assertSee('ADMIN-1001')
            ->assertSee('*****6789')
            ->assertDontSee('123456789');

        $this->actingAs($this->userWithRole('catalog_manager'))
            ->get(route('admin.payments.reconciliation'))
            ->assertForbidden();
    }

    public function test_admin_can_manual_match_transaction_and_route_requires_manage_permission(): void
    {
        [$attempt, $transaction] = $this->fixture();

        $this->post(route('admin.payments.transactions.match', $transaction), [
            'payment_attempt_id' => $attempt->id,
            'reason' => 'Đã đối chiếu sao kê ngân hàng',
        ])->assertRedirect(route('admin.payments.transactions.show', $transaction));

        $this->assertSame('matched', $transaction->fresh()->match_status);
        $this->actingAs($this->userWithRole('catalog_manager'))
            ->post(route('admin.payments.transactions.ignore', $transaction), ['reason' => 'Không xử lý'])
            ->assertForbidden();
    }

    /** @return array{0: PaymentAttempt, 1: SePayTransaction} */
    private function fixture(): array
    {
        $order = Order::factory()->create([
            'status' => 'pending', 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid',
            'subtotal' => 300000, 'shipping_fee' => 0, 'total_amount' => 300000,
        ]);
        $attempt = PaymentAttempt::factory()->for($order)->create([
            'environment' => 'sandbox', 'provider' => 'sepay', 'payment_code' => 'DH'.Str::upper(Str::random(12)),
            'expected_amount' => 300000, 'status' => 'pending', 'bank_code' => 'VCB',
            'bank_account_number' => '123456789', 'expires_at' => now()->addMinutes(10),
        ]);
        $transaction = SePayTransaction::factory()->create([
            'environment' => 'sandbox', 'external_transaction_id' => 'ADMIN-'.Str::upper(Str::random(6)),
            'account_number' => '123456789', 'amount' => 300000, 'direction' => 'in',
            'transfer_content' => $attempt->payment_code, 'raw_data' => ['code' => $attempt->payment_code],
            'match_status' => 'manual_review', 'match_reason' => 'payment_code_not_found',
        ]);
        return [$attempt, $transaction];
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());
        return $user;
    }
}
