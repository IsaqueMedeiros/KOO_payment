<?php
namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

class RefundService
{
    private HttpClientInterface $http;
    private string $apiKey;
    private string $baseUrl;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $http, LoggerInterface $logger)
    {
        $this->http     = $http;
        $this->logger   = $logger;

        // ✅ Correto: usando as variáveis do .env
        $this->apiKey   = $_ENV['VINDI_API_KEY'];
        $this->baseUrl  = rtrim($_ENV['VINDI_API_REFUND'], '/'); // remove barra final, se houver
    }

    /**
     * Estorna uma cobrança Vindi
     */
    public function refundCharge(int $chargeId, bool $cancelBill = true): array
    {
        $url = "{$this->baseUrl}/charges/{$chargeId}/refund";

        try {
            $response = $this->http->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Basic ' . base64_encode("{$this->apiKey}:"),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'cancel_bill' => $cancelBill
                ],
            ]);

            return $response->toArray(false); // Permite erros 4xx
        } catch (\Throwable $e) {
            $this->logger->error('Erro ao estornar cobrança Vindi', [
                'exception' => $e,
                'charge_id' => $chargeId,
            ]);
            throw new RuntimeException("Erro ao estornar cobrança {$chargeId}");
        }
    }
}
