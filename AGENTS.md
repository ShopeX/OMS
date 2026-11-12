# OMS System Agents Guide

> Shopex OMS (v6.0.0): Vue 3 + Element Plus frontend (v4.3.0) + PHP 8.0 backend (63 modules).
> Migrating legacy PHP HTML pages to Vue 3, reusing existing PHP backend APIs.

---

## ⚠️ User Preference (CRITICAL)

**The user is lazy — do not make them participate in testing.** You complete the test, then report.

- After implementation, **proactively** use `agent-browser` to verify
- Do not wait for the user to ask
- Emit a test report when done (see "Testing & Verification" below)

---

## 1. Repo Layout & Related Repositories (gotcha)

前后端分离 & MCP 改造（SCRUM-7359）后，本仓库**已无 submodule**（`.gitmodules` 已删除，`frontend/`、`docs/`、`mcp/` 的 gitlink 全部移除）。相关代码分布在**独立仓库**里：

| 路径 / 仓库 | 类型 | 说明 |
|------|------|------|
| `app/` | PHP backend (in-repo) | 后端业务代码 |
| `config/` | PHP runtime config (gitignored for sensitive) | — |
| `$OMS_BASE_DIR/oms-frontend` | **独立仓库** `oms-system-iframeui.git` | Vue3 前端（本仓库不跟踪 `frontend/`） |
| `$OMS_DOCS_DIR` | **独立仓库** `oms-system-docs.git` | L4/L5/L6 文档与 skill |
| `$OMS_BASE_DIR/oms-mcp` | **独立仓库** `oms-system-mcp.git` | OMS MCP server（端口 8787） |
| `upgrade/` | Per-version upgrade scripts | — |
| `tools/` | Misc CLI tools (`composer.phar`, `translation-manager.php`) | — |
| `docker/` | Dockerfile + supervisor configs | — |

改动前端 / 文档 / MCP 时，在**各自仓库内**提交（PR 流程），不要期待本仓库的 submodule ref 更新。

> 本仓库仍跟踪前端构建产物：`index.html` + `assets/`（由 `oms-frontend` 构建后经 `pnpm deploy:assets` 写入，发布动作见 `oms-frontend-tb-workflow`）。

### 路径写法（文档/脚本通用，禁止硬编码机器路径）

各仓库可 clone 到任意目录，文档与脚本中**不要硬编码** `/data/httpd/...` 之类机器路径，用下列变量：

| 变量 | 指向 | 默认 |
|------|------|------|
| `$OMS_BASE_DIR` | 承载各仓库与 task worktree 的父目录 | `/data/httpd` |
| `$OMS_DOCS_DIR` | 文档仓库（既有约定） | `$OMS_BASE_DIR/oms-docs` |
| `$OMS_BASE_DIR/oms` | PHP 后端（本仓库） | — |
| `$OMS_BASE_DIR/oms-frontend` | Vue3 前端独立仓库 | — |
| `$OMS_BASE_DIR/oms-mcp` | MCP Server 独立仓库 | — |
| `$OMS_BASE_DIR/oms-task-<ID>` | 任务临时 worktree | — |
| `$OMS_RELEASE_DOMAIN` | 后端 release 站 / MCP 调用的后端入口 | `oms.local` |
| `$OMS_DEV_DOMAIN` | 后端 develop 站（集成测试站） | `oms-develop.local` |
| `$OMS_FRONTEND_DOMAIN` | 前端 Vite dev server | `oms-frontend.local` |
| `$OMS_MCP_DOMAIN` | MCP HTTP 端点（`/mcp`） | `oms-mcp.local` |

**域名变量约定**：每个环境可自选域名（偏好不同），文档与 skill 只写变量，**本机实际取值**统一记录在 `$OMS_DOCS_DIR/l5-reference/local-dev-environment.md`。新增/更换域名时改那一处 + 上表默认值即可。

例外：代码块中作为**用户可覆盖的环境变量默认值**时，可写实际默认路径（如部署脚本 `OMS_PHP_ROOT || "/data/httpd/oms"`）。

