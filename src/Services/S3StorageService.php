<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Minimal S3-compatible uploader using AWS Signature V4 — no SDK required.
 * Works with AWS S3, Railway Bucket (Tigris), and Cloudflare R2.
 *
 * Required env vars: AWS_S3_BUCKET_NAME (or AWS_S3_BUCKET), AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY
 * Optional: AWS_DEFAULT_REGION, AWS_ENDPOINT_URL, S3_PUBLIC_BASE_URL
 */
final class S3StorageService
{
    public function __construct(
        private readonly string $bucket,
        private readonly string $region,
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $publicBaseUrl,
        private readonly string $endpoint = '',
    ) {
    }

    public static function fromConfig(): ?self
    {
        // Railway Bucket injects: AWS_S3_BUCKET_NAME, AWS_DEFAULT_REGION, AWS_ENDPOINT_URL,
        // AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY. Fall back to generic names for other setups.
        $bucket    = (string) (getenv('AWS_S3_BUCKET_NAME') ?: getenv('AWS_S3_BUCKET') ?: getenv('BUCKET_NAME') ?: '');
        $accessKey = (string) (getenv('AWS_ACCESS_KEY_ID') ?: getenv('BUCKET_ACCESS_KEY_ID') ?: '');
        $secretKey = (string) (getenv('AWS_SECRET_ACCESS_KEY') ?: getenv('BUCKET_SECRET_ACCESS_KEY') ?: '');

        if ($bucket === '' || $accessKey === '' || $secretKey === '') {
            return null;
        }

        $region        = (string) (getenv('AWS_DEFAULT_REGION') ?: getenv('AWS_REGION') ?: getenv('BUCKET_REGION') ?: 'auto');
        $endpoint      = rtrim((string) (getenv('AWS_ENDPOINT_URL') ?: getenv('AWS_S3_ENDPOINT') ?: getenv('BUCKET_ENDPOINT_URL') ?: ''), '/');
        $publicBaseUrl = rtrim((string) (getenv('S3_PUBLIC_BASE_URL') ?: getenv('BUCKET_PUBLIC_URL') ?: ''), '/');

        if ($publicBaseUrl === '') {
            $publicBaseUrl = $endpoint !== ''
                ? 'https://' . $bucket . '.' . preg_replace('#^https?://#', '', $endpoint)
                : 'https://' . $bucket . '.s3.' . $region . '.amazonaws.com';
        }

        return new self($bucket, $region, $accessKey, $secretKey, $publicBaseUrl, $endpoint);
    }

    /**
     * Apply a public-read bucket policy so all objects are publicly accessible.
     * Idempotent — safe to call on every startup.
     */
    public function ensurePublicReadPolicy(): void
    {
        $host = $this->bucketHost();

        $policy = (string) json_encode([
            'Version' => '2012-10-17',
            'Statement' => [[
                'Sid'       => 'PublicReadGetObject',
                'Effect'    => 'Allow',
                'Principal' => '*',
                'Action'    => 's3:GetObject',
                'Resource'  => 'arn:aws:s3:::' . $this->bucket . '/*',
            ]],
        ]);

        $this->signedRequest('PUT', $host, '/', 'policy=', 'application/json', $policy);
    }

