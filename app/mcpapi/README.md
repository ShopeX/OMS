# MCPAPI

The MCPAPI router handles the `/mcp-api/v1` application boundary through explicit deployment routing. Runtime URL mapping remains deployment configuration; the generated `config/mapper.php` file is not edited by this application.

Deployment must preserve the HTTP method and complete `/mcp-api/v1/*` request path. The application router resolves only known controllers and actions, rejects traversal and encoded separators, and returns `404` for the retired `/mcp-api/v1/jwks` and `/mcp-api/v1/internal/exchange` routes.

## OMS client credentials

MCP tools authenticate to business routes with:

```http
Authorization: Basic base64(client_id:client_secret)
```

OMS stores only the client secret hash. Client credentials are created, rotated, or revoked from `修改密码 → MCP接入凭证`; a newly generated secret is displayed once.

Each client is bound to its owner desktop user. On every authenticated request, OMS resolves the owner's current roles and permissions and limits the client's scopes accordingly. Revoked clients and disabled owners are rejected immediately.

## Routes

- `GET /mcp-api/v1/health` validates the client credential and returns `{ "status": "ok" }`.
- Other `/mcp-api/v1/*` business routes require the same Basic credential and apply controller permission checks.
- `GET/POST /mcp-api/client` and its rotate/revoke actions use the current browser-authenticated account and CSRF protection rather than MCP credentials.

MCPAPI request and response bodies use `application/json`, except approved file-import actions which use `multipart/form-data`. Adding a business controller follows the router's controller/action conventions; request input cannot select an owner, tenant, role, scope, or backend endpoint.
