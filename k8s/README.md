# Kubernetes deployment

One image, four pod roles, and a release job (spec §3). Manifests are plain Kustomize.

```
k8s/
  base/                   web (Nginx + PHP-FPM), Horizon workers, scheduler, Service, Ingress, HPA, PDBs, ConfigMap
  release/                the migration Job, applied on its own before each rollout
  components/realtime/    Reverb WebSockets (optional, enabled with the live calendar, CAL-07)
  overlays/
    staging/              smaller scale, staging host and database   (+ release/)
    production/           spec baseline: 3 to 12 web pods            (+ release/)
```

| Role | Workload | Command | Scaling |
| --- | --- | --- | --- |
| Web | `hospitable-monolith-web` | image default (Supervisor: Nginx + PHP-FPM) | HPA 3 to 12 on CPU 70%, surge 1, never below desired |
| Worker | `pms-horizon` | `php artisan horizon` | 2 replicas, 900 s grace period for long jobs |
| Scheduler | `pms-scheduler` | `php artisan schedule:work` | exactly 1, `Recreate` |
| Realtime | `pms-reverb` (component) | `php artisan reverb:start` | 2+, Redis pub/sub |
| Release | `pms-release` Job | `php artisan migrate --force --isolated` | once per release |

Worker, scheduler, realtime and release pods set `args`, which replaces the image command but
keeps the entrypoint (`docker/entrypoint.sh`). The entrypoint warms the config, route, event and
view caches from the pod's environment. It never migrates.

Names, the registry (`registry.digitalocean.com/hospitable/pms`), namespaces and hosts
(`pms.example.com`) are the spec's placeholders. Replace them per environment.

## Configuration and secrets

Non-sensitive settings are in the `pms-config` ConfigMap (`base/configmap.yaml`, patched per
overlay). Sensitive values live in a Secret named `pms-secrets`. It is created outside the
repository, never committed (DEP-09). For example:

```sh
kubectl -n production create secret generic pms-secrets \
  --from-literal=APP_KEY="base64:$(openssl rand -base64 32)" \
  --from-literal=DB_USERNAME=pms --from-literal=DB_PASSWORD='…' \
  --from-literal=REDIS_PASSWORD='…' \
  --from-literal=AWS_ACCESS_KEY_ID='…' --from-literal=AWS_SECRET_ACCESS_KEY='…' --from-literal=AWS_ENDPOINT='…' \
  --from-literal=MAIL_USERNAME='…' --from-literal=MAIL_PASSWORD='…'
```

In a real cluster, prefer a secrets operator (External Secrets, Sealed Secrets, or the cloud
provider's store) over hand-made Secrets. `APP_KEY` must be identical across every role and every
release. The realtime component also needs `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET`.

TLS certificates go in the Secret `pms-tls`, for example issued by cert-manager.

## Release sequence (spec §3.6)

```sh
SHA=$(git rev-parse --short HEAD)
IMAGE=registry.digitalocean.com/hospitable/pms

# 1. Build, scan and push an immutable tag (CI builds and scans every commit).
docker build --build-arg APP_VERSION="$SHA" -t "$IMAGE:$SHA" . && docker push "$IMAGE:$SHA"

(cd k8s/overlays/production && kustomize edit set image pms="$IMAGE:$SHA")
(cd k8s/overlays/production/release && kustomize edit set image pms="$IMAGE:$SHA")

# 2. Configuration, then the release job: expand-phase migrations only.
#    Jobs are immutable, so the previous run is removed first.
kubectl apply -k k8s/overlays/production -l app.kubernetes.io/component=config
kubectl -n production delete job pms-release --ignore-not-found
kubectl apply -k k8s/overlays/production/release
kubectl -n production wait --for=condition=complete job/pms-release --timeout=30m

# 3. Workers and scheduler.
kubectl apply -k k8s/overlays/production -l 'app.kubernetes.io/component in (worker,scheduler)'
kubectl -n production rollout status deploy/pms-horizon
kubectl -n production rollout status deploy/pms-scheduler

# 4. Web pods, Service, Ingress, HPA and PDBs (surge-based: never fewer than desired serving).
kubectl apply -k k8s/overlays/production
kubectl -n production rollout status deploy/hospitable-monolith-web

# 5. Smoke test.
curl -fsS https://pms.example.com/up
```

Rollback is `kubectl -n production rollout undo deploy/<name>`. It is always safe because a
release only expands the schema. Contract migrations ship in a later release (spec §4).

## Zero-downtime details

- **Web drain.** The `preStop` sleep (10 s) lets endpoints and the ingress drop the pod. Then
  Nginx and PHP-FPM shut down gracefully (`SIGQUIT`) within the 45 s grace period.
- **Workers.** Horizon finishes or releases running jobs on `SIGTERM`. The 900 s grace period
  covers the longest supervisor timeout (reports: 600 s). The Redis queue's `retry_after`
  (660 s) is longer than that, so a running job is never handed to a second worker.
- **Version skew (§4.4).** Responses carry `X-App-Version`. When it changes, the SPA shows a
  banner and makes the next navigation a full page load. If a lazy chunk from the old release
  is gone, the SPA reloads. The ingress also retries asset 404s once on another pod.
- **Placement.** PodDisruptionBudgets and topology spread keep web and worker pods from all
  being evicted at once (DEP-11).
- **Hardening (DEP-14).** Every pod runs as `www-data` (uid 82) with a read-only root filesystem,
  no privilege escalation and no capabilities. Writable paths are `emptyDir` scratch volumes.
  The web container listens on 8080, and the Service exposes it as port 80.

## Migrations: expand and contract

Every migration is labelled in its class docblock, and CI enforces the rules
(`tests/Architecture/MigrationClassificationTest.php`, DBM-06):

```php
/**
 * Adds guests.first_name and guests.last_name.
 *
 * @migration expand
 */
return new class extends Migration { … };
```

- **Expand** migrations are additive only. They may not drop, rename or change a column,
  drop an index, or add a NOT NULL column without a default to an existing table.
- **Contract** migrations remove what an earlier release stopped using. They ship one release
  after the code change, once any backfill is verified. They never touch append-only tables
  such as `activity_log`.
- Every migration has a `down()`, or explains with `@irreversible` why it cannot have one.
