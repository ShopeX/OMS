<?php
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