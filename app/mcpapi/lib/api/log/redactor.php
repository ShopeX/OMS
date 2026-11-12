<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 */

declare(strict_types=1);

final class mcpapi_api_log_redactor
{
    private const REDACTED = '[REDACTED]';

    public function redact(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && $this->isSensitiveKey($key)) {
            return self::REDACTED;
        }
        if (is_array($value)) {
            $redacted = [];
            foreach ($value as $childKey => $childValue) {
                $redacted[$childKey] = $this->redact($childValue, (string) $childKey);
            }
            return $redacted;
        }
        if (is_string($value)) {
            return $this->redactString($value);
        }
        return $value;
    }

    public function redactString(string $value): string
    {
        return (string) preg_replace('/((?:password|token|secret|authorization|session)[^=:\\s]*[=:])\\s*(.*?)(?=\\s+(?:(?:password|token|secret|authorization|session)[^=:\\s]*[=:])|[,;}]|$)/i', '$' . '1' . self::REDACTED, $value);
    }

    private function isSensitiveKey(string $key): bool
    {
        return (bool) preg_match('/(?:password|token|secret|authorization|session|credential|private[_-]?key)/i', $key);
    }
}
