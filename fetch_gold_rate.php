<?php
header('Content-Type: application/json');

// API configuration
$apiKey = 'a17294980amsh2983f710df7376dp12c7e5jsn96337199649f';
$apiHost = 'india-gold-price-live.p.rapidapi.com';
$apiUrl = 'https://india-gold-price-live.p.rapidapi.com/api/gold-prices/latest';

try {
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "GET",
        CURLOPT_HTTPHEADER => [
            "x-rapidapi-host: $apiHost",
            "x-rapidapi-key: $apiKey"
        ],
    ]);

    // Set up header capture
    $headers = [];
    curl_setopt($curl, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$headers) {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) < 2) // ignore invalid headers
            return $len;

        $headers[strtolower(trim($header[0]))][] = trim($header[1]);
        return $len;
    });

    // Execute request once with header capture
    $response = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $err = curl_error($curl);
    curl_close($curl);

    // Extract API limits from captured headers
    $requestsRemaining = $headers['x-ratelimit-requests-remaining'][0] ?? 'N/A';
    $requestsLimit = $headers['x-ratelimit-requests-limit'][0] ?? 'N/A';

    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }

    if ($httpCode !== 200) {
        throw new Exception("HTTP Error: " . $httpCode);
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON Decode Error: " . json_last_error_msg());
    }

    // Debug: Log the API response
    error_log("API Response: " . print_r($data, true));

    // Check if we have the expected data structure
    if (!isset($data['status']) || $data['status'] !== 'success' || !isset($data['data']) || !is_array($data['data'])) {
        throw new Exception("Unexpected API response structure");
    }

    // Get all gold rates
    $goldRates = [];
    foreach ($data['data'] as $rate) {
        if (in_array($rate['purity'], ['999', '995', '916', '750'])) {
            $goldRates[] = [
                'purity' => $rate['purity'],
                'price' => number_format($rate['pm_rate'], 2),
                'type' => $rate['type']
            ];
        }
    }

    if (empty($goldRates)) {
        throw new Exception("Gold rates not found in response");
    }

    // Format and send the response
    echo json_encode([
        'success' => true,
        'rates' => $goldRates,
        'timestamp' => time(),
        'api_usage' => [
            'remaining' => $requestsRemaining,
            'limit' => $requestsLimit
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
