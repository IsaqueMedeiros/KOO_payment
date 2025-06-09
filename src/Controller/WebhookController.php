<?php
use App\Service\RefundService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
class WebhookController
{
    private RefundService $refundService;
    private LoggerInterface $logger;

    public function __construct(RefundService $refundService, LoggerInterface $logger)
    {
        $this->refundService = $refundService;
        $this->logger = $logger;
    }

    #[Route('/webhook-listening', name: 'vindi_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload || !isset($payload['event'])) {
            return new JsonResponse(['status' => 'invalid payload'], 400);
        }

        $event = $payload['event'];
        $data  = $payload['data'];

        $this->logger->info("[VINDI] Webhook recebido", [
            'event' => $event,
            'id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        switch ($event) {
            case 'bill.paid':
                // Se quiser estornar automaticamente ao receber confirmação de pagamento
                try {
                    $chargeId = $data['last_charge']['id'] ?? null;

                    if ($chargeId) {
                        $result = $this->refundService->refundCharge($chargeId, true);

                        $this->logger->info('Estorno via webhook concluído com sucesso', [
                            'charge_id' => $chargeId,
                            'refund_response' => $result,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $this->logger->error("Erro ao estornar cobrança via webhook", [
                        'error' => $e->getMessage(),
                    ]);
                }
                break;
                
            case 'bill.failed':
                break;
            case 'bill.refunded':
                break;
            default:
                $this->logger->warning("[VINDI] Evento não tratado: {$event}");
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
