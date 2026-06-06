<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function api_request(string $method, string $path, ?array $data = null, ?string $token = null): array {
    session_init();
    $url = API_BASE . '/api' . $path;

    $ch = curl_init($url);
    if ($ch === false) {
        return ['error' => 'Failed to initialise cURL', '_status' => 0];
    }

    $tok = $token ?? ($_SESSION['access_token'] ?? '');
    $headers = ['Content-Type: application/json', 'Accept: application/json'];
    if ($tok) {
        $headers[] = "Authorization: Bearer $tok";
    }

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $body   = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err    = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['error' => "API unreachable: $err", '_status' => 0];
    }

    $decoded = json_decode((string) $body, true);
    if ($decoded === null) {
        return ['error' => "Non-JSON response (HTTP $status)", '_status' => $status, '_raw' => (string)$body];
    }
    $decoded['_status'] = $status;
    return $decoded;
}

function api_get(string $path, array $query = []): array {
    if ($query) {
        $path .= '?' . http_build_query($query);
    }
    return api_request('GET', $path);
}

function api_post(string $path, array $data = []): array {
    return api_request('POST', $path, $data);
}

function api_put(string $path, array $data = []): array {
    return api_request('PUT', $path, $data);
}

function api_delete(string $path): array {
    return api_request('DELETE', $path);
}

function api_ok(array $resp): bool {
    return ($resp['_status'] ?? 0) >= 200 && ($resp['_status'] ?? 0) < 300;
}

function api_error(array $resp): string {
    return $resp['error'] ?? "Unknown error (HTTP {$resp['_status']})";
}
