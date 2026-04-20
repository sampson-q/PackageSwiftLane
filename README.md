# PackageSwiftLane

## API

The repository now includes a production API entrypoint at:

- `api/index.php` (served as `/api/v1/*`)

### What is included

- Token auth (`cdb_api_user_tokens`) with login, OTP challenge/verify, forgot/reset password OTP, refresh, logout, `/auth/me`, and `/auth/permissions`
- RBAC authorization via existing `cdb_user_role_permissions` and `cdb_user_module_actions`
- Agency/customer/driver tenant scoping aligned with existing userlevel and `agency_id` rules
- Consistent JSON envelopes and error handling
- Pagination/filter/sort support (`page`, `per_page`, `search`, `sort`, `order`) on list endpoints
- Core domain endpoints for users, customers, recipients, shipments, packages, pickups, consolidations, prealerts, payments, notifications, templates, catalogs, settings, and public tracking
- API administration endpoints for sessions, CORS origins, and API logs export

### Documentation

- OpenAPI spec: `api/openapi.yaml`

### Routing

- `.htaccess` in `api/` rewrites all non-file API requests to `api/index.php`

### Lightweight tests

- Utility test script: `api/tests/ApiKernelUtilsTest.php`
