<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class WebhookController
{
    #[Route('/webhook/vindi', name: 'vindi_webhook', methods: ['POST'])]
    public function handleWebhook(Request $request, LoggerInterface $logger): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);

        if (!$payload || !isset($payload['event'])) {
            return new JsonResponse(['status' => 'invalid payload'], 400);
        }

        $event = $payload['event'];
        $data  = $payload['data'];

        $logger->info("[VINDI] Webhook recebido", [
            'event' => $event,
            'id' => $data['id'] ?? null,
            'status' => $data['status'] ?? null,
        ]);

        // 👉 Aja com base no evento
        switch ($event) {
            case 'bill.paid':
                // Ex: Marcar no banco que a fatura foi paga
                // $this->pedidoService->confirmarPagamento($data['id']);
                break;

            case 'bill.failed':
                // Ex: Notificar cliente ou reemitir cobrança
                break;

            case 'bill.refunded':
                // Ex: Atualizar status de reembolso
                break;

            // Outros eventos
            default:
                $logger->warning("[VINDI] Evento não tratado: {$event}");
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
