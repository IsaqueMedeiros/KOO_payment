<?php
namespace App\Controller;

use App\Service\PaymentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class SubscriptionController extends AbstractController
{
    private PaymentService $payment;
    private LoggerInterface $logger;

    public function __construct(PaymentService $payment, LoggerInterface $logger)
    {
        $this->payment = $payment;
        $this->logger  = $logger;
    }

    #[Route('/api/subscriptions', methods: ['POST'])]
    public function subscribe(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['user'], $data['card'], $data['plan'])) {
            return $this->json(['error' => 'Missing required fields'], 400);
        }

        try {
            $customerId = $this->payment->createCustomer($data['user']);

            if (! $this->payment->verifyCard($customerId, $data['card'])) {
                return $this->json(['error' => 'Card verification failed'], 400);
            }

            $subscription = $this->payment->createSubscription($customerId, $data['plan']);

            return $this->json([
                'subscription_id' => $subscription->id,
                'status'          => $subscription->status,
            ], 201);

        } catch (\Throwable $e) {
            $this->logger->error('Subscription creation failed', [
                'exception' => $e,
                'payload'   => $data,
            ]);
            return $this->json(['error' => 'Internal server error'], 500);
        }
    }

    #[Route('/api/subscriptions/{id}/upgrade', methods: ['POST'])]
    public function upgrade(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['new_plan'])) {
            return $this->json(['error' => 'Missing new_plan'], 400);
        }

        try {
            $sub = $this->payment->updateSubscription($id, $data['new_plan']);

            return $this->json([
                'subscription_id' => $sub->id,
                'plan_id'         => $sub->plan_id,
                'status'          => $sub->status,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Subscription upgrade failed', [
                'exception' => $e,
                'payload'   => $data,
                'id'        => $id,
            ]);
            return $this->json(['error' => 'Internal server error'], 500);
        }
    }

    #[Route('/api/subscriptions/{id}/downgrade', methods: ['POST'])]
    public function downgrade(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['new_plan'])) {
            return $this->json(['error' => 'Missing new_plan'], 400);
        }

        try {
            $sub = $this->payment->updateSubscription($id, $data['new_plan']);

            return $this->json([
                'subscription_id' => $sub->id,
                'plan_id'         => $sub->plan_id,
                'status'          => $sub->status,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Subscription downgrade failed', [
                'exception' => $e,
                'payload'   => $data,
                'id'        => $id,
            ]);
            return $this->json(['error' => 'Internal server error'], 500);
        }
    }
 
    #[Route('/api/subscriptions/{id}', methods: ['DELETE'])]
    public function cancel(int $id): JsonResponse
    {
        try {
            $sub = $this->payment->cancelSubscription($id);

            return $this->json([
                'subscription_id' => $sub->id,
                'status'          => $sub->status,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Subscription cancellation failed', [
                'exception' => $e,
                'id'        => $id,
            ]);
            return $this->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * VINDI unit test connection
     */

    #[Route('/api/test-vindi', methods: ['GET'])]
    public function testVindi(): JsonResponse
    {
        try {
            $customer = $this->payment->createCustomer([
                'name' => 'Live Test',
                'email' => 'test@yourdomain.com'
            ]);

            return $this->json(['message' => 'Connected to Vindi!', 'customer_id' => $customer]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Vindi connection failed',
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
