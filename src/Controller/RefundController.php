<?php
namespace App\Controller;

use App\Service\RefundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class RefundController extends AbstractController
{
    private RefundService $refundService;

    public function __construct(RefundService $refundService)
    {
        $this->refundService = $refundService;
    }

    /**
     * Estorna uma cobrança da Vindi
     *
     * Exemplo de chamada:
     * POST /api/refund
     * Body JSON:
     * {
     *   "charge_id": 389901388,
     *   "cancel_bill": true
     * }
     */
    #[Route('/api/refund', methods: ['POST'])]
    public function refund(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['charge_id'])) {
            return $this->json(['error' => 'Parâmetro "charge_id" é obrigatório.'], 400);
        }

        $chargeId = (int) $data['charge_id'];
        $cancelBill = $data['cancel_bill'] ?? true;

        try {
            $result = $this->refundService->refundCharge($chargeId, $cancelBill);

            return $this->json([
                'refunded' => true,
                'charge_id' => $chargeId,
                'status' => $result['status'] ?? 'unknown',
                'result' => $result
            ]);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Estorno falhou',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
