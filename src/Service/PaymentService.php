<?php
namespace App\Service;

use App\Service\VindiClient;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class PaymentService
{
    private VindiClient $vindi;
    private LoggerInterface $logger;
    private string $planId;
    private array $productIds;

    public function __construct(
        VindiClient $vindi,
        LoggerInterface $logger,
        string $planId,
        string $basicProductId,
        string $premiumProductId,
        string $exclusiveProductId
    ) {
        $this->vindi      = $vindi;
        $this->logger     = $logger;
        $this->planId     = $planId;
        $this->productIds = [
            'basic'     => $basicProductId,
            'premium'   => $premiumProductId,
            'exclusive' => $exclusiveProductId,
        ];
    }

    /**
     * Create customer in Vindi
     */
    public function createCustomer(array $user): int
    {
        try {
            $customer = $this->vindi->customers->create([
                'name'  => $user['name'],
                'email' => $user['email'],
            ]);

            return (int) $customer->id;
        } catch (Throwable $e) {
            $this->logger->error('Failed to create customer', [
                'exception' => $e,
                'user'      => $user,
            ]);
            throw new RuntimeException('Error creating customer in Vindi.');
        }
    }

    /**
     * Verify card it with a small auth R$1,00
     */
    public function verifyCard(int $customerId, array $card): bool
    {
        try {
            $profile = $this->vindi->paymentProfiles->create([
                'holder_name'           => $card['holder_name'],
                'card_number'           => $card['card_number'],
                'card_expiration_month' => $card['card_expiration_month'],
                'card_expiration_year'  => $card['card_expiration_year'],
                'card_cvv'              => $card['card_cvv'],
                'customer_id'           => $customerId,
            ]);

            $verification = $this->vindi->paymentProfiles->verify($profile->id);

            return isset($verification->status) && $verification->status === 'valid';
        } catch (Throwable $e) {
            $this->logger->error('Card verification failed', [
                'exception'   => $e,
                'customer_id' => $customerId,
                'card'        => $card,
            ]);
            return false;
        }
    }

    /**
     * Create a subscription for the selected product under the plan
     */
    public function createSubscription(int $customerId, string $planKey): object
    {
        if (!isset($this->productIds[$planKey])) {
            throw new RuntimeException("Invalid plan key: $planKey");
        }
        $productId = $this->productIds[$planKey];

        try {
            return $this->vindi->subscriptions->create([
                'plan_id'             => $this->planId,
                'customer_id'         => $customerId,
                'payment_method_code' => 'credit_card',
                'product_items'       => [
                    ['product_id' => $productId],
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Subscription creation failed', [
                'exception'   => $e,
                'customer_id' => $customerId,
                'plan_key'    => $planKey,
            ]);
            throw new RuntimeException('Error creating subscription in Vindi.');
        }
    }

    /**
     * Upgrade or downgrade an existing subscription
     */
    public function updateSubscription(int $subscriptionId, string $newPlanKey): object
    {
        if (!isset($this->productIds[$newPlanKey])) {
            throw new RuntimeException("Invalid plan key: $newPlanKey");
        }
        $productId = $this->productIds[$newPlanKey];

        try {
            return $this->vindi->subscriptions->update($subscriptionId, [
                'plan_id'       => $this->planId,
                'product_items' => [
                    ['product_id' => $productId],
                ],
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Subscription update failed', [
                'exception'       => $e,
                'subscription_id' => $subscriptionId,
                'new_plan_key'    => $newPlanKey,
            ]);
            throw new RuntimeException('Error updating subscription in Vindi.');
        }
    }

    /**
     * Cancel an existing subscription
     */
    public function cancelSubscription(int $subscriptionId): object
    {
        try {
            return $this->vindi->subscriptions->delete($subscriptionId, [
                'cancel_bills' => 'true',
                'comments'     => 'Canceled via API',
            ]);
        } catch (Throwable $e) {
            $this->logger->error('Subscription cancellation failed', [
                'exception'       => $e,
                'subscription_id' => $subscriptionId,
            ]);
            throw new RuntimeException('Error canceling subscription in Vindi.');
        }
    }
}