`.gitignore` excludes (irrelevant to you): `vendor/`, `node_modules/`, `config/`, `data/`, `public/`, `.opencode/`, `.hermes/`, `.cursor/`, `.sisyphus/`, `AGENTS.md`, `/openspec/`, `/tools/`.

### 路径与域名变量（文档/脚本通用，禁止硬编码）

| 变量 | 指向 | 默认 |
|------|------|------|
| `$OMS_BASE_DIR` | 承载各仓库与 task worktree 的父目录 | `/data/httpd` |
| `$OMS_DOCS_DIR` | 文档仓库 | `$OMS_BASE_DIR/oms-docs` |
| `$OMS_RELEASE_DOMAIN` | 后端 release 站 / MCP 调用的后端入口 | `oms.local` |
| `$OMS_DEV_DOMAIN` | 后端 develop 站（集成测试站） | `oms-develop.local` |
| `$OMS_FRONTEND_DOMAIN` | 前端 Vite dev server | `oms-frontend.local` |
| `$OMS_MCP_DOMAIN` | MCP HTTP 端点（`/mcp`） | `oms-mcp.local` |

> 每个环境域名可自选；文档与 skill 只写变量，本机实际取值见 `$OMS_DOCS_DIR/l5-reference/local-dev-environment.md`。

---

## 2. Essential Commands

### PHP backend (run from repo root)
```bash
# MANDATORY after changing dbschema/desktop.xml/app.xml/services.xml:
php app/base/cmd update

# KVstore 数据恢复：接口报 "Return value must be of type string, null returned"
# 或 getConf() 返回 null 时执行（把 MySQL sdb_base_kvstore 回放到当前 KVstore 后端）
php app/base/cmd kvrecovery

# Static analysis (level 2; phpstan.neon.dist)
composer phpstan

# Code style
composer phpcs

# Run a single test
php vendor/bin/phpunit tests/{app}/{module}/{Function}Test.php
```

### Frontend (`$OMS_BASE_DIR/oms-frontend`, 独立仓库)
```bash
pnpm dev                # Vite dev server, port 3333, proxies /index.php to PHP
pnpm build:stage        # staging (runs typecheck first)
pnpm build:prod         # production (runs typecheck first)
pnpm lint               # eslint + prettier (lint-staged runs on pre-commit)
pnpm typecheck          # vue-tsc --noEmit --skipLibCheck
pnpm test:unit          # vitest
pnpm test:e2e           # playwright (specs under tests/e2e/)
pnpm check:finder-baseline src/views/ome/delivery/bill.vue  # linter for finder baseline
```

### Task worker (swoole pool)
```bash
php app/taskmgr/sw.php worker:start -d   # whitelist: app/taskmgr/lib/whitelist.php
```

