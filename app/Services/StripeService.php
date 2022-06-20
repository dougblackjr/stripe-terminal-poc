<?php

namespace App\Services;

use Stripe\Customer;
use Stripe\Stripe;
use Stripe\StripeClient;
use Stripe\Terminal\ConnectionToken;
use Stripe\Terminal\Location;

class StripeService
{
    public $locationId;
    public $terminal;
    public $isSimulating = false;
    private $stripeKey;

    public function __construct()
    {
        $this->stripeKey = config('stripe.key');
        Stripe::setApiKey($this->stripeKey);
    }

    public function simulate()
    {
        $stripe = new StripeClient($this->stripeKey);

        $locations = $stripe->terminal->locations->all(['limit' => 3]);

        if (isset($locations->data[0]->id)) {
            $locationId = $locations->data[0]->id;
        } else {
            $location = $stripe->terminal->locations->create([
                'display_name' => 'Simulated Store',
                'address' => [
                    'line1' => '1234 Main Street',
                    'city' => 'Pittsburgh',
                    'state' => 'PA',
                    'country' => 'US',
                    'postal_code' => '15212',
                ],
            ]);

            $locationId = $location->id;
        }

        $this->locationId = $locationId;

        $terminal = $stripe->terminal->readers->create([
            'registration_code' => 'simulated-wpe',
            'location' => $this->locationId,
        ]);

        $this->terminal = $terminal;
        $this->isSimulating = true;
        return $this;
    }

    public function swipe($customerId, $amount, $terminalId)
    {
        Stripe::setApiKey($this->stripeKey);
        $stripe = new StripeClient($this->stripeKey);

        // Create setupIntent
        $intent = $stripe->paymentIntents->create([
            'currency' => 'usd',
            'payment_method_types' => [
                'card_present',
            ],
            'capture_method' => 'manual',
            'amount' => $amount,
            'customer' => $customerId,
        ]);

        // Process the payment
        $response = $stripe->terminal->readers->processPaymentIntent(
            $terminalId,
            [
                'payment_intent' => $intent->id,
            ]
        );

        return $response;
    }

    public function status($terminalId)
    {
        $stripe = new StripeClient($this->stripeKey);
        $response = $stripe->terminal->readers->retrieve($terminalId, []);
        return [
            'status' => $response?->action?->status,
            'type' => $response?->action?->type,
        ];
    }
}
