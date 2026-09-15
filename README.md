# Giving Atlas

A modern explorer of GlobalGiving projects and nonprofits, with attributed EIN-based research and a local vector-search experiment. Independent of GlobalGiving; not endorsed by it.

PHP 8.5 · Symfony 8.1 · Doctrine ORM 3 · API Platform 4 · Tabler · AssetMapper · field-bundle · Meilisearch. See [OVERVIEW.md](OVERVIEW.md), [PLAN.md](PLAN.md), and `~/sites/showcase/CONVENTIONS.md`.

## Local setup

Install from the committed Composer lockfile. The revived GlobalGiving client and Symfony bundle are published on Packagist (2.28.7 or newer). No sibling monorepo checkout, local version aliases or custom Composer repositories are required.

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

The FrankenPHP/Caddy image installs published dependencies from the lockfile using the app checkout alone. Deploy the resulting image through Dokku. Configure DATABASE_URL, Meilisearch, API keys, trusted proxies, persistent data and the daily refresh job for that environment. No remote deployment or shared Postgres migration is performed by setup.

Caddy handles `/favicon.ico` before PHP. AssetMapper compilation belongs to production builds; delete `public/assets/` after any local compile test.

## Verified local environment

The local checkout uses SQLite and Meilisearch 1.53.0 running natively at `127.0.0.1:7702`, with data under `var/meili`. Docker's 2 GB VM could not sustain the complete index alongside its other services; its isolated test container is stopped. The native server uses two indexing threads and a 256 MiB indexing-memory budget. The tracked defaults still use the shared Docker services. Allow adequate Meilisearch memory when deploying the full historical catalog.

Local overrides also set `OLLAMA_EMBED_URL=http://127.0.0.1:11434/api/embed` because native Meilisearch can reach native Ollama directly. `MEILI_ADMIN_KEY` is deliberately distinct from the older shell-wide `MEILI_API_KEY` setting.

Source IDs use explicit bigint columns, with no generated-value mapping. Besides accommodating provider IDs, this avoids SQLite's implicit auto-increment interpretation of an INTEGER primary key during schema introspection.

To restart this local native search service, install the matching official Meilisearch 1.53.0 executable under `var/tools/meilisearch`, then run `php bin/local-search.php`. The helper reads the admin key from `.env.local`, binds only port 7702 on loopback, and allows outbound loopback connections for Ollama. Meilisearch 1.53 blocks private IPs by default; that narrow allowlist is necessary for local embeddings. Release binaries: https://github.com/meilisearch/meilisearch/releases/tag/v1.53.0 .

## Search comparison lab (local development)

Giving Atlas is actively maintained again. `/lab/compare` compares Meilisearch and Elasticsearch over **identical frozen records and vectors**, while `/lab` preserves the original Meilisearch/Ollama experiment. The comparison page is unavailable in production until an authenticated research workflow is added.

```sh
# .env.local (server-side only; optional encoded Elasticsearch API key)
LAB_ES_URL=http://127.0.0.1:9200
LAB_ES_API_KEY=

# Generate once, then index the same snapshot into each engine.
php bin/console app:lab:snapshot --limit=200 --no-debug
php bin/console app:lab:index meili --no-debug
php bin/console app:lab:index elastic --no-debug
```

The first pass uses standard cosine dense-vector search in Elasticsearch 9.5.3+, with one shard and no replicas for this disposable local index. Special VectorDB/DiskBBQ modes, rerankers and alternative models are later experiments. No existing catalog index is changed.

- **Keywords:** each engine searches the same combined project text.
- **Meaning:** both engines receive the same cached Ollama query vector.
- **Keywords + meaning:** each engine supplies up to 50 keyword and 50 vector candidates; the app combines ranks with equal-weight reciprocal rank fusion (`1/(60 + rank)`), displaying ten results. Raw scores are not compared.
- Optional country codes apply before vector candidate selection in both engines.
- Save a comparison to preserve its query, filter, ranked results, index names, snapshot/model identity and timings. Rate projects as not relevant, somewhat relevant or useful. Judgments are shared across engines and modes for the same query, country and snapshot. Unrated is distinct from not relevant.

Snapshots, vector caches, saved runs and judgments live under `var/lab/compare/`. Preserve that directory to retain research. Snapshot selection is deliberately deterministic (active projects ordered by source ID), **not representative**. Text is explicitly capped at 6,000 UTF-8 bytes; the exact text and its hash are stored with source URLs and modification dates. The Ollama model digest is frozen, and a changed model requires a new snapshot. Query embedding/cache time is shown separately from engine request time; individual requests are not performance benchmarks.

Each build creates a new index and publishes its local pointer only after document-count validation. A failed build leaves the previous pointer unchanged. Old and failed comparison indexes are retained for inspection; remove them deliberately when no saved run needs them. Saved runs preserve rendered results independently of index retention. Elasticsearch indexes use the `gg_compare_` prefix; Meilisearch uses the same dedicated prefix. This initial tooling is bounded to 1,000 projects; increase scope only after evaluating the sample.

Results show native relevance scores for keyword/vector modes and the app's fusion score for hybrid mode. Hybrid hits also show their keyword and vector candidate ranks. These are query-specific ranking diagnostics, not confidence percentages or project-quality ratings. Older saved runs omit scores until rerun. Projects shared by both displayed top tens are highlighted with their rank in each engine; “Hide shared projects” leaves the differences at their original rank numbers.
