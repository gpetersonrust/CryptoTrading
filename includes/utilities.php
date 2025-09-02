<?php

/**
 * Simple reusable cURL helper
 *
 * @param string $url         The request URL
 * @param string $method      HTTP method: GET, POST, PUT, DELETE
 * @param array  $headers     Associative or numeric array of headers
 * @param array|string|null $body  Request body (array is JSON-encoded automatically)
 * @param int    $timeout     Timeout in seconds
 * @param bool   $decodeJson  Whether to decode JSON response to array
 * @return mixed              Decoded JSON (array), or raw response string
 * @throws RuntimeException   On HTTP/cURL errors
 */
function curl_request(
    string $url,
    string $method = 'GET',
    array $headers = [],
    $body = null,
    int $timeout = 15,
    bool $decodeJson = true
) {
    $ch = curl_init($url);

    // Default headers
    $finalHeaders = [];
    foreach ($headers as $k => $v) {
        $finalHeaders[] = is_string($k) ? "$k: $v" : $v;
    }

    // Handle body
    if ($body !== null) {
        if (is_array($body)) {
            $body = json_encode($body);
            $finalHeaders[] = 'Content-Type: application/json';
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $finalHeaders,
        CURLOPT_USERAGENT      => 'php-curl-helper/1.0',
    ]);

    $raw = curl_exec($ch);

    if ($raw === false) {
        $err = curl_error($ch);
        $code = curl_errno($ch);
        curl_close($ch);
        throw new RuntimeException("cURL error ($code): $err");
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300) {
        throw new RuntimeException("HTTP $httpCode response: $raw");
    }

    if ($decodeJson) {
        $decoded = json_decode($raw, true);
        if (isset($decoded['error']) && !empty($decoded['error'])) {
            throw new RuntimeException("API error: " . implode(', ', $decoded['error']));
        }
        return isset($decoded['result']) ? $decoded['result'] : $decoded;
    }
    return $raw;
}
