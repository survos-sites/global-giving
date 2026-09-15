# Giving Atlas

A modern explorer of GlobalGiving projects and nonprofits, with attributed EIN-based research and a local vector-search experiment. Independent of GlobalGiving; not endorsed by it.

PHP 8.5 · Symfony 8.1 · Doctrine ORM 3 · API Platform 4 · Tabler · AssetMapper · field-bundle · Meilisearch. See [OVERVIEW.md](OVERVIEW.md), [PLAN.md](PLAN.md), and `~/sites/showcase/CONVENTIONS.md`.

## Local setup

Keep this checkout at `~/sites/global-giving` alongside `~/sites/mono`. The Composer path repositories intentionally use the local Survos source while the client/bundle revival is reviewed. No fabricated package version is a published release: `2.99.0` is a local Composer path alias only.

```sh
composer install --no-scripts
```

Set `.env.local` (gitignored):

```dotenv
APP_SECRET=generate-a-random-secret
DATABASE_URL="sqlite:///%kernel.project_dir%/var/global-giving.db"
GLOBAL_GIVING_API_KEY=your-globalgiving-key
MEILI_SERVER=http://127.0.0.1:7700
MEILI_ADMIN_KEY=your-admin-key
MEILI_SEARCH_KEY=your-search-only-key
MEILI_LAB_SEARCH_KEY=your-lab-search-only-key
MEILI_PREFIX=gg_
```

Create search keys restricted to `search`: the catalog key allows only `gg_projects` and `gg_organizations`; the lab key allows only `gg_lab`. Never put the admin key into browser configuration. Obtain a GlobalGiving key through https://www.globalgiving.org/dy/v2/user/api/ .

```sh
php bin/console cache:clear
php bin/console assets:install public
php bin/console importmap:install
# Only for the disposable SQLite database configured above:
php bin/console doctrine:schema:update --force
php bin/console app:import --dataset=organizations --no-debug
php bin/console app:import --dataset=projects --no-debug
php bin/console app:index --no-debug
symfony server:start -d
```

The tracked database default uses the shared Postgres container on port 5434, database `global_giving`. The baseline in `migrations/Version20260915023000.php` targets a fresh PostgreSQL database and has not been applied. Review it before applying it to Postgres; the schema-update command above is only for disposable SQLite. Production secrets belong in deployment configuration, never the repository.

## Commands

Commands are methods on service classes, with typed MapInput DTOs where appropriate.

- `app:import`: defaults to active projects. `--dataset=projects` includes history, `--dataset=organizations` imports all organizations. `--refresh` downloads a fresh snapshot; otherwise validated remote snapshots are reused for one day.
- `app:import --file=/path/to/projects.xml --dataset=projects --limit=100`: validates the complete XML file, then imports at most 100 records. Local-file imports neither replace the current remote-snapshot pointer nor retire unseen projects.
- `app:index`: builds new indexes from database records and Field metadata, waits for successful Meilisearch tasks, then swaps each completed index into place.
- `app:status`: reports database counts.
- `app:research --limit=10`: fetches ProPublica records for exact valid EINs; `--organization=46` targets a specific GlobalGiving organization. API responses are cached for a day. Name similarity is never treated as identity.
- `app:lab --limit=200`: creates the bounded local vector experiment described below.
- `castor refresh`: refresh organizations and all projects, then rebuild search indexes.
- `castor check`: PHPUnit, PHPStan, container/Twig/YAML lint, schema validation.

After changing service wiring, clear the cache before using `--no-debug`: Symfony intentionally does not recompile stale containers in that mode.

## Data pipeline

Signed download metadata remains JSON; bulk payloads are XML. `survos/global-giving-client` streams downloads into temporary files and atomically publishes them after successful transfer. Its XMLReader processes one top-level record at a time, rejects DTDs, and validates root/count/end-of-file. There is no IATI conversion requirement.

