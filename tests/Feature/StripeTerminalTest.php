<?php

namespace Tests\Feature;

use App\Services\StripeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Stripe\Stripe;
use Stripe\StripeClient;
use Tests\TestCase;

class StripeTerminalTest extends TestCase
{
    public function testExample()
    {
        $response = $this->get('/');
        $response->assertStatus(200);
    }

    public function testStripeCanSimulateAReader()
    {
        $service = new StripeService;
        $service->simulate();
        $this->assertNotNull($service->terminal);
    }

    public function testStripeCanProcessSwipe()
    {
        $service = new StripeService;
        $service->simulate();

        Stripe::setApiKey(config('stripe.key'));
        $stripe = new StripeClient(config('stripe.key'));

        // Create or retrieve customer
        $customers = $stripe->customers->search([
            'query' => 'email:"test@happycog.com"',
        ]);

        if (!$customers->data || empty($customers->data)) {
            $customer = $stripe->customers->create([
                'email' => 'test@happycog.com',
                'name' => 'Happy Cogerson',
                'description' => 'A test customer only',
            ]);
        } else {
            $customer = $customers->data[0];
        }

        $response = $service->swipe($customer->id, 1000, $service->terminal->id);
        $this->assertNotNull($response?->action?->status);
        $this->assertEquals('in_progress', $response?->action?->status);
    }

    public function testStripeSwipeSucceeds()
    {
        $service = new StripeService;
        $service->simulate();

        Stripe::setApiKey(config('stripe.key'));
        $stripe = new StripeClient(config('stripe.key'));

        // Create or retrieve customer
        $customers = $stripe->customers->search([
            'query' => 'email:"test@happycog.com"',
        ]);

        if (!$customers->data || empty($customers->data)) {
            $customer = $stripe->customers->create([
                'email' => 'test@happycog.com',
                'name' => 'Happy Cogerson',
                'description' => 'A test customer only',
            ]);
        } else {
            $customer = $customers->data[0];
        }

        $response = $service->swipe($customer->id, 1000, $service->terminal->id);
        $this->assertNotNull($response?->action?->status);
        $this->assertEquals('in_progress', $response?->action?->status);

        // Let's create the payment
        $payment = $stripe->testHelpers->terminal->readers->presentPaymentMethod($service->terminal->id, []);
        $this->assertNotNull($payment?->action?->status);
        $this->assertEquals('succeeded', $payment->action->status);
        $this->assertEquals('process_payment_intent', $payment->action->type);

        // Now we await for the swipe
        $testIt = true;
        $count = 0;
        $status = $type = '';

        // Since we're long polling, we'll just run this up to 10 times
        // If it gets that far, and fails, then something broke.
        while ($testIt && $count < 10) {
            $response = $service->status($service->terminal->id);
            if ($response !== 'in_progress') {
                $status = $response['status'];
                $type = $response['type'];
                $testIt = false;
            } else {
                $count++;
                sleep(10);
            }
        }

        $this->assertNotEquals('', $status);
        $this->assertEquals('succeeded', $status);
    }
}
