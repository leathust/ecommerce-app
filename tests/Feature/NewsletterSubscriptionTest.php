<?php

namespace Tests\Feature;

use App\Models\NewsletterSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class NewsletterSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_a_progressively_enhanced_subscription_form(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('newsletter.subscribe'), false)
            ->assertSee('name="_token"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="consent"', false)
            ->assertSee('name="website"', false)
            ->assertSee('data-newsletter-form', false)
            ->assertDontSee('20%');
    }

    public function test_visitor_can_subscribe_with_normalized_email_and_no_javascript_fallback(): void
    {
        $response = $this->post(route('newsletter.subscribe'), [
            'email' => '  READER@Example.COM ',
            'consent' => '1',
            'website' => '',
        ]);

        $response->assertRedirect(route('home').'#newsletter')
            ->assertSessionHas('newsletter_success');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'reader@example.com',
            'locale' => 'vi',
            'source' => 'home',
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'unsubscribed_at' => null,
        ]);
        $this->assertNotNull(NewsletterSubscriber::sole()->consented_at);
    }

    public function test_duplicate_subscription_is_idempotent_and_does_not_reveal_membership(): void
    {
        NewsletterSubscriber::create([
            'email' => 'reader@example.com',
            'locale' => 'vi',
            'source' => 'home',
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'consented_at' => now()->subDay(),
        ]);

        $first = $this->postJson(route('newsletter.subscribe'), $this->validPayload());
        $second = $this->postJson(route('newsletter.subscribe'), $this->validPayload());

        $first->assertOk()->assertJsonStructure(['message']);
        $second->assertOk()->assertExactJson($first->json());
        $this->assertDatabaseCount('newsletter_subscribers', 1);
    }

    public function test_invalid_email_and_missing_consent_return_safe_json_validation(): void
    {
        $this->postJson(route('newsletter.subscribe'), [
            'email' => 'not-an-email',
            'website' => '',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'consent']);

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_honeypot_rejects_spam_without_persisting_it(): void
    {
        $this->postJson(route('newsletter.subscribe'), $this->validPayload([
            'website' => 'spam.example',
        ]))->assertUnprocessable()->assertJsonValidationErrors('website');

        $this->assertDatabaseCount('newsletter_subscribers', 0);
    }

    public function test_storefront_locale_is_persisted_for_english_subscription(): void
    {
        $this->withSession(['storefront_locale' => 'en'])
            ->postJson(route('newsletter.subscribe'), $this->validPayload([
                'email' => 'english@example.com',
            ]))->assertOk()
            ->assertJsonPath('message', 'Thank you. Your subscription request has been recorded.');

        $this->assertDatabaseHas('newsletter_subscribers', [
            'email' => 'english@example.com',
            'locale' => 'en',
        ]);
    }

    public function test_subscription_route_has_named_rate_limiter_and_throttles_requests(): void
    {
        $this->assertContains(
            'throttle:newsletter-subscriptions',
            Route::getRoutes()->getByName('newsletter.subscribe')->gatherMiddleware(),
        );

        foreach (range(1, 5) as $attempt) {
            $this->postJson(route('newsletter.subscribe'), $this->validPayload())->assertOk();
        }

        $this->postJson(route('newsletter.subscribe'), $this->validPayload())->assertTooManyRequests();
    }

    public function test_subscription_post_uses_web_csrf_middleware_and_form_token(): void
    {
        $route = Route::getRoutes()->getByName('newsletter.subscribe');

        $this->assertContains('web', $route->gatherMiddleware());
        $this->get(route('home'))->assertSee('name="_token"', false);
    }

    public function test_unsubscribe_token_is_stored_one_way_and_can_be_verified(): void
    {
        $subscriber = NewsletterSubscriber::create([
            'email' => 'reader@example.com',
            'locale' => 'vi',
            'source' => 'home',
            'status' => NewsletterSubscriber::STATUS_SUBSCRIBED,
            'consented_at' => now(),
        ]);

        $token = $subscriber->issueUnsubscribeToken();
        $subscriber->refresh();

        $this->assertNotSame($token, $subscriber->getRawOriginal('unsubscribe_token_hash'));
        $this->assertTrue($subscriber->matchesUnsubscribeToken($token));
        $this->assertFalse($subscriber->matchesUnsubscribeToken('invalid-token'));
        $this->assertArrayNotHasKey('unsubscribe_token_hash', $subscriber->toArray());
    }

    /** @param array<string, mixed> $overrides */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'reader@example.com',
            'consent' => '1',
            'website' => '',
        ], $overrides);
    }
}
