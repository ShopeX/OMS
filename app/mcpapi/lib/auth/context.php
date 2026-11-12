<?php
/**
 * Copyright 2012-2026 ShopeX (https://www.shopex.cn)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

declare(strict_types=1);

final class mcpapi_auth_context
{
    private string $requestId;
    private string $clientId;
    private string $externalSubject;
    private string $tenantId;
    private string $omsBaseUrl;
    private int $omsSubjectId;
    private array $omsRoleIds;
    private array $omsPermissions;
    private array $scopes;
    private int $expiresAt;
    private bool $isSuper;

    public function __construct(
        string $requestId,
        string $clientId,
        string $externalSubject,
        string $tenantId,
        string $omsBaseUrl,
        int $omsSubjectId,
        array $omsRoleIds,
        array $omsPermissions,
        array $scopes,
        int $expiresAt,
        bool $isSuper = false
    ) {
        $this->requestId = $requestId;
        $this->clientId = $clientId;
        $this->externalSubject = $externalSubject;
        $this->tenantId = $tenantId;
        $this->omsBaseUrl = $omsBaseUrl;
        $this->omsSubjectId = $omsSubjectId;
        $this->omsRoleIds = array_values($omsRoleIds);
        $this->omsPermissions = array_values($omsPermissions);
        $this->scopes = array_values($scopes);
        $this->expiresAt = $expiresAt;
        $this->isSuper = $isSuper;
    }

    public function requestId(): string
    {
        return $this->requestId;
    }

    public function clientId(): string
    {
        return $this->clientId;
    }

    public function subject(): string
    {
        return $this->externalSubject;
    }

    public function externalSubject(): string
    {
        return $this->externalSubject;
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }

    public function omsBaseUrl(): string
    {
        return $this->omsBaseUrl;
    }

    public function omsSubjectId(): int
    {
        return $this->omsSubjectId;
    }

    public function omsRoleIds(): array
    {
        return $this->omsRoleIds;
    }

    public function scopes(): array
    {
        return $this->scopes;
    }

    public function omsPermissions(): array
    {
        return $this->omsPermissions;
    }

    public function expiresAt(): int
    {
        return $this->expiresAt;
    }

    public function isSuper(): bool
    {
        return $this->isSuper;
    }

    public function hasPermission(string $permission): bool
    {
        return $permission !== '' && ($this->isSuper || in_array($permission, $this->omsPermissions, true));
    }
}
