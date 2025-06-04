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

            $subscription = $this->payment->createSubscription($customerId,);

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


    /**
     * VINDI unit test connection
     */

    #[Route('/api/test-vindi', methods: ['GET'])]
    public function testVindi(): JsonResponse
    {
        try {
            $customer = $this->payment->createCustomer([
                'name' => 'Live Test 300 ',
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
