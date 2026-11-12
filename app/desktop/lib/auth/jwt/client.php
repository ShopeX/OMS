<?php
/**
 * JWT Client Identity
 *
 * Identifies requester type (browser/wap/api). MCP authorization is handled
 * independently by MCPAPI client credentials.
 */

class desktop_auth_jwt_client
{
    // Client type constants
    public const TYPE_BROWSER = 'browser';
    public const TYPE_WAP = 'wap';
    public const TYPE_API = 'api';

    // Token source mode constants
    public const MODE_BROWSER_COOKIE = 'browser_cookie';
    public const MODE_WAP_COOKIE = 'wap_cookie';
    public const MODE_HEADER_BODY = 'header_body';

    /** @var string */
    private string $type;

    /**
     * @param string $type
     */
    private function __construct(string $type) {
        $this->type = $type;
    }

    // Factory

    /**
     * Build client identity from current HTTP request.
     *
     * @return static
     */
    public static function fromRequest(): self
    {
        $server = $_SERVER ?? [];

        $clientType = $server['HTTP_X_OMS_CLIENT_TYPE'] ?? '';

        // Determine type
        $type = self::TYPE_BROWSER; // safe default

        if ($clientType === self::TYPE_WAP || $clientType === self::TYPE_API) {
            $type = $clientType;
        }

        // WAP detection by URI pattern (conservative)
        if (self::isWapUri($server)) {
            $type = self::TYPE_WAP;
        }

        return new self($type);
    }

    // Accessors

    public function type(): string
    {
        return $this->type;
    }

    public function isBrowser(): bool
    {
        return $this->type === self::TYPE_BROWSER;
    }

    public function isWap(): bool
    {
        return $this->type === self::TYPE_WAP;
    }

    public function isApi(): bool
    {
        return $this->type === self::TYPE_API;
    }

    // Token source mode

    /**
     * Which token source this client should read from.
     *
     * Controls token delivery/reading isolation:
     *   - browser_cookie : Browser/WAP HttpOnly Cookie (never expose to JS)
     *   - wap_cookie     : WAP-specific cookie keys (wap-token-key / wap-refresh-token-key)
     *   - header_body    : Authorization header or request body token (API clients)
     *
     * @return string
     */
    public function tokenSourceMode(): string
    {
        if ($this->isWap()) {
            return self::MODE_WAP_COOKIE;
        }

        if ($this->isApi()) {
            return self::MODE_HEADER_BODY;
        }

        // Browser and default
        return self::MODE_BROWSER_COOKIE;
    }

    /**
     * Whether the caller should supply a token in the request body or header.
     *
     * @return bool true for API clients that must provide their own token;
     *              false for browser/wap which rely on HttpOnly cookies.
     */
    public function requiresTokenBody(): bool
    {
        return $this->isApi();
    }

    // Private helpers

    /**
     * Determine whether the request targets a WAP endpoint.
     *
     * Conservative: checks REQUEST_URI for typical WAP controller patterns.
     *
     * @param array $server
     * @return bool
     */
    private static function isWapUri(array $server): bool
    {
        $uri = $server['REQUEST_URI'] ?? '';
        // WAP entry points commonly contain /wap/ or app=ctl set to wap controllers
        if (stripos($uri, '/wap/') !== false) {
            return true;
        }
        $script = $server['SCRIPT_NAME'] ?? '';
        if (stripos($script, '/wap/') !== false) {
            return true;
        }
        // Also recognise ?app=wap&ctl=... patterns
        parse_str(parse_url($uri, PHP_URL_QUERY) ?? '', $query);
        if (isset($query['app']) && $query['app'] === 'wap') {
            return true;
        }
        return false;
    }

}
