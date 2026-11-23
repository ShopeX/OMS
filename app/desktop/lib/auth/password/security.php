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

/**
 * Password-change timestamping and JWT revocation sequencing.
 */
class desktop_auth_password_security
{
    public static function stampPasswordChangedAt(array $saveData, ?int $changedAt = null): array
    {
        $saveData['pam_account']['password_changed_at'] = $changedAt ?? time();
        return $saveData;
    }

    public static function revokeAfterSuccessfulChange(int $userId, callable $revoke): void
    {
        $revoke($userId);
    }

    public static function complete(
        int $userId,
        array $saveData,
        callable $persist,
        callable $revoke,
        ?int $changedAt = null
    ): bool {
        $saveData = self::stampPasswordChangedAt($saveData, $changedAt);
        if ($persist($saveData) === false) {
            return false;
        }

        self::revokeAfterSuccessfulChange($userId, $revoke);
        return true;
    }
}