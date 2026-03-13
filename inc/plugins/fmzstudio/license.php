<?php
/**
 * FMZ Studio — License Client (Encrypted)
 *
 * Validates license keys against a WordPress/WooCommerce REST API.
 * All license data is stored encrypted (AES-256-CBC) and signed (HMAC-SHA256).
 *
 * Security layers:
 *   1. AES-256-CBC encryption of stored data — DB values are opaque blobs
 *   2. HMAC-SHA256 integrity on stored blobs — tampering detected
 *   3. Site-bound encryption key — derived from DB credentials, not portable between installs
 *   4. File integrity self-verification — detects source code tampering
 *   5. Periodic server re-validation — cached token expires, forces fresh check
 *   6. HTTPS + SSL verification — authenticates the license server
 *
 * @version 2.0.0
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class FMZLicense
{
    /* ─────────────────────────────────
       Constants
       ───────────────────────────────── */

    const API_BASE = 'https://tektove.com/wp-json/fmz-license/v1';

    // Single setting name for the encrypted blob
    const SETTING_BLOB  = 'fmz_license_blob';
    const SETTING_CHECK = 'fmz_license_check'; // last successful validation timestamp

    // How often to re-validate with the server (seconds)
    const REVALIDATION_INTERVAL = 300; // 5 minutes — catches remote deactivation quickly

    // Internal salt for key derivation — change before distribution
    const DERIVATION_SALT = 'fmz_2026_studio_v2_kr9Xm4pQ';

    // RSA public key for verifying API response signatures
    const RSA_PUBLIC_KEY = '-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAvMoGW7YJDA6kiR95Nu7S
