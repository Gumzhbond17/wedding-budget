# Production deployment: separate frontend and API hosts

The recommended deployment is the Docker stack, which serves the frontend and the API on one
origin (see the root [README](../../README.md): "Running with Docker" and the production
checklist). This page covers the other layout: the SPA and the API on different subdomains.
Sanctum SPA authentication uses a session cookie, so the browser must treat the frontend and
the API as the same site.

## Domains and HTTPS

- Serve the frontend and the API on **the same parent domain**, e.g. `app.example.com` (Nuxt)
  and `api.example.com` (Laravel). Different registrable domains will not work: the browser
  would treat the session cookie as third-party and drop it.
- **HTTPS only.** Redirect HTTP to HTTPS and send `Strict-Transport-Security` at the proxy.
  Secure cookies are never sent over plain HTTP.
- If TLS ends at a load balancer or reverse proxy, configure trusted proxies so Laravel sees
  `https` (needed for secure cookies and for signed receipt URLs, which are absolute).

## Backend `.env`

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.example.com

FRONTEND_URL=https://app.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com

# Leading dot: the cookie is shared by app.example.com and api.example.com.
SESSION_DOMAIN=.example.com
SESSION_SECURE_COOKIE=true
# HttpOnly and SameSite=lax are the defaults; do not override them.
```

- `FRONTEND_URL` is the only CORS origin (credentials enabled). Never use `*`.
- Keep `APP_KEY` secret and stable: it signs receipt URLs and encrypts sessions.

## Receipts

- Receipts live on the private `receipts` disk (`storage/app/private/receipts`). Keep that path
  out of any web root and on persistent storage. The local disk's `storage/{path}` route is
  disabled on purpose.
- Signed receipt URLs are valid for 2 minutes. The application never logs them. Keep the query
  string out of **web server** access logs too. With nginx:

```nginx
location ^~ /api/v1/receipts/ {
    access_log off;          # or a log_format that uses $uri instead of $request_uri
    try_files $uri /index.php?$query_string;
}
```

## Checklist

- [ ] Frontend and API on subdomains of the same parent domain
- [ ] HTTPS everywhere, HSTS enabled
- [ ] `SESSION_DOMAIN=.example.com`, `SESSION_SECURE_COOKIE=true`
- [ ] `SANCTUM_STATEFUL_DOMAINS` and `FRONTEND_URL` set to the frontend host
- [ ] `APP_DEBUG=false`
- [ ] Receipt URLs excluded from access logs