The application writes normalized JSONL via `survos/jsonl-bundle`. Only a completely validated snapshot becomes current. SHA-256 checks and expected row counts protect later reads (including against skipped corrupt JSONL). The import uses one database transaction with regular flush/clear batches. Repeated imports upsert GlobalGiving IDs. Full remote project snapshots mark previously active but absent records inactive; records are retained for historical links. Partial/local imports cannot trigger that reconciliation.

Immutable source IDs, original provider fields, import timestamps and source URLs preserve provenance. Missing historical organizations are allowed. The public API excludes raw source/contact payloads. Source snapshots and the database are under `var/`; persist this directory in a deployment. Previous snapshots remain available for audit; disk retention is an operator decision.

## Local semantic search

The AI Lab uses Meilisearch's native Ollama embedder on a separate index, not a second custom search controller. It defaults to 200 active projects; `--limit` accepts 1–1000. The main catalog remains complete keyword/faceted search.

```sh
ollama pull nomic-embed-text
# Ollama must be reachable from the Meilisearch container.
# Defaults: OLLAMA_EMBED_URL=http://host.docker.internal:11434/api/embed
#           OLLAMA_EMBED_MODEL=nomic-embed-text
php bin/console app:lab --no-debug
```

Open `/lab`, type a natural-language query, and vary semantic share from 0% (keywords) to 100% (meaning). No cloud inference is used by the default model. `var/lab/` records each source text, content hash, source URL, source modification date and model name for reproducible evaluation. This is a discovery experiment, not an impact-rating model. Rebuild it deliberately when the catalog/model changes.

## Nonprofit research

The organization page combines GlobalGiving's mission/projects with a separately attributed ProPublica/IRS panel when an exact EIN lookup has been run. Filing figures are organization-wide and include fiscal year and retrieval date. They are not project spending or measures of effectiveness. Missing EINs, non-US coverage and unmatched records remain unmatched.

Provider contract: https://projects.propublica.org/nonprofits/api . Charity Navigator could be another provider later; no ratings are invented or scraped here.

## Validation and deployment

```sh
castor check
castor image
```

The FrankenPHP/Caddy image builds with a named `mono=../mono` context and mirrors path packages into vendor; it does not ship dangling symlinks. Deploy the resulting image through Dokku, or publish the registered monorepo packages and switch to release dependencies first. Configure DATABASE_URL, Meilisearch, API keys, trusted proxies, persistent data and the daily refresh job for that environment. No remote deployment or shared Postgres migration is performed by setup.

Caddy handles `/favicon.ico` before PHP. AssetMapper compilation belongs to production builds; delete `public/assets/` after any local compile test.

## Verified local environment

The local checkout uses SQLite and Meilisearch 1.53.0 running natively at `127.0.0.1:7702`, with data under `var/meili`. Docker's 2 GB VM could not sustain the complete index alongside its other services; its isolated test container is stopped. The native server uses two indexing threads and a 256 MiB indexing-memory budget. The tracked defaults still use the shared Docker services. Allow adequate Meilisearch memory when deploying the full historical catalog.

Local overrides also set `OLLAMA_EMBED_URL=http://127.0.0.1:11434/api/embed` because native Meilisearch can reach native Ollama directly. `MEILI_ADMIN_KEY` is deliberately distinct from the older shell-wide `MEILI_API_KEY` setting.

Source IDs use explicit bigint columns, with no generated-value mapping. Besides accommodating provider IDs, this avoids SQLite's implicit auto-increment interpretation of an INTEGER primary key during schema introspection.

To restart this local native search service, install the matching official Meilisearch 1.53.0 executable under `var/tools/meilisearch`, then run `php bin/local-search.php`. The helper reads the admin key from `.env.local`, binds only port 7702 on loopback, and allows outbound loopback connections for Ollama. Meilisearch 1.53 blocks private IPs by default; that narrow allowlist is necessary for local embeddings. Release binaries: https://github.com/meilisearch/meilisearch/releases/tag/v1.53.0 .
