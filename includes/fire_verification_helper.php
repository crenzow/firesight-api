<?php
/**
 * Calls the local FIRESIGHT fire-verification-service (FastAPI running on
 * http://localhost:8000) to classify an uploaded report photo.
 *
 * This NEVER blocks report submission: if the service is down, times out,
 * or errors, this returns null values and the report is still saved as
 * normal (just without an AI flag) — that's the intended behavior.
 *
 * @param string $absoluteImagePath Full filesystem path to the saved image.
 * @return array{label: ?string, confidence: ?float} 
 */
function verifyFireImage(string $absoluteImagePath): array
{
    $result = ['label' => null, 'confidence' => null];

    if (!file_exists($absoluteImagePath)) {
        error_log('[fire_verification_helper] Image not found: ' . $absoluteImagePath);
        return $result;
    }

    // Change this if the inference service runs elsewhere (e.g. on the VPS,
    // this might be an internal address like http://127.0.0.1:8000 too,
    // since it's meant to run on the same server as the PHP backend).
    $serviceUrl = 'http://localhost:8000/predict';

    $mimeType = mime_content_type($absoluteImagePath) ?: 'image/jpeg';

    $curlFile = new CURLFile($absoluteImagePath, $mimeType, basename($absoluteImagePath));

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $serviceUrl,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['image' => $curlFile],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10, // don't let a slow/dead service hang the request
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);

    if ($curlError) {
        error_log('[fire_verification_helper] cURL error: ' . $curlError);
        return $result;
    }

    if ($httpCode !== 200) {
        error_log('[fire_verification_helper] Service returned HTTP ' . $httpCode . ': ' . $response);
        return $result;
    }

    $data = json_decode($response, true);
    if (!is_array($data) || !isset($data['label'], $data['confidence'])) {
        error_log('[fire_verification_helper] Unexpected response: ' . $response);
        return $result;
    }

    $result['label'] = $data['label'];       // 'fire' or 'non_fire'
    $result['confidence'] = (float) $data['confidence'];

    return $result;
}