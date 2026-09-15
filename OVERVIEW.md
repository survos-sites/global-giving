# Giving Atlas

Search and explore GlobalGiving's project and nonprofit dataset. The application links project narratives, goals, funding and organizations, with an optional EIN-based ProPublica/IRS research panel and a local semantic-search lab.

## Components

- `survos/global-giving-client`: typed JSON API access and streaming native XML bulk exports.
- `survos/global-giving-bundle`: kit-based Symfony service configuration.
- App services: SnapshotService (validated JSONL), AppService (transactional upserts), SearchService (Field-derived Meilisearch indexes), ResearchService (exact EIN enrichment), LabService (local vectors).
- Entities: Project, Organization, Theme; public properties, natural IDs, Field/EntityMeta/RouteIdentity metadata.
- UI: Tabler, AssetMapper, meili-bundle's existing InstantSearch controller and Twig hit templates.

Read-only API Platform endpoints expose public catalog fields. Raw provider fields and research provenance remain separate. No donation processing, user accounts, ratings, or claims of verified impact are implemented.
