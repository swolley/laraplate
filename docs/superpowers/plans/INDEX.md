# Plans Index

Navigation aid only. Use this file to find the relevant plan, then open only that plan or section.

- `2026-05-13-rag-multi-instance-elasticsearch.md`: Implement Elasticsearch-backed RAG vector store.
- `2026-05-15-cms-comments-moderation.md`: Implement CMS comments with AI-assisted moderation.
- `2026-05-21-module-testing-strategy.md`: Reorganize module/application test suites.
- `2026-05-21-settings-group-cache-invalidation.md`: Implement group-level settings cache invalidation.
- `2026-05-25-erp-m36-purchase-invoice-posting.md`: Verify and complete purchase invoice posting cleanup.
- `2026-05-25-erp-m4-policies-filament-reporting.md`: ERP policies, Filament actions, and reporting alignment.
- `2026-05-25-erp-m61-bank-reconciliation.md`: ERP bank statement import and reconciliation.
- `2026-05-25-erp-m62-returns-management.md`: ERP customer/supplier returns with inventory effects.
- `2026-05-25-erp-m63-einvoice-sdi.md`: ERP e-invoice stub and submission structure.
- `2026-05-25-erp-m71-advanced-pricelists.md`: ERP advanced pricelists and discount rules.
- `docs/superpowers/plans/2026-06-19-mes-module-full-implementation.md`: Full MES module implementation plan.
- `docs/superpowers/specs/2026-07-09-mes-module-decisions-design.md`: MES locked decisions (confirmed 2026-07-09).
- `2026-06-23-erp-accounting-golden-master.md`: ERP accounting golden-master regression tests.
- `2026-06-30-erp-hardening-spec1.md`: ERP Spec 1 hardening — **completed** (8 tasks + patch `971851d` ERP / `15b11c8` Core). Open follow-ups → Spec 2 master backlog.
- `2026-06-30-erp-hardening-spec2-phase2a.md`: ERP Spec 2 Phase 2A — Filament domain actions + state-aware policies (**completed**, ERP `300f9ef`).
- `2026-06-30-erp-hardening-spec2-phase2b.md`: ERP Spec 2 Phase 2B — commercial/banking UX, returns automation, reporting polish (**in progress**, Wave A + optional `2B-03` + Wave B completed, 7 backlog rows remain).
- `2026-06-30-erp-hardening-spec2-phase3-remaining.md`: ERP Spec 2 remaining backlog — mandatory non-API work complete; Phase 3 `/app` half (`3-04`, `3-01`, `3-06`) ready to implement, `/api/v1` half deferred; `4-09` import framework planned.
- `2026-06-30-cms-graph-layer.md`: Core Graph Framework — CRUD-aligned expand/search/stats in Core, CMS as first provider, Phase 5 materialized edges gated by benchmarks and invalidation design.
- `2026-07-02-cms-content-provenance-ai-assistance.md`: CMS content origin, references bibliography, and per-translation `ai_assistance` enum.
- `2026-07-09-query-memory-filament-performance.md`: Core query batching (DatabaseEngine SQLite, licenses list, closure rebuild) and Filament widget/cache follow-ups.
- `2026-07-16-rag-retrieval-strategy.md`: Evaluation-first documentation RAG evolution from vector baseline to optional hybrid/reranking, with a separate authorization gate for any graph spike.
- `2026-07-16-in-app-ai-assistance-security.md`: Mandatory server-owned profiles, isolated user RAG, fail-closed guardrails, and ACL-preserving read-only Core Graph tools for in-app assistance.
- `2026-07-17-application-content-retrieval.md`: General Core provider registry, authenticated module evidence retrieval, CMS record-level baseline, AI tool integration, and separately gated public assistance.
- `2026-07-21-core-version-set-foundation.md`: Implement the generic Core sole writer, synchronous single-connection version sets, pivot descriptors, forward restore, approval integration, and set-level retention.
- `2026-07-21-cms-versioned-categories-pilot.md`: Prove versioned composite pivots on `Content::categories()` with one set per root and explicitly partial Content coverage.
- `2026-07-22-module-import-command-framework.md`: Completed Core abstract import mechanics and CMS command migration; ERP scope transferred to its dedicated plan.
- `2026-07-22-erp-external-source-importers.md`: Add `erp:import` and three ERP adapters in `laraplate-importers` for legacy Symfony SQL, SPLID Excel, and supported Tricount exports.
- `2026-07-29-filament-environment-indicator.md`: Replace pxlrbt env indicator with Core plugin + module version dropdown.
- `2026-07-29-filament-make-resources.md`: `filament:make-resources` + ownership helper + permanent Filament ClassGenerator rebind.
- `2026-07-29-hasform-entity-preset.md`: Core `HasForm` Entity → Preset cascade; persist `presettable_id` only.
- `2026-07-31-domain-action-http-routes.md`: Implements `3-04`, `3-01`, `3-06` — PermissionName helper, domain action registry with the generic-verb collision guard, dispatcher, `POST /app/crud/{action}/{module}/{entity}`, ERP registrar and the HTTP behaviour matrix.
