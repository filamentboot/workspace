# Authenticating requests

To authenticate requests, include an **`Authorization`** header with the value **`"Bearer {YOUR_ADMIN_TOKEN}"`**.

All authenticated endpoints are marked with a `requires authentication` badge in the documentation below.

通过 POST /api/v1/admin/login 接口获取 access_token，填入 Bearer {token}
