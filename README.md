# OpenCart IBAN (Opendatabot)

Payment extension for **OpenCart 4.x** (**PHP 8.1+**) that creates an IBAN invoice via Opendatabot and redirects the customer to the invoice page.

## What it does

- Adds a checkout payment method: **IBAN invoice (Opendatabot)**
- Builds invoice payload from the order (amount + purpose + IBAN + company code)
- Creates invoice via Opendatabot API and redirects to the invoice page
- Admin settings:
  - IBAN
  - Company code (RNOKPPP/EDRPOU)
  - Payment purpose template (supports `{order_id}`)
  - Order Status (when redirecting)
  - Enable/Disable + Sort order

Limitations (current MVP):
- **UAH only**
- `x-client-key` is currently static in code
- No callback/webhook yet (store won’t know if invoice was paid)

## Repo layout

- `src/` — extension sources (installed into `extension/opencart_iban/`)
- `scripts/` — build scripts
- `dist/` — built `.ocmod.zip` (gitignored)
- `dev/` — Docker sandbox store (OpenCart by git tag)

## Quick start (Docker sandbox)

Prereqs:
- Docker Desktop / Docker Engine + Compose v2

### Run (dev mode, recommended)

```bash
cp dev/.env.example dev/.env
docker compose --env-file dev/.env -f dev/docker-compose.yml -f dev/docker-compose.dev.yml up -d --build
```

- Store: `http://localhost:8080/`
- Admin: `http://localhost:8080/admin/` (default `admin` / `admin`)

This mode mounts local `src/` into the container, so template/controller changes apply immediately.
You still need to **install the extension once** via Installer (so OpenCart registers it in DB).

### Reset sandbox (wipe DB + files)

```bash
docker compose --env-file dev/.env -f dev/docker-compose.yml down -v --remove-orphans
```

### OpenCart version

Set `OPENCART_VERSION` in `dev/.env` (example: `4.0.2.3`), then reset with `down -v`.

## Build & install (as a store owner would)

Build:

```bash
./scripts/build-ocmod-zip.sh
```

Upload/install:
1) Admin → **Extensions → Installer** → upload `dist/opencart_iban.ocmod.zip`
2) Admin → **Extensions → Extensions** → Type: **Payments**
3) **Opendatabot IBAN Invoice** → **Install**
4) **Edit** and set:
   - `IBAN`
   - `Company code`
   - `Payment purpose` (optional; supports `{order_id}`)
   - `Order Status` (recommended: Pending / Awaiting payment)
   - `Status` = Enabled
5) Admin → **Developer Settings** → refresh Theme + Cache

## Currency (UAH)

The payment method appears **only** when the checkout currency is **UAH**.

### Docker sandbox default (recommended)

In `dev/.env.example` the default is:

```bash
OPENCART_CURRENCY=UAH
```

It is applied automatically **on first install** of the sandbox store.
If you already installed the sandbox with another currency, either:
- change it in admin (below), or
- reset the sandbox with `down -v` and start again.

### Change currency in an existing store

1) Admin → **System → Settings** → edit your store  
2) Tab **Local** → set **Currency = UAH** → Save  
3) Admin → **System → Localisation → Currencies** → ensure **UAH** is Enabled  
4) In storefront, switch currency to UAH (or clear the `currency` cookie / use incognito)

## Xdebug (optional)

1) In `dev/.env` set:

```bash
XDEBUG_MODE=debug,develop
```

2) Rebuild and recreate the container:

```bash
docker compose --env-file dev/.env -f dev/docker-compose.yml -f dev/docker-compose.dev.yml up -d --build --force-recreate opencart
```

Defaults: `host.docker.internal:9003`.  
Path mapping for IDE: `/var/www/html/extension/opencart_iban` → local `src/`.

## References

- OpenCart developer guide (extensions): https://opencart.gitbook.io/opencart/developer-guide/extensions
- Opendatabot IBAN invoice (form + API example): https://iban.opendatabot.ua/create-invoice
