<?php
// src/Service/VindiClient.php
namespace App\Service;

use Vindi\Vindi;
use Vindi\Customer;
use Vindi\Subscription;
use Vindi\PaymentProfile;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class VindiClient
{
    public Customer $customers;
    public Subscription $subscriptions;
    public PaymentProfile $paymentProfiles;

    public function __construct(string $apiKey, string $apiUri, LoggerInterface $logger)
    {
        Vindi::setApiKey($apiKey);
        Vindi::setApiUri($apiUri);

        try {
            $this->customers       = new Customer();
            $this->subscriptions   = new Subscription();
            $this->paymentProfiles = new PaymentProfile();
        } catch (Throwable $e) {
            $logger->critical('Failed to initialize Vindi SDK client', [
                'exception' => $e,
                'api_uri'   => $apiUri
            ]);
            throw new RuntimeException('Failed to initialize Vindi SDK.');
        }
    }
}