Vx3iUaTn7aCrI4mLsd+JpybAyab8Dm98NjSWVQofg5FqX2LJ9PRHYBvVfZHpn+tC
0DOn1RcUNxsXW0qW/3xhhgZTPq3l+SHsvVvwHeY8cc31A8ZFjHUv0q0mzQvZ03UB
LrVrQRQY07xxohpP3G4GxQSbOYcHfQHS43xTTxcffrQ/g4U0uJWAivr8T3muxEtN
OdebX/qoUOMe8AWcn0q3AvRXT+zDMzk2aQdOCbs0EpZeOC55FdkxEaO0UeOuocSl
JosmM1L684iZDk8gDi0GWPYElAIQzbtAoucZ/cO5EbI5XEbA/9mmEK+9YPPk2jk3
fwIDAQAB
-----END PUBLIC KEY-----';

    /* ─────────────────────────────────
       Settings Management
       ───────────────────────────────── */

    public static function ensureSettings(): void
    {
        global $db;

        $names = [self::SETTING_BLOB, self::SETTING_CHECK];
        foreach ($names as $name) {
            $q = $db->simple_select('settings', 'name', "name='" . $db->escape_string($name) . "'");
            if (!$db->num_rows($q)) {
                $db->insert_query('settings', [
                    'name'        => $name,
                    'title'       => 'FMZ License Data',
                    'description' => '',
                    'optionscode' => 'text',
                    'value'       => '',
                    'disporder'   => 0,
                    'gid'         => 0,
                ]);
            }
        }

        // Migrate from old plaintext settings if they exist
        self::migrateFromPlaintext();

        rebuild_settings();
    }

    public static function removeSettings(): void
    {
        global $db;
        $db->delete_query('settings', "name IN ('fmz_license_blob','fmz_license_check','fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");
        rebuild_settings();
    }

    /* ─────────────────────────────────
       Public Getters (from decrypted blob)
       ───────────────────────────────── */

    private static ?array $_cache = null;

    private static function loadLicenseData(): array
    {
        if (self::$_cache !== null) {
            return self::$_cache;
        }

        global $mybb;
        $blob = $mybb->settings[self::SETTING_BLOB] ?? '';
        if (empty($blob)) {
            self::$_cache = [];
            return [];
        }

        $data = self::decryptBlob($blob);
        if ($data === null) {
            self::$_cache = [];
            return [];
        }

        self::$_cache = $data;
        return $data;
    }

    public static function getKey(): string
    {
        return self::loadLicenseData()['key'] ?? '';
    }

    public static function getStatus(): string
    {
        return self::loadLicenseData()['status'] ?? '';
    }

    public static function getEmail(): string
    {
        return self::loadLicenseData()['email'] ?? '';
    }

    public static function getExpiry(): string
    {
        return self::loadLicenseData()['expiry'] ?? '';
    }

    public static function getDomain(): string
    {
        return self::loadLicenseData()['domain'] ?? '';
    }

    /* ─────────────────────────────────
       Validation
       ───────────────────────────────── */

    /**
     * Primary validation — checks local encrypted data + expiry + domain.
     */
    public static function isValid(): bool
    {
        $data = self::loadLicenseData();
        if (empty($data)) {
            return false;
        }

        $status = $data['status'] ?? '';
        if (!in_array($status, ['valid', 'active', 'reissued', 'redistributable'], true)) {
            return false;
        }

        // Check domain binding
        if (($data['domain'] ?? '') !== self::getSiteDomain()) {
            return false;
        }

        // Check expiry
        $expiry = $data['expiry'] ?? 'lifetime';
        if ($expiry !== 'lifetime' && strtotime($expiry) < time()) {
            return false;
        }

        // Periodic server re-validation
        self::periodicRevalidation();

        // Re-check after revalidation — blob may have been cleared by the server
        $data = self::loadLicenseData();
        if (empty($data)) {
            return false;
        }
        $status = $data['status'] ?? '';
        if (!in_array($status, ['valid', 'active', 'reissued', 'redistributable'], true)) {
            return false;
        }

        return true;
    }

    /**
     * Secondary inline validation — called from protected actions (editors, etc.)
     * Uses a different code path so patching isValid() alone won't suffice.
     */
    public static function assertLicensed(): bool
    {
        $d = self::loadLicenseData();
        if (empty($d) || empty($d['key'])) {
            return false;
        }
        // Re-derive and verify HMAC of the stored blob directly
        global $mybb;
        $raw = $mybb->settings[self::SETTING_BLOB] ?? '';
        if (empty($raw)) {
            return false;
        }
        $parts = explode('.', $raw, 3);
        if (count($parts) !== 3) {
            return false;
        }
        $eKey = self::deriveKey();
        $computedHmac = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $eKey);
        if (!hash_equals($computedHmac, $parts[2])) {
            return false;
        }
        $s = $d['status'] ?? '';
        return in_array($s, ['valid', 'active', 'reissued', 'redistributable'], true);
    }

    /**
     * File integrity check — verifies this file hasn't been tampered with.
     * Returns a hash of the license validation logic for comparison.
     */
    public static function integrityHash(): string
    {
        $file = __FILE__;
        if (!file_exists($file)) {
            return '';
        }
        return hash_hmac('sha256', file_get_contents($file), self::DERIVATION_SALT);
    }

    public static function getSiteDomain(): string
    {
        global $mybb;
        $url = $mybb->settings['bburl'] ?? '';
        $parsed = parse_url($url);
        return $parsed['host'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    }

    /* ─────────────────────────────────
       RSA Signature Verification
       ───────────────────────────────── */

    /**
     * Verify the RSA-SHA256 signature from an API response.
     * Prevents MITM attacks from forging validation responses.
     *
     * @param array $responseData  Decoded API response with 'signature' and 'sig_payload'
     * @return bool  True if signature is valid or signing not available, false if forged
     */
    private static function verifySignature(array $responseData): bool
    {
        $signature  = $responseData['signature']  ?? '';
        $sigPayload = $responseData['sig_payload'] ?? '';

        // If the server didn't include a signature, accept (signing may be disabled)
        if (empty($signature) || empty($sigPayload)) {
            return true;
        }

        $publicKey = openssl_pkey_get_public(self::RSA_PUBLIC_KEY);
        if (!$publicKey) {
            return true; // Can't verify without a valid public key — don't break functionality
        }

        $sigBinary = base64_decode($signature, true);
        if ($sigBinary === false) {
            return false;
        }

        $result = openssl_verify($sigPayload, $sigBinary, $publicKey, OPENSSL_ALGO_SHA256);
        return ($result === 1);
    }

    /* ─────────────────────────────────
       API Calls
       ───────────────────────────────── */

    public static function activate(string $key): array
    {
        $key    = trim($key);
        $domain = self::getSiteDomain();

        $response = self::apiPost('/activate', [
            'license_key' => $key,
            'domain'      => $domain,
            'product'     => 'fmz-studio',
            'site_hash'   => hash('sha256', $domain . self::DERIVATION_SALT),
        ]);

        if (!$response['success']) {
            return $response;
        }

        $data   = $response['data'];
        $status = $data['status'] ?? 'invalid';

        // Verify RSA signature from the server
        if (!self::verifySignature($data)) {
            return [
                'success' => false,
                'status'  => 'invalid',
                'message' => 'License server response signature verification failed. Possible tampering detected.',
                'data'    => $data,
            ];
        }

        if (in_array($status, ['valid', 'active', 'reissued', 'redistributable'], true)) {
            // Normalize 'active' to 'valid' for local storage consistency
            $localStatus = ($status === 'active') ? 'valid' : $status;

            $licenseData = [
                'key'         => $key,
                'status'      => $localStatus,
                'email'       => $data['email'] ?? '',
                'expiry'      => $data['expiry'] ?? 'lifetime',
                'domain'      => $domain,
                'activated'   => time(),
            ];

            self::saveLicenseBlob($licenseData);
            self::saveCheckTimestamp(time());
            self::$_cache = null; // force reload

            return [
                'success' => true,
                'status'  => $status,
                'message' => $data['message'] ?? 'License activated successfully.',
                'data'    => $data,
            ];
        }

        return [
            'success' => false,
            'status'  => $status,
            'message' => $data['message'] ?? 'This license key could not be activated.',
            'data'    => $data,
        ];
    }

    public static function deactivate(): array
    {
        $data   = self::loadLicenseData();
        $key    = $data['key'] ?? '';
        $domain = $data['domain'] ?? '';

        if (empty($key)) {
            return ['success' => false, 'message' => 'No license key is currently active.'];
        }

        $response = self::apiPost('/deactivate', [
            'license_key' => $key,
            'domain'      => $domain,
            'product'     => 'fmz-studio',
        ]);

        // Clear local data regardless
        self::clearLicenseBlob();
        self::$_cache = null;

        if (!$response['success']) {
            return [
                'success' => true,
                'message' => 'License cleared locally. (API notification may have failed: ' . $response['message'] . ')',
            ];
        }

        return [
            'success' => true,
            'message' => $response['data']['message'] ?? 'License deactivated successfully. It can now be used on another site.',
        ];
    }

    public static function checkStatus(): array
    {
        $data   = self::loadLicenseData();
        $key    = $data['key'] ?? '';
        $domain = $data['domain'] ?? '';

        if (empty($key)) {
            return ['success' => false, 'status' => '', 'message' => 'No license key stored.'];
        }

        $response = self::apiPost('/check', [
            'license_key' => $key,
            'domain'      => $domain,
            'product'     => 'fmz-studio',
        ]);

        if (!$response['success']) {
            // Server returned an error — check if the license has been revoked/deactivated
            $errStatus = $response['data']['status'] ?? '';
            if (in_array($errStatus, ['invalid', 'expired', 'revoked', 'inactive', 'deactivated', 'suspended'], true)) {
                self::clearLicenseBlob();
                self::$_cache = null;
            }
            return $response;
        }

        $rData  = $response['data'];
        $status = $rData['status'] ?? 'invalid';

        // Verify RSA signature from the server
        if (!self::verifySignature($rData)) {
            return [
                'success' => false,
                'status'  => 'invalid',
                'message' => 'License server response signature verification failed.',
            ];
        }

        // Handle statuses that mean the license is no longer valid
        // These statuses indicate remote deactivation from WordPress or expiry
        if (in_array($status, ['inactive', 'deactivated', 'expired', 'revoked', 'suspended', 'domain_mismatch', 'invalid'], true)) {
            self::clearLicenseBlob();
            self::$_cache = null;
            return [
                'success' => false,
                'status'  => $status,
                'message' => $rData['message'] ?? 'License is no longer valid.',
            ];
        }

        // Normalize 'active' to 'valid' for local storage
        $localStatus = ($status === 'active') ? 'valid' : $status;

        // Update local blob with new status
        $data['status'] = $localStatus;
        if (isset($rData['expiry'])) {
            $data['expiry'] = $rData['expiry'];
        }
        self::saveLicenseBlob($data);
        self::saveCheckTimestamp(time());
        self::$_cache = null;

        return [
            'success' => in_array($status, ['valid', 'active', 'reissued', 'redistributable'], true),
            'status'  => $status,
            'message' => $rData['message'] ?? '',
        ];
    }

    /* ─────────────────────────────────
       Periodic Re-validation
       ───────────────────────────────── */

    private static function periodicRevalidation(): void
    {
        global $mybb;
        $lastCheck = intval($mybb->settings[self::SETTING_CHECK] ?? 0);
        if ((time() - $lastCheck) > self::REVALIDATION_INTERVAL) {
            // Re-validate — don't block page load on failure
            $result = self::checkStatus();
            if (!$result['success']) {
                $status = $result['status'] ?? '';
                // Any non-valid status clears the license — this catches remote deactivation
                // from WordPress (inactive/deactivated), admin revocation, expiry, suspension, etc.
                if (in_array($status, ['invalid', 'expired', 'revoked', 'domain_mismatch', 'inactive', 'deactivated', 'suspended'], true)) {
                    self::clearLicenseBlob();
                    self::$_cache = null;
                }
            }
        }
    }

    /* ─────────────────────────────────
       Encryption & Decryption
       ───────────────────────────────── */

    /**
     * Derive a site-specific encryption key from database credentials.
     * This ensures encrypted blobs cannot be copied between MyBB installations.
     */
    private static function deriveKey(): string
    {
        global $config;
        $material = ($config['database']['hostname'] ?? 'localhost')
                  . '|' . ($config['database']['database'] ?? 'mybb')
                  . '|' . ($config['database']['table_prefix'] ?? 'mybb_')
                  . '|' . self::DERIVATION_SALT;
        return hash('sha256', $material, true); // 32 bytes for AES-256
    }

    /**
     * Encrypt license data array into a storable blob.
     * Format: base64(IV) . '.' . base64(ciphertext) . '.' . HMAC
     */
    private static function encryptBlob(array $data): string
    {
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        $key  = self::deriveKey();
        $iv   = random_bytes(16);

        $ciphertext = openssl_encrypt($json, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            return '';
        }

        $b64Iv   = base64_encode($iv);
        $b64Data = base64_encode($ciphertext);
        $hmac    = hash_hmac('sha256', $b64Iv . '.' . $b64Data, $key);

        return $b64Iv . '.' . $b64Data . '.' . $hmac;
    }

    /**
     * Decrypt a stored blob back into a license data array.
     * Returns null if tampered, corrupted, or wrong encryption key.
     */
    private static function decryptBlob(string $blob): ?array
    {
        $parts = explode('.', $blob, 3);
        if (count($parts) !== 3) {
            return null;
        }

        [$b64Iv, $b64Data, $storedHmac] = $parts;
        $key = self::deriveKey();

        // Verify HMAC first (timing-safe)
        $computedHmac = hash_hmac('sha256', $b64Iv . '.' . $b64Data, $key);
        if (!hash_equals($computedHmac, $storedHmac)) {
            return null; // tampered
        }

        $iv         = base64_decode($b64Iv, true);
        $ciphertext = base64_decode($b64Data, true);
        if ($iv === false || $ciphertext === false) {
            return null;
        }

        $json = openssl_decrypt($ciphertext, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) ? $data : null;
    }

    /* ─────────────────────────────────
       Storage
       ───────────────────────────────── */

    private static function saveLicenseBlob(array $data): void
    {
        global $mybb;
        $blob = self::encryptBlob($data);
        self::saveSettingValue(self::SETTING_BLOB, $blob);
        $mybb->settings[self::SETTING_BLOB] = $blob; // update in-memory so current request sees it
        rebuild_settings();
    }

    private static function clearLicenseBlob(): void
    {
        global $mybb;
        self::saveSettingValue(self::SETTING_BLOB, '');
        self::saveSettingValue(self::SETTING_CHECK, '');
        $mybb->settings[self::SETTING_BLOB] = '';    // update in-memory immediately
        $mybb->settings[self::SETTING_CHECK] = '';   // so loadLicenseData() sees empty blob
        self::$_cache = null;                         // force cache reload
        rebuild_settings();
    }

    private static function saveCheckTimestamp(int $time): void
    {
        global $mybb;
        self::saveSettingValue(self::SETTING_CHECK, (string)$time);
        $mybb->settings[self::SETTING_CHECK] = (string)$time;
    }

    private static function saveSettingValue(string $name, string $value): void
    {
        global $db;
        $db->update_query('settings', ['value' => $db->escape_string($value)], "name='" . $db->escape_string($name) . "'");
    }

    /* ─────────────────────────────────
       Migration from v1 plaintext
       ───────────────────────────────── */

    private static function migrateFromPlaintext(): void
    {
        global $db;

        // Check if old plaintext settings exist
        $q = $db->simple_select('settings', 'value', "name='fmz_license_key'");
        if (!$db->num_rows($q)) {
            return;
        }

        $oldKey = $db->fetch_field($q, 'value');
        if (empty($oldKey)) {
            // Clean up empty old settings
            $db->delete_query('settings', "name IN ('fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");
            return;
        }

        // Read all old values
        $oldStatus = '';
        $q2 = $db->simple_select('settings', 'value', "name='fmz_license_status'");
        if ($db->num_rows($q2)) {
            $oldStatus = $db->fetch_field($q2, 'value');
        }

        $oldEmail = '';
        $q3 = $db->simple_select('settings', 'value', "name='fmz_license_email'");
        if ($db->num_rows($q3)) {
            $oldEmail = $db->fetch_field($q3, 'value');
        }

        $oldExpiry = 'lifetime';
        $q4 = $db->simple_select('settings', 'value', "name='fmz_license_expiry'");
        if ($db->num_rows($q4)) {
            $oldExpiry = $db->fetch_field($q4, 'value') ?: 'lifetime';
        }

        $oldDomain = '';
        $q5 = $db->simple_select('settings', 'value', "name='fmz_license_domain'");
        if ($db->num_rows($q5)) {
            $oldDomain = $db->fetch_field($q5, 'value');
        }

        // Encrypt and store as new blob
        $licenseData = [
            'key'         => $oldKey,
            'status'      => $oldStatus,
            'email'       => $oldEmail,
            'expiry'      => $oldExpiry,
            'domain'      => $oldDomain ?: self::getSiteDomain(),
            'activated'   => time(),
            'migrated'    => true,
        ];

        self::saveLicenseBlob($licenseData);
        self::saveCheckTimestamp(time());

        // Remove old plaintext settings
        $db->delete_query('settings', "name IN ('fmz_license_key','fmz_license_status','fmz_license_email','fmz_license_expiry','fmz_license_domain')");
    }

    /* ─────────────────────────────────
       HTTP
       ───────────────────────────────── */

    private static function apiPost(string $endpoint, array $body): array
    {
        $url = self::API_BASE . $endpoint;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($body),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/x-www-form-urlencoded',
                'X-FMZ-Client: 2.0.0',
                'X-FMZ-Integrity: ' . self::integrityHash(),
            ],
        ]);

        $rawResponse = curl_exec($ch);
        $httpCode    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError   = curl_error($ch);
        curl_close($ch);

        if ($rawResponse === false) {
            return [
                'success' => false,
                'message' => 'Could not connect to the license server. cURL error: ' . $curlError,
                'data'    => [],
            ];
        }

        $decoded = json_decode($rawResponse, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'message' => 'Invalid response from license server (HTTP ' . $httpCode . ').',
                'data'    => [],
            ];
        }

        if ($httpCode >= 400) {
            return [
                'success' => false,
                'message' => $decoded['message'] ?? 'License server returned an error (HTTP ' . $httpCode . ').',
                'data'    => $decoded,
            ];
        }

        return [
            'success' => true,
            'message' => $decoded['message'] ?? 'OK',
            'data'    => $decoded,
        ];
    }
}
