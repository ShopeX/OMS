<?php
/**
 * JWT Authentication Result Object
 *
 * Encapsulates the result of authentication/refresh operations
 * to avoid controllers parsing raw strings.
 */

class desktop_auth_jwt_result
{
    /** @var bool */
    private bool $authenticated;

    /** @var int|null */
    private ?int $userId;

    /** @var int */
    private int $statusCode;

    /** @var string */
    private string $reason;

    /** @var string */
    private string $message;

    /** @var array */
    private array $data;

    /** @var array */
    private array $cookies;

    /** @var bool */
    private bool $clearCookies;

    /** @var string */
    private string $transportMode;

    /**
     * @param bool $authenticated
     * @param int|null $userId
     * @param int $statusCode
     * @param string $reason
     * @param string $message
     * @param array $data
     * @param array $cookies
     * @param bool $clearCookies
     * @param string $transportMode
     */
    public function __construct(
        bool $authenticated,
        ?int $userId,
        int $statusCode,
        string $reason,
        string $message,
        array $data = [],
        array $cookies = [],
        bool $clearCookies = false,
        string $transportMode = 'browser'
    ) {
        $this->authenticated = $authenticated;
        $this->userId = $userId;
        $this->statusCode = $statusCode;
        $this->reason = $reason;
        $this->message = $message;
        $this->data = $data;
        $this->cookies = $cookies;
        $this->clearCookies = $clearCookies;
        $this->transportMode = $transportMode;
    }

    // Getters

    public function isAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function data(): array
    {
        return $this->data;
    }

    public function cookies(): array
    {
        return $this->cookies;
    }

    public function shouldClearCookies(): bool
    {
        return $this->clearCookies;
    }

    public function transportMode(): string
    {
        return $this->transportMode;
    }

    // Static constructors

    /**
     * Create an authenticated result.
     *
     * @param int $userId
     * @param array $data
     * @return static
     */
    public static function authenticated(
        int $userId,
        array $data = [],
        string $transportMode = 'browser'
    ): self {
        return new static(
            true,
            $userId,
            200,
            'authenticated',
            '认证成功',
            $data,
            [],
            false,
            $transportMode
        );
    }

    /**
     * Create an unauthorized result.
     *
     * @param string $message
     * @param string $reason
     * @param array $data
     * @param bool $clearCookies
     * @return static
     */
    public static function unauthorized(
        string $message = '登录状态已过期',
        string $reason = 'unauthorized',
        array $data = [],
        bool $clearCookies = false
    ): self {
        return new static(
            false,
            null,
            401,
            $reason,
            $message,
            $data,
            [],
            $clearCookies,
            'browser'
        );
    }

    /**
     * Create a forbidden result.
     *
     * @param string $message
     * @param string $reason
     * @param array $data
     * @return static
     */
    public static function forbidden(
        string $message = '拒绝访问',
        string $reason = 'forbidden',
        array $data = []
    ): self {
        return new static(
            false,
            null,
            403,
            $reason,
            $message,
            $data,
            [],
            false,
            'browser'
        );
    }

    /**
     * Create a login succeeded result.
     *
     * @param array $data
     * @param array $cookies
     * @param string $transportMode
     * @return static
     */
    public static function loginSucceeded(
        array $data = [],
        array $cookies = [],
        string $transportMode = 'browser'
    ): self {
        return new static(
            true,
            null,
            200,
            'login_succeeded',
            '登录成功',
            $data,
            $cookies,
            false,
            $transportMode
        );
    }

    /**
     * Create a refresh succeeded result.
     *
     * @param array $data
     * @param array $cookies
     * @param string $transportMode
     * @return static
     */
    public static function refreshSucceeded(
        array $data = [],
        array $cookies = [],
        string $transportMode = 'browser',
        ?int $userId = null
    ): self {
        $resolvedUserId = $userId ?? (int)($data['user_id'] ?? 0);

        return new static(
            true,
            $resolvedUserId > 0 ? $resolvedUserId : null,
            200,
            'refresh_succeeded',
            '刷新成功',
            $data,
            $cookies,
            false,
            $transportMode
        );
    }

    /**
     * Generic API failure (validation, IDAAS challenge, etc.).
     */
    public static function apiError(
        string $message,
        int $statusCode = 400,
        string $reason = 'error',
        array $data = []
    ): self {
        return new static(
            false,
            null,
            $statusCode,
            $reason,
            $message,
            $data,
            [],
            false,
            'browser'
        );
    }

    // Utilities

    /**
     * Convert to array for JSON serialization in transport layer.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'authenticated' => $this->authenticated,
            'userId' => $this->userId,
            'statusCode' => $this->statusCode,
            'reason' => $this->reason,
            'message' => $this->message,
            'data' => $this->data,
            'cookies' => $this->cookies,
            'clearCookies' => $this->clearCookies,
            'transportMode' => $this->transportMode,
        ];
    }
}
