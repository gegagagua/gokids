<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * ProCredit E-commerce Payment Gateway client (Internet Shop Integration v1.1).
 * Two-way TLS: client certificate (cert.pem + key.pem), server verified with ca.pem.
 * CN of client certificate must match MerchantID provided by the bank.
 */
class ProCreditEcommerceClient
{
    protected string $orderEndpoint;

    protected ?string $certPath;

    protected ?string $keyPath;

    protected ?string $caPath;

    protected bool $verifyPeer;

    public function __construct()
    {
        $this->orderEndpoint = rtrim((string) config('services.procredit.order_endpoint', ''), '/');
        $this->certPath = config('services.procredit.cert_path');
        $this->keyPath = config('services.procredit.key_path');
        $this->caPath = config('services.procredit.ca_path');
        $this->verifyPeer = (bool) config('services.procredit.verify_peer', true);
    }

    /**
     * Create Order (Purchase) – POST JSON to E-commerce PG.
     * Uses HTTPS + TLS client certificates (no SSH). Cert files from config (certs/cert.pem, key.pem, ca.pem).
     */
    public function createOrder(array $params): array
    {
        $url = $this->orderEndpoint ?: null;
        if (!$url) {
            Log::warning('ProCredit E-commerce: order_endpoint not configured');
            return ['success' => false, 'error' => 'ProCredit order endpoint not configured'];
        }

        // api.bank.com is a placeholder from the PDF – not a real host. Bank must provide the real URL.
        if (str_contains(parse_url($url, PHP_URL_HOST) ?? '', 'api.bank.com')) {
            return [
                'success' => false,
                'error' => 'ProCredit order endpoint is still the placeholder (api.bank.com). Set the real URL from the bank in config/services.php → procredit.order_endpoint.',
            ];
        }

        $certCheck = $this->validateCertPaths();
        if ($certCheck !== null) {
            Log::warning('ProCredit E-commerce: certificate check failed', ['error' => $certCheck]);
            return ['success' => false, 'error' => $certCheck];
        }

        Log::info('ProCredit E-commerce Create Order request', ['params' => $params]);

        $body = [
            'order' => [
                'typeRid' => $params['typeRid'] ?? config('services.procredit.type_rid', 'ORD1'),
                'amount' => 100,
                'currency' => 'GEL',
                'description' => $params['description'] ?? 'Order',
                'language' => $params['language'] ?? 'en',
                'hppRedirectUrl' => $params['hppRedirectUrl'],
                'initiationEnvKind' => $params['initiationEnvKind'] ?? 'Browser',
                'consumerDevice' => $params['consumerDevice'] ?? $this->defaultConsumerDevice(),
            ],
        ];

        $json = json_encode($body);
        if ($json === false) {
            return ['success' => false, 'error' => 'Invalid JSON body'];
        }

        Log::info('ProCredit E-commerce Create Order request', ['url' => $url, 'body_keys' => array_keys($body['order'])]);

        $response = $this->request('POST', $url, $json);

        if ($response['errno'] !== 0) {
            Log::error('ProCredit E-commerce Create Order cURL error', [
                'errno' => $response['errno'],
                'error' => $response['error'],
            ]);
            return ['success' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        $status = (int) ($response['http_code'] ?? 0);
        $responseBody = $response['body'] ?? '';

        if ($status < 200 || $status >= 300) {
            Log::warning('ProCredit E-commerce Create Order non-2xx', [
                'http_code' => $status,
                'body' => $responseBody,
            ]);
            return [
                'success' => false,
                'error' => 'Gateway returned ' . $status,
                'response_body' => $responseBody,
            ];
        }

        $data = json_decode($responseBody, true);
        if (!isset($data['order'])) {
            Log::warning('ProCredit E-commerce Create Order invalid response', ['body' => $responseBody]);
            return ['success' => false, 'error' => 'Invalid response', 'response_body' => $responseBody];
        }

        return ['success' => true, 'order' => $data['order']];
    }

    /**
     * Get Order Details – GET order/{id}?password=...&tokenDetailLevel=2&tranDetailLevel=1.
     */
    public function getOrderDetails(string $bankOrderId, string $password): array
    {
        $baseUrl = $this->orderEndpoint ?: null;
        if (!$baseUrl) {
            return ['success' => false, 'error' => 'ProCredit order endpoint not configured'];
        }

        $url = $baseUrl . '/' . $bankOrderId . '?' . http_build_query([
            'password' => $password,
            'tokenDetailLevel' => 2,
            'tranDetailLevel' => 1,
        ]);

        Log::info('ProCredit E-commerce Get Order Details request', ['url_base' => $baseUrl, 'order_id' => $bankOrderId]);

        $response = $this->request('GET', $url, null);

        if ($response['errno'] !== 0) {
            Log::error('ProCredit E-commerce Get Order Details cURL error', [
                'errno' => $response['errno'],
                'error' => $response['error'],
            ]);
            return ['success' => false, 'error' => $response['error'] ?? 'Connection failed'];
        }

        $status = (int) ($response['http_code'] ?? 0);
        $responseBody = $response['body'] ?? '';

        if ($status < 200 || $status >= 300) {
            Log::warning('ProCredit E-commerce Get Order Details non-2xx', [
                'http_code' => $status,
                'body' => $responseBody,
            ]);
            return [
                'success' => false,
                'error' => 'Gateway returned ' . $status,
                'response_body' => $responseBody,
            ];
        }

        $data = json_decode($responseBody, true);
        if (!isset($data['order'])) {
            return ['success' => false, 'error' => 'Invalid response', 'response_body' => $responseBody];
        }

        return ['success' => true, 'order' => $data['order']];
    }

    protected function request(string $method, string $url, ?string $body): array
    {
        $ch = curl_init($url);

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ];

        if ($this->certPath && is_file($this->certPath)) {
            $opts[CURLOPT_SSLCERT] = $this->certPath;
        }
        if ($this->keyPath && is_file($this->keyPath)) {
            $opts[CURLOPT_SSLKEY] = $this->keyPath;
        }
        if ($this->verifyPeer && $this->caPath && is_file($this->caPath)) {
            $opts[CURLOPT_CAINFO] = $this->caPath;
            $opts[CURLOPT_SSL_VERIFYPEER] = true;
        } else {
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if ($method === 'POST' && $body !== null) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        foreach ($opts as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        return [
            'body' => $responseBody === false ? '' : $responseBody,
            'http_code' => $httpCode,
            'errno' => $errno,
            'error' => $error,
        ];
    }

    /**
     * Ensure cert/key/ca files exist and are readable. Returns null if OK, or error message.
     */
    protected function validateCertPaths(): ?string
    {
        $paths = [
            'cert' => $this->certPath,
            'key' => $this->keyPath,
            'ca' => $this->caPath,
        ];
        foreach ($paths as $name => $path) {
            if (empty($path)) {
                return "ProCredit: {$name}_path is empty (check config/services.php → procredit).";
            }
            if (!is_file($path)) {
                return "ProCredit: certificate file not found: {$path} (check certs/ folder and config).";
            }
            if (!is_readable($path)) {
                return "ProCredit: certificate file not readable: {$path}.";
            }
        }
        Log::info('ProCredit E-commerce: using certificates', [
            'cert_path' => $this->certPath,
            'key_path' => $this->keyPath,
            'ca_path' => $this->caPath,
        ]);
        return null;
    }

    protected function defaultConsumerDevice(): array
    {
        return [
            'browser' => [
                'javaEnabled' => false,
                'jsEnabled' => true,
                'acceptHeader' => 'application/json,application/jose;charset=utf-8',
                'ip' => request()->ip() ?? '127.0.0.1',
                'colorDepth' => '24',
                'screenW' => '1080',
                'screenH' => '1920',
                'tzOffset' => '-240',
                'language' => 'en-EN',
                'userAgent' => request()->userAgent() ?? 'Mozilla/5.0 (compatible; MyKids/1.0)',
            ],
        ];
    }
}
