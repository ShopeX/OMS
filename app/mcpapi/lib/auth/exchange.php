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

final class mcpapi_auth_exchange
{
    private mcpapi_auth_client_registry $registry;
    private mcpapi_auth_token_issuer $issuer;
    private mcpapi_auth_owner $owner;

    public function __construct(
        mcpapi_auth_client_registry $registry,
        mcpapi_auth_token_issuer $issuer,
        ?mcpapi_auth_owner $owner = null
    )
    {
        $this->registry = $registry;
        $this->issuer = $issuer;
        $this->owner = $owner ?? new mcpapi_auth_owner();
    }

    public function issue(mcpapi_gateway_identity $identity, ?int $now = null): string
    {
        $issuedAt = $now ?? time();
        $record = $this->registry->find($identity->clientId());
        if ($record === null || $record->clientId() !== $identity->clientId() || !$record->isUsableAt($issuedAt)) {
            throw new mcpapi_auth_exception();
        }
        $authorization = [
            'client_id' => $record->clientId(),
            'tenant_id' => $record->tenantId(),
            'oms_base_url' => $record->omsBaseUrl(),
            'oms_subject_id' => $record->omsSubjectId(),
            'oms_role_ids' => $record->omsRoleIds(),
            'oms_permissions' => $record->omsPermissions(),
            'scope' => $record->scopes(),
            'external_subject' => $identity->subject(),
            'is_super' => false,
        ];
        if ($record->ownerUserId() > 0) {
            $authorization = array_merge($authorization, $this->owner->resolve($record->ownerUserId()));
            $authorization['scope'] = $this->owner->limitScopes(
                $record->scopes(),
                $authorization['oms_permissions'],
                $authorization['is_super']
            );
        }

        return $this->issuer->issue($authorization, $issuedAt);
    }
}
