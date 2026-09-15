# GlobalGiving revival

## Requirements and progress

- [x] Modern PHP 8.5 / Symfony 8.1 / Doctrine 3 / API Platform 4 / Tabler / AssetMapper / Castor.
- [x] Typed reusable GlobalGiving client, kit-based bundle, streaming XML ingestion and JSONL snapshots.
- [x] Field metadata, stable source IDs, optional historical organizations, repeatable bounded-memory imports.
- [x] Working project and organization discovery, facets and detail pages using current Survos search UI.
- [x] AI/vector experiment path with explicit provenance and no automatic paid processing.
- [x] EIN-based nonprofit research integration (evaluate ProPublica/Charity Navigator).
- [x] Offline tests, full live import, repeat import, database validation and rendered browser verification.
- [x] Bundle/client publication registration and install documentation.

## Decisions

2026-09-14: Rebuild the small application layer in place; retain repository/history, replace prototype internals. Use shared Postgres as the tracked default, disposable SQLite in local/test overrides for autonomous verification. No existing database is being migrated or dropped. Native GlobalGiving XML is the supported bulk input. Keep client code in `survos/global-giving-client`, Symfony wiring in `survos/global-giving-bundle`. Historical rows may lack organizations. Preserve raw normalized source data for provenance. Active catalog first, all-history import also supported. Do not infer nonprofit equivalence from name similarity; EIN matches must be explicit and attributed.

2026-09-14: EasyAdmin 4 is incompatible with the current UX Twig Component 3 dependencies; use EasyAdmin 5 (showcase already does). Bulk serialization uses a dedicated Symfony Serializer, with no profiler wrappers, to avoid retaining all normalized records. Fixed kit-bundle's static-analysis-only optional trait annotations so Symfony DebugClassLoader does not mistake them for mandatory subclass methods.

2026-09-14 verification: imported all 54,022 projects and 27,810 organizations (28 themes). A full repeat under a 128 MiB PHP limit preserved counts and peaked at 50.33 MB. ProPublica exact-EIN lookups succeeded for organizations 8, 15 and 46. The first full catalog index could not fit in the shared 2 GB Docker VM; local verification now uses native Meilisearch 1.53.0 on port 7702. The isolated Docker Meilisearch test container is stopped. No shared service configuration was changed. A fresh PostgreSQL baseline was generated but not applied.

Final verification: 18 PHPUnit tests / 62 assertions; PHPStan level 5 for app, client and bundle; container, Twig and YAML lint; Doctrine mapping/schema validation. Full project and organization indexes contain exactly 54,022 and 27,810 documents. AI lab contains 200 documents and 200 embeddings. Browser checks covered homepage, project/organization details, exact-EIN research, keyword search, activity filters, organization search, mobile layout and semantic share 0/100. FrankenPHP image builds and serves the homepage and read-only API with mirrored dependencies. The PostgreSQL baseline and public package release remain deployment decisions, not prerequisites for this local working version.

Integration fixes: kit-bundle optional-trait annotations are PHPStan-only; meili-bundle now supports a human-readable `searchPlaceholder` Stimulus value instead of exposing server/index internals in the search box. Composer path repositories use default local symlinking so COMPOSER_MIRROR_PATH_REPOS can override it during container builds.

2026-09-15: Replaced local Composer path repositories and 2.99 aliases with published packages. Client and bundle require ^2.28.7. The bundle split release succeeded after initializing its empty repository and rerunning the failed release job. A scoped GitHub VCS override bridges the legacy Packagist repository URL until its owner updates it. Tests and PHPStan resolve package sources under vendor; Docker no longer needs the monorepo build context.

Published dependency validation: 18 tests / 62 assertions, PHPStan, lint and schema checks pass. Browser semantic search returns 200 sample matches. Added an explicit symfony/process dependency for the native search launcher. The first standalone Docker build passed; the final rebuild after that dependency addition stalled during production cache warmup while Docker status commands also stopped responding, so final image verification remains incomplete.

2026-09-15 checkpoint: Packagist now points to the revived bundle repository. Removed the scoped VCS override; Composer resolves the same 2.28.7 release directly from Packagist. This is the working Meilisearch/Ollama baseline before Elasticsearch comparison work.

2026-09-15: Added local `/lab/compare` research tooling. Shared, checksummed JSONL snapshots and cached vectors feed separate Meilisearch userProvided and Elasticsearch cosine indexes. Both engines use the same combined text, country prefilter and app-side reciprocal rank fusion. Saved result sets and query/snapshot-scoped relevance judgments persist under var/lab/compare. Production route is disabled until authentication is designed. Engine-specific VectorDB tuning, model comparisons and full-catalog expansion remain follow-up experiments. The pre-ES baseline was merged and pushed to main at 43a7c1d.

Comparison verification: both engines indexed the same 200-record snapshot; browser checks passed for keyword, vector and hybrid modes, Guatemala prefiltering, saved-run reload, shared relevance ratings and clearing ratings. All 24 PHPUnit tests / 80 assertions, PHPStan level 5, container/Twig/YAML lint and schema validation pass. A failed count-validation test confirms the previous engine pointer remains unchanged. No application deployment or shared service change was performed.

Score/overlap UI: retained native scores from both engines; hybrid results retain fusion scores and candidate-rank contributions. Shared top-ten IDs are highlighted and can be hidden without renumbering the remaining results. Focused scoring/transport tests, PHPStan and Twig lint pass; browser verified live fusion scores, shared rank badges and the differences-only view.