    /**
     * Fetch an object from S3. Returns raw bytes.
     */
    public function get(string $key): string
    {
        $host        = $this->bucketHost();
        $uri         = '/' . ltrim($key, '/');
        $datetime    = gmdate('Ymd\THis\Z');
        $date        = gmdate('Ymd');
        $payloadHash = hash('sha256', '');

        $canonicalHeaders =
            'host:' . $host . "\n" .
            'x-amz-content-sha256:' . $payloadHash . "\n" .
            'x-amz-date:' . $datetime . "\n";
        $signedHeaders = 'host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", ['GET', $uri, '', $canonicalHeaders, $signedHeaders, $payloadHash]);
        $credentialScope  = $date . '/' . $this->region . '/s3/aws4_request';
        $stringToSign     = implode("\n", ['AWS4-HMAC-SHA256', $datetime, $credentialScope, hash('sha256', $canonicalRequest)]);

        $kDate      = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion    = hash_hmac('sha256', $this->region, $kDate, true);
        $kService   = hash_hmac('sha256', 's3', $kRegion, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $scheme  = ($this->endpoint !== '' && str_starts_with($this->endpoint, 'http://')) ? 'http' : 'https';
        $url     = $scheme . '://' . $host . $uri;
        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => implode("\r\n", [
                    'x-amz-content-sha256: ' . $payloadHash,
                    'x-amz-date: ' . $datetime,
                    'Authorization: ' . $authorization,
                ]),
                'ignore_errors' => true,
            ],
        ]);

        $body       = @file_get_contents($url, false, $context);
        $statusCode = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $statusCode = (int) $m[1];
            }
        }
        if ($statusCode !== 200 || $body === false) {
            throw new RuntimeException('S3 GET failed (HTTP ' . $statusCode . ') for key: ' . $key);
        }

        return $body;
    }

    /**
     * Upload a local file to S3. Returns the public URL.
     */
    public function upload(string $localPath, string $key, string $mimeType): string
    {
        $body = file_get_contents($localPath);
        if ($body === false) {
            throw new RuntimeException('Cannot read file for S3 upload: ' . $localPath);
        }

        $host = $this->bucketHost();
        $this->signedRequest('PUT', $host, '/' . ltrim($key, '/'), '', $mimeType, $body);

        return $this->publicBaseUrl . '/' . ltrim($key, '/');
    }

    private function bucketHost(): string
    {
        return $this->endpoint !== ''
            ? $this->bucket . '.' . preg_replace('#^https?://#', '', $this->endpoint)
            : $this->bucket . '.s3.' . $this->region . '.amazonaws.com';
    }

    private function signedRequest(
        string $method,
        string $host,
        string $uri,
        string $queryString,
        string $contentType,
        string $body
    ): void {
        $datetime    = gmdate('Ymd\THis\Z');
        $date        = gmdate('Ymd');
        $payloadHash = hash('sha256', $body);

        $canonicalHeaders =
            'content-type:' . $contentType . "\n" .
            'host:' . $host . "\n" .
            'x-amz-content-sha256:' . $payloadHash . "\n" .
            'x-amz-date:' . $datetime . "\n";
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonicalRequest = implode("\n", [
            $method,
            $uri,
            $queryString,
            $canonicalHeaders,
            $signedHeaders,
            $payloadHash,
        ]);

        $credentialScope = $date . '/' . $this->region . '/s3/aws4_request';
        $stringToSign    = implode("\n", [
            'AWS4-HMAC-SHA256',
            $datetime,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate      = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $kRegion    = hash_hmac('sha256', $this->region, $kDate, true);
        $kService   = hash_hmac('sha256', 's3', $kRegion, true);
        $signingKey = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature  = hash_hmac('sha256', $stringToSign, $signingKey);

        $authorization = 'AWS4-HMAC-SHA256 Credential=' . $this->accessKey . '/' . $credentialScope
            . ', SignedHeaders=' . $signedHeaders
            . ', Signature=' . $signature;

        $scheme = ($this->endpoint !== '' && str_starts_with($this->endpoint, 'http://')) ? 'http' : 'https';
        $url    = $scheme . '://' . $host . $uri . ($queryString !== '' ? '?' . $queryString : '');

        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", [
                    'Content-Type: ' . $contentType,
                    'Content-Length: ' . strlen($body),
                    'x-amz-content-sha256: ' . $payloadHash,
                    'x-amz-date: ' . $datetime,
                    'Authorization: ' . $authorization,
                ]),
                'content'       => $body,
                'ignore_errors' => true,
            ],
        ]);

        $result     = @file_get_contents($url, false, $context);
        $statusCode = 0;
        foreach ($http_response_header ?? [] as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d+)#', $h, $m)) {
                $statusCode = (int) $m[1];
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException(
                'S3 request failed (HTTP ' . $statusCode . '): ' . (is_string($result) ? substr($result, 0, 300) : 'no response')
            );
        }
    }
}
