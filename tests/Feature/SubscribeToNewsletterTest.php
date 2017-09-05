<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Riverskies\LaravelNewsletterSubscription\Jobs\SendNewsletterSubscriptionConfirmation;
use Riverskies\LaravelNewsletterSubscription\NewsletterSubscription;
use Tests\TestCase;

class SubscribeToNewsletterTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    public function people_can_subscribe_to_receive_newsletters()
    {
        Mail::fake();
        $this->withoutExceptionHandling();

        $response = $this->post(
            $this->config('subscribe_url'),
            ['email' => 'john@example.com'],
            ['HTTP_REFERER' => '/test']
        );

        $response->assertRedirect('/test');
        $this->assertDatabaseHas($this->config('table_name'), ['email'=>'john@example.com']);
        $response->assertSessionHas('flash', 'You will receive the latest news at john@example.com');
    }

    /** @test */
    public function people_who_already_subscribed_cannot_create_a_new_subscription()
    {
        Queue::fake();
        factory(NewsletterSubscription::class)->create(['email'=>'john@example.com']);
        $this->assertCount(1, NewsletterSubscription::all());

        $response = $this->post(
            $this->config('subscribe_url'),
            ['email'=>'john@example.com'],
            ['HTTP_REFERER' => '/test']
        );

        $response->assertRedirect('/test');
        $this->assertCount(1, NewsletterSubscription::all());
        $this->assertDatabaseHas($this->config('table_name'), ['email'=>'john@example.com']);
        $response->assertSessionHas('flash', 'You will receive the latest news at john@example.com');
        Queue::assertNotPushed(SendNewsletterSubscriptionConfirmation::class);
    }

    /** @test */
    public function a_confirmation_email_is_queued_to_be_sent_after_each_new_subscription()
    {
        Queue::fake();
        $this->post($this->config('subscribe_url'), ['email'=>'john@example.com']);

        $subscription = NewsletterSubscription::first();

        Queue::assertPushed(SendNewsletterSubscriptionConfirmation::class, function($job) use ($subscription) {
            return $job->subscription->is($subscription);
        });
    }

    /** @test */
    public function the_email_is_required()
    {
        $response = $this->post(
            $this->config('subscribe_url'),
            [],
            ['HTTP_REFERER' => '/test']
        );

        $response->assertRedirect('/test');
        $response->assertSessionHasErrors('email');
        $this->assertEmpty(NewsletterSubscription::all());
    }

    /** @test */
    public function the_email_must_be_a_valid_address()
    {
        $response = $this->post(
            $this->config('subscribe_url'),
            ['email'=>'gibberish'],
            ['HTTP_REFERER' => '/test']
        );

        $response->assertRedirect('/test');
        $response->assertSessionHasErrors('email');
        $this->assertEmpty(NewsletterSubscription::all());
    }
}
