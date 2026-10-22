<?php

namespace Tests\Feature;

use App\Mail\ContactReplyMail;
use App\Models\Contact;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ContactFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_contact_form_is_connected_to_backend(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee(route('contact.store'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="message"', false);
    }

    public function test_visitor_can_create_a_contact(): void
    {
        $response = $this->post(route('contact.store'), $this->validPayload());

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('contacts', [
            'email' => 'mira@example.com',
            'reason' => 'order',
            'order_code' => 'SPR-204418',
            'status' => 'new',
        ]);
    }

    public function test_contact_validation_and_honeypot_reject_invalid_submissions(): void
    {
        $this->from(route('contact'))->post(route('contact.store'), [
            'email' => 'not-an-email',
            'website' => 'spam.example',
        ])->assertRedirect(route('contact'))
            ->assertSessionHasErrors(['first_name', 'last_name', 'email', 'reason', 'message', 'website']);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_contact_submission_route_is_rate_limited(): void
    {
        $this->assertContains(
            'throttle:contact-submissions',
            Route::getRoutes()->getByName('contact.store')->gatherMiddleware(),
        );
    }

    public function test_support_user_can_view_search_and_filter_contacts(): void
    {
        Contact::create($this->contactAttributes([
            'first_name' => 'Mira',
            'email' => 'mira@example.com',
            'status' => 'new',
            'reason' => 'order',
            'message' => '<script>alert("xss")</script>',
        ]));
        Contact::create($this->contactAttributes([
            'first_name' => 'Alex',
            'email' => 'alex@example.com',
            'status' => 'resolved',
            'reason' => 'warranty',
        ]));

        $user = $this->userWithRole('customer_support');

        $this->actingAs($user)
            ->get(route('admin.contacts.index', ['q' => 'Mira', 'status' => 'new']))
            ->assertOk()
            ->assertSee('mira@example.com')
            ->assertDontSee('alex@example.com')
            ->assertSee('&lt;script&gt;', false)
            ->assertDontSee('<script>alert("xss")</script>', false);
    }

    public function test_contacts_are_paginated(): void
    {
        for ($index = 1; $index <= 16; $index++) {
            Contact::create($this->contactAttributes([
                'email' => "contact{$index}@example.com",
            ]));
        }

        $user = $this->userWithRole('customer_support');

        $this->actingAs($user)
            ->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertViewHas('contacts', fn ($contacts) => $contacts->total() === 16 && $contacts->count() === 15);
    }

    public function test_contact_permissions_control_menu_list_and_status_update(): void
    {
        $contact = Contact::create($this->contactAttributes());
        $support = $this->userWithRole('customer_support');

        $this->actingAs($support)
            ->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertSee(route('admin.contacts.index'), false);

        $this->actingAs($support)
            ->patch(route('admin.contacts.status.update', $contact), ['status' => 'resolved'])
            ->assertRedirect();

        $this->assertDatabaseHas('contacts', ['id' => $contact->id, 'status' => 'resolved']);

        $orderManager = $this->userWithRole('order_manager');
        $this->actingAs($orderManager)
            ->get(route('admin.contacts.index'))
            ->assertForbidden();
    }

    public function test_view_only_contact_role_cannot_update_status(): void
    {
        $role = Role::where('name', 'customer_support')->firstOrFail();
        $role->permissions()->sync([
            Permission::where('name', 'contacts.view')->firstOrFail()->id,
        ]);

        $user = User::factory()->create();
        $user->roles()->attach($role);
        $contact = Contact::create($this->contactAttributes());

        $this->actingAs($user)
            ->get(route('admin.contacts.index'))
            ->assertOk()
            ->assertDontSee(route('admin.contacts.status.update', $contact), false);

        $this->actingAs($user)
            ->patch(route('admin.contacts.status.update', $contact), ['status' => 'closed'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Mira',
            'last_name' => 'Kapoor',
            'email' => 'mira@example.com',
            'phone' => '+1 415 555 0123',
            'reason' => 'order',
            'order_code' => 'SPR-204418',
            'message' => 'Please help me track my order.',
            'website' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function contactAttributes(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'phone' => null,
            'reason' => 'other',
            'order_code' => null,
            'message' => 'Test contact message.',
            'status' => 'new',
        ], $overrides);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $roleName)->firstOrFail());

        return $user;
    }

    public function test_support_can_reply_to_contact_email_from_database(): void
    {
        Mail::fake();
        $contact = Contact::create($this->contactAttributes());
        $user = $this->userWithRole('customer_support');

        $this->actingAs($user)->from(route('admin.contacts.index'))
            ->post(route('admin.contacts.reply', $contact), [
                'subject' => 'Support reply',
                'reply_message' => 'We have received your request.',
                'email' => 'tampered@example.com',
            ])->assertRedirect(route('admin.contacts.index'))->assertSessionHas('success');

        Mail::assertSent(ContactReplyMail::class, fn ($mail) => $mail->hasTo($contact->email)
            && $mail->replySubject === 'Support reply'
            && $mail->replyMessage === 'We have received your request.'
        );
    }

    public function test_reply_requires_permission_and_valid_content(): void
    {
        Mail::fake();
        $contact = Contact::create($this->contactAttributes());
        $this->actingAs($this->userWithRole('order_manager'))
            ->post(route('admin.contacts.reply', $contact), [
                'subject' => 'Reply', 'reply_message' => 'Hello',
            ])->assertForbidden();
        $this->actingAs($this->userWithRole('customer_support'))
            ->post(route('admin.contacts.reply', $contact), [])
            ->assertSessionHasErrors(['subject', 'reply_message']);
        Mail::assertNothingSent();
    }
}
