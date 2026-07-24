# TOTPVault Helm Chart

Deploys [TOTPVault](https://github.com/token2/TOTPVault) — a self-hosted TOTP manager with server-side AES-256-GCM encryption — on Kubernetes, with an optional bundled MariaDB or a connection to an external database.

Docker Compose remains the recommended path for local development and simple single-host deployments; this chart is the Kubernetes-native deployment path.

## Prerequisites

- Kubernetes 1.23+
- Helm 3.8+
- A default StorageClass (or set `*.storageClass`) for the session and database volumes
- An Ingress controller (NGINX, Traefik, …) if you enable ingress
- Optionally [cert-manager](https://cert-manager.io) for TLS certificates

The chart pulls `ghcr.io/token2/totpvault`, published by the repository's `docker-publish` GitHub Actions workflow. For air-gapped clusters, mirror that image (and `mariadb:10.11`, plus `busybox:1.36` for `helm test`) into your private registry and override `image.repository` / `mariadb.image.repository`.

## Quick start

```bash
helm install totpvault ./charts/totpvault -n totpvault --create-namespace
```

That works out of the box: the chart deploys MariaDB internally and **generates random values** for `DB_PASSWORD`, `DB_ROOT_PASSWORD`, and `ENCRYPTION_KEY` on first install (they are preserved across upgrades and kept on uninstall). Afterwards:

```bash
kubectl -n totpvault port-forward svc/totpvault 8080:80
open http://localhost:8080
```

> ⚠ **Back up the generated Secret immediately.** `ENCRYPTION_KEY` must never change once tokens are stored — losing or rotating it makes every stored TOTP secret permanently unreadable:
>
> ```bash
> kubectl -n totpvault get secret totpvault -o yaml > totpvault-secret-backup.yaml
> ```

To sign in you must configure at least one login method: a MailerSend API key (magic-link email login) or OAuth/OIDC credentials for Google, Microsoft, GitHub, or Keycloak.

## Production install (recommended pattern)

Create the Secret yourself and point the chart at it — no secrets ever touch `values.yaml` or your Git history:

```bash
kubectl create namespace totpvault

kubectl -n totpvault create secret generic totpvault-secrets \
  --from-literal=DB_PASSWORD="$(openssl rand -base64 18)" \
  --from-literal=DB_ROOT_PASSWORD="$(openssl rand -base64 18)" \
  --from-literal=ENCRYPTION_KEY="$(openssl rand -base64 32)" \
  --from-literal=MAILERSEND_KEY="..." \
  --from-literal=GOOGLE_CLIENT_SECRET="..."
```

`ENCRYPTION_KEY` must be the base64 encoding of exactly 32 random bytes (`openssl rand -base64 32` produces exactly that).

```yaml
# my-values.yaml
app:
  url: https://totp.example.com

secrets:
  existingSecret: totpvault-secrets

oauth:
  google:
    clientId: "1234-abc.apps.googleusercontent.com"

ingress:
  enabled: true
  className: nginx
  annotations:
    cert-manager.io/cluster-issuer: letsencrypt-prod
  hosts:
    - host: totp.example.com
      paths:
        - path: /
          pathType: Prefix
  tls:
    - secretName: totpvault-tls
      hosts:
        - totp.example.com

resources:
  requests:
    cpu: 100m
    memory: 128Mi
  limits:
    memory: 512Mi
```

```bash
helm install totpvault ./charts/totpvault -n totpvault -f my-values.yaml
```

Secret keys recognised in `existingSecret` (they map 1:1 to the app's environment variables):

| Key | Required | Purpose |
|---|---|---|
| `DB_PASSWORD` | yes | Database password for the app user |
| `ENCRYPTION_KEY` | yes | base64 of 32 random bytes — encrypts all TOTP secrets |
| `DB_ROOT_PASSWORD` | when `mariadb.enabled` | MariaDB root password |
| `MAILERSEND_KEY` | optional | Magic-link email login |
| `GOOGLE_CLIENT_SECRET`, `MICROSOFT_CLIENT_SECRET`, `GITHUB_CLIENT_SECRET`, `KEYCLOAK_CLIENT_SECRET` | optional | OAuth/OIDC providers |

External secret managers (External Secrets Operator, Sealed Secrets, Vault) work naturally: have them materialise a Secret with the keys above and set `secrets.existingSecret` to its name.

## Database options

### Bundled MariaDB (default)

`mariadb.enabled: true` deploys a single-replica MariaDB 10.11 StatefulSet with a persistent volume. The schema is initialised automatically on first start from the chart's copy of `init-db.sql` (only when the data volume is empty — the same semantics as Docker Compose).

### External database

```yaml
mariadb:
  enabled: false
externalDatabase:
  host: mariadb.db.svc.cluster.local   # or an RDS/CloudSQL endpoint
  port: 3306
  database: totpvault
  username: totpvault
```

The password still comes from the `DB_PASSWORD` secret key. **You must import the schema yourself** — `schema.sql` from the repository root, plus any `migrations/*.sql` your version requires:

```bash
mysql -h <host> -u totpvault -p totpvault < schema.sql
```

## OAuth / OIDC

Client IDs are plain values; client secrets live in the Secret. Redirect URIs are derived from `app.url` (register `https://totp.example.com/auth/callback/<provider>` with each provider). For Keycloak:

```yaml
oauth:
  keycloak:
    clientId: totpvault
    baseUrl: https://sso.example.com
    realm: myrealm
    # Only when the pod must reach Keycloak through a different in-cluster URL:
    internalBaseUrl: http://keycloak.sso.svc.cluster.local:8080
```

## Persistence and scaling

| Volume | Default | Purpose |
|---|---|---|
| `data` (MariaDB PVC template) | 8Gi RWO | Database files |
| `<release>-sessions` PVC | 1Gi RWO | PHP session files — keeps users logged in across pod restarts |

TOTPVault uses **file-based PHP sessions**, so the default `replicaCount: 1` is the safe configuration. To run more replicas, either:

- give the sessions volume `ReadWriteMany` storage (`persistence.sessions.accessModes: [ReadWriteMany]` with an RWX StorageClass), or
- keep RWO and set `service.sessionAffinity: ClientIP` (best effort; affinity is not preserved across some ingress controllers — check yours).

## Values reference

| Value | Default | Description |
|---|---|---|
| `replicaCount` | `1` | App replicas (see scaling notes) |
| `image.repository` | `ghcr.io/token2/totpvault` | App image |
| `image.tag` | chart `appVersion` | Pin this in production |
| `app.url` | derived from ingress host | Public base URL, no trailing slash |
| `session.cookieName` / `session.lifetime` | `totpvault_session` / `2592000` | Session cookie settings |
| `mail.fromEmail` / `mail.fromName` | `noreply@localhost` / `TOTPVault` | Magic-link sender |
| `oauth.<provider>.clientId` | `""` | Empty disables the provider |
| `oauth.keycloak.*` | `""` | `baseUrl`, `realm`, optional `internalBaseUrl`, `redirectUri`, `scope` |
| `secrets.existingSecret` | `""` | Use a pre-created Secret (recommended) |
| `secrets.*` | `""` | Inline secret values; empty required ones are generated at install |
| `mariadb.enabled` | `true` | Deploy the bundled MariaDB StatefulSet |
| `mariadb.auth.database` / `username` | `totpvault` | Internal DB name/user |
| `mariadb.persistence.size` / `storageClass` | `8Gi` / default class | Database volume |
| `externalDatabase.*` | — | Used when `mariadb.enabled: false`; `host` is required |
| `persistence.sessions.*` | enabled, `1Gi`, RWO | Sessions PVC (`existingClaim` supported) |
| `service.type` / `port` / `sessionAffinity` | `ClusterIP` / `80` / `None` | App Service |
| `ingress.*` | disabled | Standard ingress with `className`, `annotations`, `hosts`, `tls` |
| `resources`, `podSecurityContext`, `securityContext` | mostly empty | See hardening notes |
| `livenessProbe` / `readinessProbe` | HTTP GET `/` | Fully overridable |
| `extraEnv` | `[]` | Extra env vars for the app container |
| `serviceAccount.*`, `nodeSelector`, `tolerations`, `affinity`, `podAnnotations`, `podLabels`, `imagePullSecrets` | — | Standard knobs |

## Security hardening notes

- The app image runs Apache as root (it drops worker processes to `www-data` and binds port 80), so `runAsNonRoot`/`readOnlyRootFilesystem` are not enabled by default. `podSecurityContext.fsGroup: 33` is set so PHP can write session files to the volume.
- The chart-managed Secret carries `helm.sh/resource-policy: keep`, so `helm uninstall` does not delete it (protecting `ENCRYPTION_KEY`). Delete it explicitly only when you intend to destroy all data.
- `serviceAccount.automount` defaults to `false` — the app needs no Kubernetes API access.
- Consider adding a NetworkPolicy restricting MariaDB ingress to the app pods; this is cluster-specific and not templated by the chart.

## Upgrades

```bash
helm upgrade totpvault ./charts/totpvault -n totpvault -f my-values.yaml
```

Things to know before upgrading:

- **`ENCRYPTION_KEY` is immutable in practice.** The chart preserves a generated key across upgrades, but if you manage the Secret yourself, never change this key — all stored TOTP secrets become permanently unreadable.
- **Database credentials only apply on first init.** Like the Compose setup, MariaDB reads `MYSQL_USER`/`MYSQL_PASSWORD` only when its data volume is empty. Changing `DB_PASSWORD` later requires updating the DB user manually (`ALTER USER ... IDENTIFIED BY ...`) or re-initialising the volume (destroys all data).
- **Schema migrations are manual.** When a new app version ships files in `migrations/`, apply them to your database before or right after upgrading the image.
- **Config changes roll pods automatically** via checksum annotations; secrets/config updates take effect on the next rollout.
- Use `helm install/upgrade`, not `helm template | kubectl apply` — offline rendering cannot look up the existing Secret and would regenerate the random values each time.

## Validation

```bash
helm lint charts/totpvault
helm template totpvault charts/totpvault
helm install totpvault ./charts/totpvault -n totpvault --create-namespace --dry-run --debug
helm test totpvault -n totpvault     # after install
```
