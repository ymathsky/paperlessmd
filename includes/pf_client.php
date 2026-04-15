<?php
/**
 * Practice Fusion FHIR R4 — API Client
 * Handles OAuth 2.0 token management and all HTTP calls to PF.
 */

require_once __DIR__ . '/pf_config.php';

class PracticeFusionClient
{
    private string $accessToken = '';

    /**
     * Get a valid access token (fetches new one or returns cached).
     */
    public function getAccessToken(): string
    {
        // Try cached token first
        if (file_exists(PF_TOKEN_CACHE)) {
            $cached = json_decode(file_get_contents(PF_TOKEN_CACHE), true);
            if (!empty($cached['access_token']) && time() < ($cached['expires_at'] - 30)) {
                return $cached['access_token'];
            }
        }

        // Request new token via client_credentials grant
        $ch = curl_init(PF_TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_POSTFIELDS     => http_build_query([
                'grant_type'    => 'client_credentials',
                'client_id'     => PF_CLIENT_ID,
                'client_secret' => PF_CLIENT_SECRET,
                'scope'         => 'system/Patient.read system/DocumentReference.write',
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        ]);

        $body   = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err || $status !== 200) {
            throw new RuntimeException('PF token request failed: HTTP ' . $status . ' — ' . $err);
        }

        $json = json_decode($body, true);
        if (empty($json['access_token'])) {
            throw new RuntimeException('PF token response missing access_token: ' . $body);
        }

        // Cache the token
        $cacheDir = dirname(PF_TOKEN_CACHE);
        if (!is_dir($cacheDir)) mkdir($cacheDir, 0750, true);
        file_put_contents(PF_TOKEN_CACHE, json_encode([
            'access_token' => $json['access_token'],
            'expires_at'   => time() + (int)($json['expires_in'] ?? 3600),
        ]));

        return $json['access_token'];
    }

    /**
     * Make an authenticated request to the PF FHIR API.
     * @return array ['status' => int, 'body' => array|null, 'raw' => string]
     */
    public function request(string $method, string $path, ?array $payload = null): array
    {
        $url     = rtrim(PF_FHIR_BASE, '/') . '/' . ltrim($path, '/');
        $token   = $this->getAccessToken();
        $headers = [
            'Authorization: Bearer ' . $token,
            'Accept: application/fhir+json',
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        if ($payload !== null) {
            $jsonBody  = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/fhir+json';
            $headers[] = 'Content-Length: ' . strlen($jsonBody);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $raw    = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        if ($err) {
            throw new RuntimeException('cURL error: ' . $err);
        }

        return [
            'status' => $status,
            'body'   => json_decode($raw, true),
            'raw'    => $raw,
        ];
    }

    /**
     * Search for a patient in Practice Fusion by name + DOB.
     * Returns array of matching FHIR Patient resources.
     */
    public function searchPatient(string $firstName, string $lastName, ?string $dob = null): array
    {
        $params = ['family' => $lastName, 'given' => $firstName];
        if ($dob) $params['birthdate'] = $dob; // format: YYYY-MM-DD

        $result = $this->request('GET', 'Patient?' . http_build_query($params));

        if ($result['status'] !== 200) {
            throw new RuntimeException('Patient search failed: HTTP ' . $result['status']);
        }

        $bundle  = $result['body'];
        $entries = $bundle['entry'] ?? [];
        return array_column($entries, 'resource');
    }

    /**
     * Upload a signed consent form as a FHIR DocumentReference.
     * $pdfBase64 — base64-encoded PDF content
     */
    public function uploadDocument(
        string $pfPatientId,
        string $title,
        string $docType,
        string $pdfBase64,
        string $dateTime
    ): array {
        // LOINC codes for common document types
        $loinc = [
            'vital_cs'    => ['code' => '59284-0', 'display' => 'Consent Document'],
            'new_patient' => ['code' => '59284-0', 'display' => 'Consent Document'],
            'abn'         => ['code' => '42348-3', 'display' => 'Advance directives'],
            'pf_signup'   => ['code' => '59284-0', 'display' => 'Consent Document'],
            'ccm_consent' => ['code' => '59284-0', 'display' => 'Consent for care plan'],
        ];
        $typeCode = $loinc[$docType] ?? ['code' => '59284-0', 'display' => 'Clinical Document'];

        $resource = [
            'resourceType' => 'DocumentReference',
            'status'       => 'current',
            'type'         => [
                'coding' => [[
                    'system'  => 'http://loinc.org',
                    'code'    => $typeCode['code'],
                    'display' => $typeCode['display'],
                ]],
                'text' => $title,
            ],
            'subject' => [
                'reference' => 'Patient/' . $pfPatientId,
            ],
            'date'    => $dateTime, // ISO 8601
            'description' => $title,
            'content' => [[
                'attachment' => [
                    'contentType' => 'application/pdf',
                    'data'        => $pdfBase64,
                    'title'       => $title . '.pdf',
                    'creation'    => $dateTime,
                ],
            ]],
            'context' => [
                'practiceSetting' => [
                    'coding' => [[
                        'system'  => 'http://snomed.info/sct',
                        'code'    => '394814009',
                        'display' => 'General practice',
                    ]],
                ],
            ],
        ];

        $result = $this->request('POST', 'DocumentReference', $resource);

        if (!in_array($result['status'], [200, 201], true)) {
            throw new RuntimeException(
                'DocumentReference POST failed: HTTP ' . $result['status'] . ' — ' . $result['raw']
            );
        }

        return $result['body'] ?? [];
    }
}