### MCP server (`$OMS_BASE_DIR/oms-mcp`, 独立仓库)
```bash
cd $OMS_BASE_DIR/oms-mcp && pnpm install
pnpm start:stdio  # stdio MCP server
pnpm build        # 编译到 dist/
pnpm typecheck    # 类型检查
# HTTP 模式经 docker compose（docker/docker-compose.yml，端口 8787，nginx 反代 oms-mcp.local）
```
```
Configured in `.cursor/mcp.json` (already wired). Token: `OMS_MCP_TOKEN` (prod only).

---

## 3. Browser / E2E Testing (MANDATORY)

**All interactive exploratory web page verification uses `agent-browser`.** Persistent Playwright regression specs remain required under `frontend/tests/e2e/` for repeatable coverage against the real backend. Playwright also retains the `<input type="file">` upload exception, but it is not an ad hoc replacement for `agent-browser`.

```bash
agent-browser open http://localhost:3333/m-ome/order/active
agent-browser snapshot -i
agent-browser click @e5
agent-browser fill @e8 "admin"
agent-browser close
```

Persistent Playwright specs live under `frontend/tests/e2e/` (real regression suite, run with `pnpm test:e2e`). Interactive exploratory checks use `agent-browser`; persistent specs use Playwright. Both layers are required when a migration adds or changes a persistent E2E path. Auxiliary `*.cjs` and `*.mjs` debug scripts may exist there, but they do not replace committed regression specs.

---

## 4. Copyright Header (REQUIRED)

Every new file and substantive modification must start with the ShopeX Apache 2.0 header (`Copyright 2012-2026 ShopeX`). Exempt: config files (`.eslintrc`, `.prettierrc`, `tsconfig.json`), third-party libs, lockfiles, `*.md`.

---

## 5. PHP Conventions

### Autoload (class name ↔ path)
Underscores become path separators. **No underscores in filenames:**

```
material_goods_category   → app/material/lib/goods/category.php
material_mdl_goods_cat    → app/material/model/goods/cat.php
material_ctl_admin_barcode → app/material/controller/admin/barcode.php
material_finder_barcode   → app/material/lib/finder/barcode.php
```

### Vue API controllers — path layout (commonly misread)
For Vue pages, the API controller class splits as `<app>_ctl_api_<group>_<entity>`. The path mirrors the suffixes:

| Class | File |
|-------|------|
| `material_ctl_api_material_cat` | `app/material/controller/api/material/cat.php` |
| `material_ctl_api_material_channel` | `app/material/controller/api/material/channel.php` |

`api_admin_material_cat` is **NOT** `api/admin/material/cat.php`. Decompose as `material_ctl` + `api` + `material_cat` → `app/material/controller/api/material/cat.php`.

### Capability layer (业务写行为归属)

业务逻辑放在 **`app/<app>/lib/capability/<name>.php`**（类名 `<app>_capability_<name>`），**不**放 controller、**不**放 `app/*/lib/service/`：

- controller 只做：权限注解 / 入参解析 / 响应格式化
- capability 拥有：入参归一化、业务校验、**显式事务**、**业务操作日志**、调 model 持久化
- **CUD 必须把整组写入包在显式事务**：`$this->database->beginTransaction()` / `commit()` / `rollBack()`
- **CUD 必须写 `ome.operation_log` 业务审计**：先在 `<app>_operation_log::get_operations()` 注册 operation，再 `write_log()`（改注册表后跑 `php app/base/cmd update`）
- 操作者身份**显式传入**（不依赖 session，否则 MCP 调用会全记成 `system`）；MCP 出口归属 owner，memo 标注 `via MCP client <client_id>`
- **业务流程直白可读，禁止过度封装**：不建只做转发的 adapter/wrapper，不为单次使用抽 helper，不为"未来可能复用"预留抽象；但**事务边界 / 数据权限过滤 / 操作日志写入必须在该 capability 流程里显式可见**（可抽同类内私有方法，不得下沉到通用层）
- 同一业务需 Vue + MCP 两个出口时必须抽 capability（双出口契约点）

完整架构说明见 `$OMS_DOCS_DIR/l5-reference/capability-layer.md`。

### 权限（接口权限 + 数据权限）

**接口权限**（fail-closed）：

- MCP API 每个业务 controller 方法**必须**带 `#[RequirePermission]` 注解；**缺注解 = 403**
- 权限 ID 用点分隔：`{app}.action.{business}.{operation}`

**数据权限**（P0 风险，MCP 尤其危险）：

- 数据范围判定**必须走 `mcpapi_auth_context`**（`omsSubjectId()` / `isSuper()`），**禁止依赖 session**
- 原因：model 层的 session 式数据权限（如 `ome/model/branch.php:8` 的 `if ($op_id)`）在 MCP 无会话时 **fail-open = 全权限**
- 新增/改动的 capability 必须实现 `applyOrganizationScope` 模式（过滤列表 + 校验单行 + 无权限置 `'false'`）
- 前端 `v-permission` / `checkPermission` 是**角色级**，**不是**权限 ID，**不得**当安全边界；细粒度权限以后端为准

完整机制、风险清单与实现范式见 `$OMS_DOCS_DIR/l5-reference/capability-layer.md` §数据权限。

### Permission IDs (desktop.xml)
Format `{app}.action.{business}.{operation}` — **dots, not underscores**:
```xml
<permission id="material.action.goods_category.add">基础物料分类新增</permission>
```

### Database schema
PHP array format (NOT XML) in `app/{module}/dbschema/*.php`. See existing files for `type`/`label` shape.

### Data model: material / warehouse mapping (IMPORTANT)
The canonical material/SKU table is **`material.basic_material`** (`app/material/dbschema/basic_material.php`): PK `bm_id`, code `material_bn`. The legacy **`ome.products`** table is **deprecated — do not join it**.

- `ome.branch_product.product_id` = `material.basic_material.bm_id` (仓库物料关联)
- `ome.sales_items.product_id` = `material.basic_material.bm_id` (NOT `products.product_id`)
- `ome.sales_items.bn` = sales material code (`sales_material_bn`), NOT `material_bn`
- `material.sales_basic_material` maps `sm_id` (sales material) ↔ `bm_id` (basic material), one-to-many

### PHPUnit setUp()
Always load OMS framework inside `setUp()`, not at file top:
```php
protected function setUp(): void {
    parent::setUp();
    chdir(__DIR__ . '/../../..');
    include_once 'config/config.php';
    include_once 'app/base/defined.php';
    include_once 'app/base/kernel.php';
}
```

---

## 6. Vue Migration Conventions

### Standard components (MANDATORY — NOT native Element Plus)
Use Finder components in `frontend/src/components/Finder/`:
- `FinderFilter`, `FilterItem` — filter bar
- `FinderTable` — data table
- `FinderPagination` — pagination
- `FinderColPicker` — column picker
- `FinderTabs` — tabs (for Finder views)
- `FinderPageTemplate` — page-level wrapper
- `FinderFormDialog`, `FinderImportDialog`, `FinderExportDialog` — action dialogs

**Forbidden in new code**: `el-table`, `el-pagination`, `el-form`, `el-form-item`, `el-checkbox`. (Existing pages still have them — don't churn.)

### vueComponent path (desktop.xml)
Path **omits** appId; the loader adds it:
```php
// Wrong: 'vueComponent' => 'wmsmgr/admin/wms/add'
'vueComponent' => 'admin/wms/add'
```

### Filename / import naming
- Filename: camelCase (`bindShop.vue`)
- Import name: PascalCase (`import BindShop from './bindShop.vue'`)
- All interactive elements need `data-testid`

### Reference pages
- `frontend/src/views/desktop/roles/index.vue` — substantive Finder page with tabs, filters, actions, row expansion, and dialogs
- `frontend/src/views/logistics/area/index.vue` — Finder CRUD and `FinderFormDialog` reference

Do not use `frontend/src/views/taoexlib/ietask/index.vue` as a canonical example; it is currently an incomplete stub.

### HTTP requests
```typescript
import { request } from '@/utils/service'   // NOT raw axios
request({ url, method, data })
```
- URL params: `p[N]` array format (OMS framework convention)
- `Content-Type`: JSON for list/GET; `application/x-www-form-urlencoded` for form submits
- **Legacy controllers** (those using `begin`/`end` transactions) **must** use form-urlencoded, NOT JSON

### Frontend code style (enforced by `pnpm lint`)
- Prettier: 120 char width, 2-space indent, **no semicolons**, single quotes
- ESLint `eslint.config.js` enables `vue/no-v-html: error` with a per-file override list (currently ~30 legacy files). When you fix one, remove its path from the override. Master tracking: `$OMS_DOCS_DIR/migration/notes/v-html-cleanup-tracking.md`.
- Custom rules under `frontend/eslint-rules/finder/` (load-data-timing, filter-reset, tab-change-param) — currently disabled but available.

### Frontend lint-staged / husky
- `frontend/.husky/pre-commit` runs `npx lint-staged`
- `lint-staged` runs eslint --fix + prettier --write on staged `*.{vue,js,jsx,ts,tsx}`
- `frontend/.npmrc` uses `shamefully-hoist = true` (component types)

---

## 7. Finder Views / Tabs — Implementation Gotchas

### API controller passes views inline
Views go directly in `finderDispatch()` — do **not** extract to `buildViewDefinitions()`:
```php
$this->finderDispatch([
    'objectName' => 'ome_mdl_delivery_bill',
    'finderAlias' => 'delivery-bill',
    'views' => [
        ['id' => 'all', 'label' => '全部', 'filter' => [], 'optional' => false],
        ['id' => 'got_exception', 'label' => '揽收异常', 'filter' => ['exception_code' => 'GOT_EXCEPTION'], 'optional' => false],
    ],
]);
```

### view_id pattern (recommended)
Frontend passes `view_id`, backend resolves the filter from the view definition:
```typescript
fetchDeliveryBillList({ view_id: currentTab.id, filters })
```

### loadTabs → loadData ordering
`loadData()` must be called AFTER tabs are loaded (not in `onMounted`):
```typescript
const loadTabs = async () => {
    const res = await fetchDeliveryBillTabs()
    if (res?.length) {
        tabs.value = res.filter((t) => t.addon > 0)
        loadData()   // ← called here
    }
}
onMounted(loadTabs)
```

### FinderTabs `@change` event
Emits the **tab object**, not the id:
```typescript
const handleTabChange = (tab: FinderTab) => { activeTab.value = tab.id; loadData() }
```

### API response interceptor (service.ts) auto-unwraps
After interceptor, `res` is already `apiData.data` — there is no `{ rsp, data }` wrapper:
```typescript
// Wrong
const res = await request<{ rsp: string; data: { tabs } }>({...})
return res.rsp === "succ" ? res.data?.tabs ?? [] : []

// Correct
const res = await request<{ tabs: FinderTab[] }>({...})
return res?.tabs ?? []
```

---

## 8. Common Pitfalls (quick checklist)

1. Forgot `php app/base/cmd update` after `dbschema/` / `desktop.xml` / `app.xml` / `services.xml` changes
2. Underscore left in PHP filename (must convert all underscores to `/`)
3. Used raw `axios` instead of `@/utils/service` `request()`
4. `vueComponent` path includes appId prefix
5. PHPUnit loads framework at file top instead of `setUp()`
6. Permission ID uses underscores in `desktop.xml` (use dots)
7. Missing ShopeX copyright header
8. JSON `Content-Type` against legacy `begin/end` controller (use form-urlencoded)
9. Called `loadData()` in `onMounted` instead of after `loadTabs`
10. Read `res.rsp === "succ"` on already-unwrapped response
11. Edited files inside the separate repos (`$OMS_BASE_DIR/oms-frontend`, `$OMS_DOCS_DIR`, `$OMS_BASE_DIR/oms-mcp`) and expected this repo to track them — they are independent git repos, commit there
12. Used `el-table`/`el-pagination`/`el-form-item` in new code (use Finder components)

---

## 9. Database / Environment

- MySQL 8.0+: set `sql_mode` to include `only_full_group_by`
- URL routing: `m-*` paths and `/home` rewrite to `index.html` (Vue SPA). See `README.md` for full Apache/Nginx vhost configs (deny access to `/.git`, `/script`, `/data`, `/config`, `/vendor`, `/cgi-bin`, `/tools`)
- Dev: map `oms.local` → 127.0.0.1 (backend) and `oms-frontend.local` → 127.0.0.1 (Vite) so the proxy works
- 本地环境真相（域名地图 / 容器网络 / KVstore 恢复 / Vite CSRF 约束）见 `$OMS_DOCS_DIR/l5-reference/local-dev-environment.md`

---

## 10. Commit Convention

Format enforced by `commitlint.config.js`:
```
[TBID:{tbid}] {type}{scope}: {subject}
```
Where `tbid` is required (a business ticket id). Types: `feat`, `fix`, `perf`, `style`, `refactor`, `revert`, `test`, `docs`, `chore`, `workflow`, `ci`, `types`, `wip`.

Setup the hook once: download the template repo and symlink `template/hooks/commit-msg` to `.git/hooks/commit-msg`, then `chmod +x` it. (Note: `commitlint.config.js` is in `.gitignore` — the hook reads it via the symlinked repo.)

---

## 11. Project-Specific Skills & Long-form Docs

Custom skills live in (location depends on agent harness):
- **Hermes**: `$OMS_DOCS_DIR/l4-playbooks/skills/`（`~/.hermes/config.yaml` 的 `skills.external_dirs` 已指向该目录）
- **OpenCode**: 用户级 `skills.paths` 指向 `$OMS_DOCS_DIR/l4-playbooks/skills`（递归扫描 `**/SKILL.md`，一条配置挂载全部 skill）。一次性挂载/校验/撤销：`$OMS_DOCS_DIR/ops/setup-agent-skills.sh`。`.opencode/skills/` 仅放项目级 skill（如 `openspec-*`，gitignored）；**`opencode.json` 项目级配置不存在，勿再引用**

Key skills (same source of truth: `$OMS_DOCS_DIR/l4-playbooks/`):
- `oms-html-to-vue-migration` — legacy 菜单页 → Vue3（迁移流程 + Finder 细则 + 审查验收 + 架构与扩展性）
- `oms-frontend-tb-workflow` — 前端标品 TB 流程（含跨仓库产物发布）
- `oms-mcp-service` — OMS MCP 服务架构/工具实现/部署
- `oms-mcp-tb-workflow` — MCP 功能的标品 TB 流程（三仓库合并）
- `tb-dev-workflow` — 标品 TB 4 阶段分治（PHP 后端）
- `oms-project-task-workflow` / `oms-independent-git-workflow` — 项目线流程

Long-form references (read on demand, never bulk):
- `$OMS_DOCS_DIR/l4-playbooks/` — how-to playbooks + skills
- `$OMS_DOCS_DIR/l5-reference/` — long checklists
- `$OMS_DOCS_DIR/business/` — domain maps
- `$OMS_DOCS_DIR/oms-mcp/` — MCP skill marketplace + tool catalog

> **MD vs code conflicts**: trust the code; flag stale docs as `STALE_DOC`.

---

## 12. Agent Safety (P0)

**No bulk-edit scripts** across the repo. One task = one file (or a small, explicitly-named set).

- Forbidden: Python/shell/sed doing multi-file find-replace or glob-driven rewrites against business source
- Allowed: read-only audits, writing to `data/migration/*.csv`, verification reports
- E2E specs go in `frontend/tests/e2e/**` (specs/utils/verify) only
- If you discover prior broken commits, revert from the last clean git state

---

## 13. Quick Architectural Map

```
oms-system-beta/
├── app/                     PHP backend (63 modules — logistics, wms, material, ome, ...)
│   ├── base/                Framework core: kernel, autoload, cmd
│   └── {module}/            controller/, model/, lib/, dbschema/, desktop.xml
├── frontend/                Vue 3 SPA (Element Plus, Pinia, vue-router, dayjs, lodash-es)
│   ├── src/views/           Pages organized by module
│   ├── src/components/Finder/  Standard Finder primitives (use these!)
│   ├── src/api/             API request modules
│   ├── src/router/          vue-router config
│   ├── src/utils/service.ts Unified request wrapper
│   ├── tests/e2e/           Playwright specs (real suite exists)
│   └── tests/unit/          Vitest specs
├── mcp/                     OMS MCP server (StreamableHTTP :8787)
├── docs/                    Submodule with l4-playbooks, l5-reference, business, oms-mcp
├── upgrade/                 Per-version upgrade scripts (5.0.0 → 6.0.0+)
├── config/                  PHP runtime config (gitignored sensitive bits)
└── tests/                   PHP PHPUnit tests
```

When something looks missing, check whether it's actually in a submodule (`frontend/`, `docs/`, `mcp/`) before declaring it absent.
