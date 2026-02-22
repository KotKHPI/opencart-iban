# OpenCart IBAN (Opendatabot)

Payment extension for:
- **OpenCart 4.x** (**PHP 8.1+**) — sources in `src_oc4/`
- **OpenCart 3.x** (**PHP 7.2+**, but OpenCart 3 itself usually needs PHP 7.4) — sources in `src_oc3/`

Creates an IBAN invoice via Opendatabot and redirects the customer to the invoice page.

## What it does

- Adds a checkout payment method: **IBAN invoice (Opendatabot)**
- Builds invoice payload from the order
- Creates invoice via Opendatabot API and redirects to the invoice page
- Admin settings:
  - IBAN
  - Code (RNOKPPP/EDRPOU)
  - Payment purpose template (supports `{order_id}`)
  - Order Status (when redirecting)
  - Enable/Disable + Sort order

Limitations (current MVP):
- **UAH only**
- `x-client-key` is currently static in code

## Repo layout

- `src_oc4/` — OpenCart 4.x extension sources (installed into `extension/opencart_iban/`)
- `src_oc3/` — OpenCart 3.x `.ocmod.zip` sources (`upload/` structure)
- `scripts/` — build scripts
- `dist/` — built `.ocmod.zip` (gitignored)
- `dev_oc4/` — Docker sandbox store (OpenCart 4.x by git tag)
- `dev_oc3/` — Docker sandbox store (OpenCart 3.x by git tag)

## Build & install (as a store owner would)

Build (OpenCart 4.x):

```bash
./scripts/build-ocmod-zip-oc4.sh
```

Upload/install:
1) Admin → **Extensions → Installer** → upload `dist/opencart_iban.ocmod.zip`
2) Admin → **Extensions → Extensions** → Type: **Payments**
3) **Opendatabot IBAN Invoice** → **Install**
4) **Edit** and set:
   - `IBAN`
   - `Code (RNOKPPP/EDRPOU)`
   - `Payment purpose` (optional; supports `{order_id}`)
   - `Order Status` (recommended: Pending / Awaiting payment)
   - `Status` = Enabled
5) Admin → **Developer Settings** → refresh Theme + Cache

Build (OpenCart 3.x):

```bash
./scripts/build-ocmod-zip-oc3.sh
```

Upload/install:
1) Admin → **Extensions → Installer** → upload `dist/opencart_iban_oc3.ocmod.zip`
2) Admin → **Extensions → Modifications** → click **Refresh**
3) Admin → **Extensions → Extensions** → Type: **Payments**
4) **Opendatabot IBAN Invoice** → **Install**
5) **Edit** and set:
   - `IBAN`
   - `Code (RNOKPPP/EDRPOU)`
   - `Payment purpose` (optional; supports `{order_id}`)
   - `Order Status` (recommended: Pending / Awaiting payment)
   - `Status` = Enabled

## Docker sandboxes (for development)

Prereqs:
- Docker Desktop / Docker Engine + Compose v2

### OpenCart 4.x (dev mode, recommended)

```bash
cp dev_oc4/.env.example dev_oc4/.env
docker compose --env-file dev_oc4/.env -f dev_oc4/docker-compose.yml -f dev_oc4/docker-compose.dev.yml up -d --build
```

- Store: `http://localhost:8080/`
- Admin: `http://localhost:8080/admin/` (default `admin` / `admin`)

This mode mounts local `src_oc4/` into the container, so template/controller changes apply immediately.
You still need to **install the extension once** via Installer (so OpenCart registers it in DB).

Reset sandbox (wipe DB + files):

```bash
docker compose --env-file dev_oc4/.env -f dev_oc4/docker-compose.yml down -v --remove-orphans
```

OpenCart version:

Set `OPENCART_VERSION` in `dev_oc4/.env` (example: `4.0.2.3`), then reset with `down -v`.

### OpenCart 3.x (dev mode, recommended)

```bash
cp dev_oc3/.env.example dev_oc3/.env
docker compose --env-file dev_oc3/.env -f dev_oc3/docker-compose.yml -f dev_oc3/docker-compose.dev.yml up -d --build
```

- Store: `http://localhost:8081/`
- Admin: `http://localhost:8081/admin/` (default `admin` / `admin`)

This mode mounts local extension files from `src_oc3/upload/` into the container.
If you change templates/controllers in OC3, you may need to refresh modifications and clear theme cache in admin.

Reset sandbox (wipe DB + files):

```bash
docker compose --env-file dev_oc3/.env -f dev_oc3/docker-compose.yml down -v --remove-orphans
```

### Currency (UAH)

The payment method appears **only** when the checkout currency is **UAH**.

This applies to both sandboxes and real stores.

1) Admin → **System → Localisation → Currencies** → ensure **UAH** exists and is Enabled
2) Admin → **System → Settings** → edit your store  
3) Tab **Local** → set **Currency = UAH** → Save  
4) In storefront, switch currency to UAH (or clear the `currency` cookie / use incognito)

## Xdebug (optional)

1) In `dev_oc4/.env` (or `dev_oc3/.env`) set:

```bash
XDEBUG_MODE=debug,develop
```

2) Rebuild and recreate the container:

```bash
docker compose --env-file dev_oc4/.env -f dev_oc4/docker-compose.yml -f dev_oc4/docker-compose.dev.yml up -d --build --force-recreate opencart
```

For OpenCart 3.x:

```bash
docker compose --env-file dev_oc3/.env -f dev_oc3/docker-compose.yml -f dev_oc3/docker-compose.dev.yml up -d --build --force-recreate opencart
```

Defaults: `host.docker.internal:9003`.  
Path mapping for IDE:
- OpenCart 4.x: `/var/www/html/extension/opencart_iban` → local `src_oc4/`.
- OpenCart 3.x: `/var/www/html` → local `src_oc3/upload` (mounted per-file in dev compose).

## References

- OpenCart developer guide (extensions): https://opencart.gitbook.io/opencart/developer-guide/extensions
- Opendatabot IBAN invoice (form + API example): https://iban.opendatabot.ua/create-invoice
