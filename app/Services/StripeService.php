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
            'setup_future_usage' => 'off_session',
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
        $intent = $response?->action?->process_payment_intent?->payment_intent;

        return [
            'status' => $response?->action?->status,
            'type' => $response?->action?->type,
            'intent' => $intent,
            'response' => $response,
        ];
    }

    public function retrieveCustomerPaymentMethods($id)
    {
        $stripe = new StripeClient($this->stripeKey);
        $response = $stripe->customers->allPaymentMethods(
            $id,
            [
                'type' => 'card',
            ]
        );
        return $response;
    }

    public function makeCustomerPaymentMethodDefault($customerId, $intentId)
    {
        $stripe = new StripeClient($this->stripeKey);

        $intent = $this->getPaymentIntentById($intentId);

        if (!$intent) {
            return 'Intent not found';
        }

        if (!$intent->payment_method) {
            return 'Payment method not found';
        }
        // dd(json_encode($stripe->customers->allPaymentMethods($customerId, ['type' => 'card'])), $customerId, $intent->payment_method);
        $customer = $stripe->customers->update($customerId, ['default_source' => $intent->payment_method,]);

        // dd($customer);

        return $response;
    }

    private function getPaymentIntentById($id)
    {
        $stripe = new StripeClient($this->stripeKey);
        $intent = $stripe->paymentIntents->retrieve($id);
        return $intent;
    }
}
