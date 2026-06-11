<?php

declare(strict_types=1);

namespace App\Services;

final class OAuthService
{
    public function googleAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $state,
            'access_type'   => 'online',
            'prompt'        => 'select_account',
        ]);
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . $params;
    }

    public function fetchGoogleUser(string $code): array
    {
        $tokenData = $this->post('https://oauth2.googleapis.com/token', [
            'code'          => $code,
            'client_id'     => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'client_secret' => $_ENV['GOOGLE_CLIENT_SECRET'] ?? '',
            'redirect_uri'  => $_ENV['GOOGLE_REDIRECT_URI'] ?? '',
            'grant_type'    => 'authorization_code',
        ]);

        $accessToken = $tokenData['access_token'] ?? '';
        if ($accessToken === '') {
            throw new \RuntimeException('Google token exchange failed.');
        }

        $userInfo = $this->get('https://www.googleapis.com/oauth2/v3/userinfo', $accessToken);

        $sub   = $userInfo['sub'] ?? '';
        $email = $userInfo['email'] ?? '';
        $name  = $userInfo['name'] ?? ($userInfo['given_name'] ?? $email);

        if ($sub === '' || $email === '') {
            throw new \RuntimeException('Google user info incomplete.');
        }

        return [
            'provider'    => 'google',
            'provider_id' => $sub,
            'email'       => $email,
            'name'        => $name,
        ];
    }

    public function appleAuthUrl(string $state): string
    {
        $params = http_build_query([
            'client_id'     => $_ENV['APPLE_SERVICE_ID'] ?? '',
            'redirect_uri'  => $_ENV['APPLE_REDIRECT_URI'] ?? '',
            'response_type' => 'code id_token',
            'scope'         => 'name email',
            'state'         => $state,
            'response_mode' => 'form_post',
        ]);
        return 'https://appleid.apple.com/auth/authorize?' . $params;
    }

    public function verifyAppleIdToken(string $idToken): array
    {
        $parts   = explode('.', $idToken);
        $header  = json_decode($this->b64uDecode($parts[0] ?? ''), true) ?? [];
        $payload = json_decode($this->b64uDecode($parts[1] ?? ''), true) ?? [];

        $kid = $header['kid'] ?? '';
        if ($kid === '') {
            throw new \RuntimeException('Apple id_token missing kid.');
        }

        $keys = $this->get('https://appleid.apple.com/auth/keys');
        $jwk  = null;
        foreach ($keys['keys'] ?? [] as $key) {
            if (($key['kid'] ?? '') === $kid) {
                $jwk = $key;
                break;
            }
        }

        if ($jwk === null) {
            throw new \RuntimeException("Apple public key not found for kid: {$kid}");
        }

        $pem    = $this->rsaJwkToPem($jwk['n'], $jwk['e']);
        $pubKey = openssl_pkey_get_public($pem);

        if ($pubKey === false) {
            throw new \RuntimeException('Failed to load Apple public key.');
        }

        $signature = $this->b64uDecode($parts[2] ?? '');
        $data      = $parts[0] . '.' . $parts[1];
        $result    = openssl_verify($data, $signature, $pubKey, OPENSSL_ALGO_SHA256);

        if ($result !== 1) {
            throw new \RuntimeException('Apple id_token signature invalid.');
        }

        $sub   = $payload['sub'] ?? '';
        $email = $payload['email'] ?? '';

        if ($sub === '') {
            throw new \RuntimeException('Apple id_token missing sub.');
        }

        return [
            'provider'    => 'apple',
            'provider_id' => $sub,
            'email'       => $email,
            'name'        => $email,
        ];
    }

    private function post(string $url, array $data): array
    {
        $ctx  = stream_context_create([
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query($data),
                'timeout' => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException("HTTP POST failed: {$url}");
        }
        return json_decode($body, true) ?? [];
    }

    private function get(string $url, string $bearerToken = ''): array
    {
        $headers = "Accept: application/json\r\n";
        if ($bearerToken !== '') {
            $headers .= "Authorization: Bearer {$bearerToken}\r\n";
        }
        $ctx  = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => $headers,
                'timeout'       => 15,
                'ignore_errors' => true,
            ],
        ]);
        $body = file_get_contents($url, false, $ctx);
        if ($body === false) {
            throw new \RuntimeException("HTTP GET failed: {$url}");
        }
        return json_decode($body, true) ?? [];
    }

    private function rsaJwkToPem(string $n, string $e): string
    {
        $nRaw = $this->b64uDecode($n);
        $eRaw = $this->b64uDecode($e);

        if (ord($nRaw[0]) > 0x7f) {
            $nRaw = "\x00" . $nRaw;
        }
        if (ord($eRaw[0]) > 0x7f) {
            $eRaw = "\x00" . $eRaw;
        }

        $modulus  = "\x02" . $this->derLen(strlen($nRaw)) . $nRaw;
        $exponent = "\x02" . $this->derLen(strlen($eRaw)) . $eRaw;
        $bitStr   = "\x30" . $this->derLen(strlen($modulus . $exponent)) . $modulus . $exponent;
        $oid      = "\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00";
        $bs       = "\x03" . $this->derLen(strlen($bitStr) + 1) . "\x00" . $bitStr;
        $spki     = "\x30" . $this->derLen(strlen($oid . $bs)) . $oid . $bs;

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($spki), 64)
            . "-----END PUBLIC KEY-----\n";
    }

    private function derLen(int $len): string
    {
        if ($len < 128) {
            return chr($len);
        }
        $bytes = '';
        for ($tmp = $len; $tmp > 0; $tmp >>= 8) {
            $bytes = chr($tmp & 0xff) . $bytes;
        }
        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private function b64uDecode(string $data): string
    {
        $pad  = strlen($data) % 4;
        if ($pad) {
            $data .= str_repeat('=', 4 - $pad);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
