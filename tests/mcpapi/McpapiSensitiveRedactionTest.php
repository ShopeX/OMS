<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class McpapiSensitiveRedactionTest extends TestCase
{
    public function testApiLogWriterRedactsSecretsFromRequestAndResponseBeforePersistence(): void
    {
        $root = dirname(__DIR__, 2);
        require_once $root . '/app/mcpapi/lib/api/log/writer/contract.php';
        require_once $root . '/app/mcpapi/lib/api/log/redactor.php';
        require_once $root . '/app/mcpapi/lib/api/log/writer.php';

        $model = new class {
            public array $rows = [];

            public function gen_id(): string
            {
                return 'redaction-log-id';
            }

            public function insert(array &$data): bool
            {
                $this->rows[] = $data;
                return true;
            }
        };

        $writer = new mcpapi_api_log_writer($model);
        $writer->write([
            'path' => '/mcp-api/v1/user/create',
            'method' => 'POST',
            'request' => [
                'pam_account' => [
                    'login_password' => 'Plaintext-Sentinel-123',
                    'password_hash' => 'Hash-Sentinel-456',
                    'session_token' => 'Token-Sentinel-789',
                ],
            ],
            'response_body' => [
                'user' => [
                    'password' => 'Response-Sentinel-000',
                    'secret' => 'Secret-Sentinel-111',
                ],
            ],
            'message' => 'password=Plaintext-Sentinel-123 password=Password With Spaces',
        ]);

        $serialized = json_encode($model->rows[0]);
        $this->assertStringNotContainsString('Plaintext-Sentinel-123', $serialized); $this->assertStringNotContainsString('Password With Spaces', $serialized); $this->assertStringNotContainsString('With Spaces', $serialized);
        $this->assertStringNotContainsString('Hash-Sentinel-456', $serialized);
        $this->assertStringNotContainsString('Token-Sentinel-789', $serialized);
        $this->assertStringNotContainsString('Response-Sentinel-000', $serialized);
        $this->assertStringNotContainsString('Secret-Sentinel-111', $serialized);
        $this->assertStringContainsString('[REDACTED]', $serialized);
    }
}
