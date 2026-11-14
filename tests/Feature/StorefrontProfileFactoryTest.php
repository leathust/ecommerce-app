<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontProfileFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_factory_states_are_explicit_and_safe(): void
    {
        $active = User::factory()->customerProfile()->create();
        $inactive = User::factory()->inactiveCustomer()->create();

        $this->assertSame('active', $active->status);
        $this->assertNotNull($active->first_name);
        $this->assertNotNull($active->last_name);
        $this->assertMatchesRegularExpression('/^09\d{8}$/', $active->phone);
        $this->assertSame('inactive', $inactive->status);
        $this->assertTrue($active->roles()->doesntExist());
        $this->assertTrue($inactive->roles()->doesntExist());
    }

    public function test_profile_runbook_documents_security_and_out_of_scope_boundaries(): void
    {
        $docs = file_get_contents(base_path('docs/profile-operations.md'));

        $this->assertStringContainsString('first_name', $docs);
        $this->assertStringContainsString('Không log, flash', $docs);
        $this->assertStringContainsString('Không seed hồ sơ cá nhân', $docs);
        $this->assertStringContainsString('thu hồi mọi phiên', $docs);
        $this->assertStringNotContainsString('GOOGLE_CLIENT_SECRET=', $docs);
    }
}
