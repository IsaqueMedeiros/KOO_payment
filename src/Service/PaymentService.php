<?php
namespace App\Service;

use App\Service\VindiClient;
use App\Service\RefundService;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

class PaymentService
{
    private VindiClient $vindi;
    private LoggerInterface $logger;
    private RefundService $refund;
    public function __construct(
        VindiClient $vindi,
        LoggerInterface $logger,
        RefundService $refund

    ) {
        $this->vindi = $vindi;
        $this->logger = $logger;
        $this->refund = $refund;
    }
    public function createCustomer(array $user): int
    {
        try {
            $customer = $this->vindi->customers->create([
                'name' => $user['name'],
                'email' => $user['email'],
            ]);

            if (!is_object($customer)) {
                // Log completo do conteúdo de $customer (string, array, etc)
                $this->logger->error('Resposta inesperada da Vindi (customer): ' . var_export($customer, true), [
                    'raw_response' => $customer,
                ]);
                throw new RuntimeException('Invalid response from Vindi SDK.');
            }

            return (int) $customer->id;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to create customer', [
                'exception' => $e,
                'user' => $user,
            ]);
            throw new RuntimeException('Error creating customer in Vindi.');
        }
    }
    public function verifyCard(int $customerId, array $card): bool
    {
        try {
            $profile = $this->vindi->paymentProfiles->create([
                'holder_name' => $card['holder_name'],
                'card_number' => $card['card_number'],
                'card_expiration' => $card['card_expiration'],
                'card_cvv' => $card['card_cvv'],
                'payment_method_code' => 'credit_card',
                'customer_id' => $customerId

            ]);

            // Armazene o ID do perfil de pagamento para criar assinatura
            $this->lastPaymentProfileId = $profile->id;

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to verify card', [
                'exception' => $e,
                'customer_id' => $customerId,
            ]);
            return false;
        }
    }
    public function createSubscription(int $customerId, bool $verifyMode = true): object
{
    $planId = 512868;

    try {
        $subscription = $this->vindi->subscriptions->create([
            'customer_id' => $customerId,
            'payment_method_code' => 'credit_card',
            'plan_id' => $planId
        ]);

        $chargeId = $subscription->charges[0]->id ?? null;

        $this->logger->info('Charge ID gerado:', ['charge_id' => $chargeId]);

        // ✅ Executa o estorno apenas se estiver no modo de verificação
        if ($verifyMode && $chargeId) {
            $refundResponse = $this->refund->refundCharge($chargeId, true);
            $this->logger->info('Estorno realizado com sucesso', [
                'charge_id' => $chargeId,
                'refund_status' => $refundResponse['status'] ?? 'desconhecido'
            ]);
        }

        return $subscription;

    } catch (\Throwable $e) {
        $this->logger->error('Subscription creation failed', [
            'exception' => $e,
            'customer_id' => $customerId,
        ]);
        throw new RuntimeException('Error creating subscription in Vindi.');
    }
}




}
