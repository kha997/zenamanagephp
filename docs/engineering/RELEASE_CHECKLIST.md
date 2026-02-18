# Release Checklist

This checklist is for onboarding a new developer and for staging bootstrap.
It does not change domain/business logic.

## 1) Required environment variables

### Tenant and app context
- `APP_ENV` (must be `local`, `development`, `dev`, or `testing` for local bootstrap script)
- `APP_URL`
- `APP_KEY`

Notes:
- Tenant isolation for protected APIs uses `X-Tenant-ID` header.
- Default seeded tenant domain is `zena.local`.

### Auth
- `SANCTUM_STATEFUL_DOMAINS`
- `SESSION_DOMAIN`
- `SESSION_DRIVER`
- `JWT_SECRET` (recommended; falls back to `APP_KEY` if omitted)

### Database
- `DB_CONNECTION`
- `DB_HOST`
- `DB_PORT`
- `DB_DATABASE`
- `DB_USERNAME`
- `DB_PASSWORD`

### Redis (optional)
- `REDIS_HOST`
- `REDIS_PORT`
- `REDIS_PASSWORD`
- Optional usage toggles:
- `CACHE_DRIVER`
- `QUEUE_CONNECTION`
- `SESSION_DRIVER`

## 2) Setup steps

From repo root:

```bash
composer install
npm install
npm run build

cp .env.example .env   # if .env does not exist
php artisan key:generate

php artisan migrate --force
php artisan db:seed --class=Database\\Seeders\\DatabaseSeeder --force
```

## 3) Demo tenant + system_admin user

Default seed data already creates:
- tenant: `ZENA Company` with domain `zena.local`
- user: `admin@zena.local` / `password`
- role assignment: `System Admin`

If you need to create/update explicitly:

```bash
php artisan tinker --execute="
\$tenant = App\\Models\\Tenant::firstOrCreate(
  ['domain' => 'zena.local'],
  ['name' => 'ZENA Company', 'slug' => 'zena-company', 'is_active' => true, 'status' => 'active']
);
\$user = App\\Models\\User::updateOrCreate(
  ['email' => 'admin@zena.local'],
  ['name' => 'Admin User', 'password' => Illuminate\\Support\\Facades\\Hash::make('password'), 'tenant_id' => \$tenant->id, 'is_active' => true]
);
\$role = App\\Models\\Role::firstOrCreate(['name' => 'System Admin'], ['scope' => 'system', 'allow_override' => true, 'description' => 'System Administrator', 'is_active' => true]);
Illuminate\\Support\\Facades\\DB::table('user_roles')->updateOrInsert(
  ['user_id' => \$user->id, 'role_id' => \$role->id],
  ['created_at' => now(), 'updated_at' => now()]
);
"
```

## 4) Smoke test API calls

Set base URL:

```bash
export BASE_URL="http://127.0.0.1:8000"
```

Useful route names:
- `api.zena.api.health` -> `GET /api/zena/health`
- `api.zena.auth.login` -> `POST /api/zena/auth/login`
- `api.zena.auth.me` -> `GET /api/zena/auth/me`
- `projects.index` -> `GET /api/projects`

### 4.1 Public health

```bash
curl -sS "$BASE_URL/api/zena/health" | jq .
```

### 4.2 Login and capture token

```bash
TOKEN=$(curl -sS -X POST "$BASE_URL/api/zena/auth/login" \
  -H 'Content-Type: application/json' \
  -d '{"email":"admin@zena.local","password":"password"}' \
  | jq -r '.data.token // .token // empty')

echo "$TOKEN"
```

### 4.3 Resolve tenant id for header

```bash
TENANT_ID=$(php artisan tinker --execute="echo optional(App\\Models\\Tenant::where('domain','zena.local')->first())->id;" | tail -n 1)
echo "$TENANT_ID"
```

### 4.4 Authenticated profile check

```bash
curl -sS "$BASE_URL/api/zena/auth/me" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  | jq .
```

### 4.5 Authenticated project list check

```bash
curl -sS "$BASE_URL/api/projects" \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Tenant-ID: $TENANT_ID" \
  | jq .
```

## 5) Quality and test commands

```bash
composer ssot:lint
composer lint:domain-ownership
COMPOSER_PROCESS_TIMEOUT=0 composer test:fast
composer test:nightly
```

## 6) One-command local bootstrap

```bash
bash scripts/bootstrap_local.sh
```
