<?php

namespace Tests\Feature;

use App\Models\Cog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CogApprovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_cog_can_only_be_approved_once(): void
    {
        $cog = Cog::create([
            'cog_no' => 'IT/26/001',
            'document_date' => '2026-07-07',
            'status' => 'pending_approval',
            'approval_token' => 'token-123',
            'approval_sent_at' => now(),
            'approval_expires_at' => now()->addDays(7),
        ]);

        $cog->items()->create([
            'qty' => 1,
            'full_description' => 'Test item',
            'unit_price' => 1,
            'total_amount' => 1,
        ]);

        $payload = [
            'receiver_name' => 'Receiver',
            'receiver_designation' => 'Storekeeper',
            'receiver_remarks' => 'Accepted',
        ];

        $this->post(route('cogs.approval.approve', $cog->approval_token), $payload)
            ->assertRedirect();

        $cog->refresh();

        $this->assertSame('approved', $cog->status);
        $this->assertNull($cog->approval_token);
        $this->assertNull($cog->approval_expires_at);

        $this->post('/cog/approval/token-123/approve', $payload)
            ->assertNotFound();
    }

    public function test_expired_cog_approval_link_is_rejected(): void
    {
        $cog = Cog::create([
            'cog_no' => 'IT/26/002',
            'document_date' => '2026-07-07',
            'status' => 'pending_approval',
            'approval_token' => 'expired-token',
            'approval_sent_at' => now()->subDays(8),
            'approval_expires_at' => now()->subMinute(),
        ]);

        $cog->items()->create([
            'qty' => 1,
            'full_description' => 'Expired item',
            'unit_price' => 1,
            'total_amount' => 1,
        ]);

        $this->get(route('cogs.approval.show', $cog->approval_token))
            ->assertStatus(410);

        $this->post(route('cogs.approval.approve', $cog->approval_token), [
            'receiver_name' => 'Receiver',
        ])->assertStatus(410);
    }
}
