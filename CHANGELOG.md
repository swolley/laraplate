# Changelog

All notable changes to this project will be documented in this file.

## [1.14.1] - 2026-09-15

### 🚀 Features

- *(modules)* [**breaking**] Record locking overhaul across Core, CMS, ERP and SAO
- *(admin)* Let each module own its navigation group
- *(release)* Choose, skip or confirm each target interactively
- *(release)* Release every pending module, then the application, with --all
- *(release)* Regenerate and verify changelogs against history
- *(release)* Gate commit messages and replace the unused post-commit machinery

### 🐛 Bug Fixes

- *(core)* Bump submodule for fork-safe redis cache purge
- *(filament)* Update Core and CMS for validity table and edit form fixes
- *(admin)* Stop prefetching panel links, which locked records on hover
- *(http)* Trust the reverse proxy headers so HTTPS is detected
- *(core)* Update Core submodule with Number field validation fix
- *(release)* Keep single-line commits and tag the release being written
- *(release)* Rewrite version.sh around a plan and a plain release commit
- *(release)* Read the whole module list before matching a target
- *(release)* Carry the module release level into the application under --all
- *(modules)* Update subproject commits for AI, CMS, Core, ERP, MES, and SAO modules
- *(release)* Keep unreleased work out of CHANGELOG.md

### 💼 Other

- Update submodules and dependencies; enhance update script

- Updated submodule references for CMS and Core modules.
- Updated composer.lock to reflect new versions of dependencies, including:
  - filament packages from v5.7.7 to v5.7.8
  - laravel/framework from v12.68.0 to v12.69.1
  - laravel/socialite from v5.30.1 to v5.31.0
  - league/flysystem from v3.35.3 to v3.36.0
  - monolog/monolog from v3.10.0 to v3.11.0
  - neuron-core/neuron-ai from v3.16.7 to v3.16.8
  - spatie/laravel-medialibrary from v11.23.5 to v11.23.6
  - larastan/larastan from v3.10.0 to v3.11.0
  - rector/rector from v2.6.5 to v2.6.6
- Updated package-lock.json for various @rolldown bindings to version 1.2.7.
- Modified update_app.sh script to provide more informative output during submodule updates and added user prompt for full update confirmation.
- Add security and test data guidelines; update Laravel Boost and Claude documentation

- Introduced a new security tests guideline document outlining best practices for testing security boundaries and escaping user-provided content.
- Added a comprehensive test data guideline document detailing the creation of mutable records, use of factories, and datasets for tests.
- Updated Laravel Boost guidelines to clarify foundational context, skills activation, conventions, verification scripts, and application structure.
- Enhanced Claude documentation with clearer instructions on Laravel Boost guidelines, project rules, and testing practices.
- Updated submodule references for AI, Core, ERP, MES, and SAO modules.
- Modified boost.json to include additional skills and packages for improved development support.
- The application's tool configuration covers the modules
- Declare the test toolchain in the application
- Refactor code structure for improved readability and maintainability

### 🚜 Refactor

- Enhance schema component with intersection observer for deferred loading

### 📚 Documentation

- Pin native modules to one schema and one connection
- *(ai)* Spec RAG R2 — SAO application-content provider
- *(sao)* Implementation plan RAG R2 — SAO application-content provider
- *(plans)* Track ES index-mapping fix + vector enablement (indexing solved, vector retrieval verified)
- *(plans)* Record AI search-path resilience fixes (NeuronAI init, LLM + reranker degradation)
- *(plans)* Check off the ES-gated createIndex mapping integration test
- *(plans)* Record the locking overhaul as shipped, and bump Core
- *(plans)* Close the comments moderation plan as shipped and generalized
- *(plans)* Close two more plans whose empty boxes were not outstanding work
- *(plans)* Close four more, and say which open box is real
- Adopt the subproject as the home of its own specs and plans
- *(plans)* Close the delivered plans and record the retrospective ones
- *(plans)* Close the import framework, the last box was already covered
- *(plans)* Close the ERP factories plan
- *(plans)* The shared SQL parsers Nebula wants already exist
- *(plans)* Finalize ERP factories and demo dataset design
- *(plans)* The module testing strategy keeps one runner, not six
- *(plans)* Correct the ticks the execution did not earn
- *(versioning)* Mark reference-only restore done in milestone-1 plan
- *(specs)* Move MCP server design into the backend repo
- *(release)* Plan the release tooling and move versioning out of the testing strategy
- *(specs)* Remove duplicated sections from MCP server design
- *(changelog)* Regenerate with the corrected git-cliff configuration
- *(release)* Document the release process and expose it through Composer

### 🧪 Testing

- *(release)* Add a harness that drives version.sh against throwaway repositories

### ⚙️ Miscellaneous Tasks

- Update submodule references and add release tooling design document
- *(modules)* Bump Core for non-appended HasPath accessor
- *(modules)* Bump Core and CMS for HasPlace N+1 fixes
- *(modules)* Update submodule references for Core and CMS
- *(modules)* Update submodule references for Core, CMS, ERP, MES, and SAO; add record locking overhaul plan
- *(sao)* Bump submodule to the `select` verb normalization
- *(permissions)* Bump SAO and ERP to the dead-verb cleanup
- *(modules)* Bump Core and ERP for the lock-scope extension point
- *(modules)* Update submodule commits for AI, CMS, Core, ERP, MES, and SAO
- *(env)* Make the example match the stack the app actually runs on
- *(deps)* Update submodule commits for Core, CMS, ERP, MES, and SAO; bump Filament packages to version 5.8.1; add public/flags/ to .gitignore; update app and horizon config to use environment variables for timezone and memory limit
- *(deps)* Update submodule commits for AI, CMS, Core, and SAO; bump inspector-php, mcp, and pint versions; add libc support in package-lock
- *(deps)* Update Core submodule to latest commit ea2253e
- *(deps)* Update CMS and Core submodules to latest commits
- *(core)* Bump submodule for the lock verb permissions
- Bump Core/CMS/AI submodules — ES multilingual index + multi-model embeddings (tasks 1-9)
- Bump Core/CMS/AI/SAO submodules and lock files
- *(sao)* Bump submodule for the transition permission picker
- *(modules)* Bump CMS and Core for the closed-plan documentation pass
- *(docs)* Make "nothing ships documented only in a plan" enforceable
- One versioning script for the whole stack
- *(modules)* Record the toolchain centralization pointers
- *(deps)* Modules own their dependencies, the application keeps the platform
- *(modules)* Record the regenerated module changelogs
- *(modules)* Record the module release documentation

## [1.14.0] - 2026-08-31

### 🚀 Features

- *(admin)* Keep session-backed approvals preview across Livewire updates

### 🐛 Bug Fixes

- *(admin)* Apply settings overlay and scoped log context on the panel

### 💼 Other

- Refactor code structure for improved readability and maintainability

### 📚 Documentation

- *(ai)* Spec RAG R1b — assistant end-to-end evaluation
- *(ai)* Implementation plan RAG R1b — assistant end-to-end evaluation
- Note the grid deprecation and bump Core for cache documentation
- *(plans)* Record the octane readiness plan and its execution outcome

### ⚙️ Miscellaneous Tasks

- *(core)* Bump Core submodule for viewer-relative pending (modifier identity)
- *(deps)* Update dependencies to latest versions
- *(modules)* Bump Core for grid deprecation
- *(modules)* Bump AI and Core for signal-free AI deadlines
- *(modules)* Bump Core and CMS for request-scoped permission caches
- *(modules)* Bump Core for class-independent permission memo key
- *(modules)* Bump Core for bounded depth cache
- *(modules)* Bump Core for the production-path permission memo test
- *(modules)* Bump Core for per-request settings overlay and seed budget warmup
- *(modules)* Bump Core for Spatie reset listener
- *(modules)* Bump Core for swagger-safe media upload rules
- *(modules)* Bump Core for schema-aware boolean input coercion
- *(modules)* Bump Core, CMS, and ERP for CRUD API exposure via settings

## [1.13.8] - 2026-08-27

### 🐛 Bug Fixes

- *(tests)* Force English locale in phpunit and bump CMS/ERP

### ⚙️ Miscellaneous Tasks

- Update submodule references for CMS, Core, and ERP modules

## [1.13.7] - 2026-08-26

### 🚀 Features

- *(perf)* Bump Core submodule for perf:bench benchmarking harness
- *(core)* Bump Core for list freshness endpoint
- *(core)* Bump Core for freshness presence and grid action fix
- *(perf)* Bump Core submodule for the perf stress-test toolkit
- *(mes)* Production-order auto-creation from confirmed sales orders
- *(mes)* Auto quality checks and stock-shortage detection
- *(mes)* Partial consumption on stock shortage
- *(mes)* Notify recipients on material shortage
- *(ai)* Per-module allowlist for embeddings and translation
- *(ai)* Default CRUD tools for the in-app assistant
- *(ai)* Structured filters/sort and request echo for CRUD tools
- *(ai)* Configure-mode view CRUD tool
- *(sao)* Phase 5b — code-to-work, releases and deploy census
- *(sao)* Phase 6 — fix propagation & evidence-based closure
- *(sao)* Phase 7 — gitea and sentry drivers
- *(sao)* Filament surfaces for releases, environments and closure policies
- *(sao)* Read-only filament surface for closure audits
- *(sao)* Apply closure decisions to the ticket workflow
- *(sao)* Deterministic ownership suggestion with read-only surface
- *(sao)* Codeowners ownership-evidence resolver
- *(sao)* Normalize the commit author in vcs reads
- *(sao)* Recent-touch ownership-evidence resolver
- *(sao)* Blame capability and blame-concentration ownership resolver
- *(sao)* Contributor identity directory as the ownership identity-map source
- *(sao)* Ownership suggestion coordinator
- *(sao)* Discover a pull request's changed files for ownership
- *(sao)* Phase 8 — AI-phrased ownership suggestions
- Optional AI text generation via a Core event seam
- *(ai)* Optional listener answering Core's AI text-generation request
- *(ai)* Production-ready live text generation behind its flag
- *(sao)* Connection health check + graylog logs driver
- *(filament)* Enable native database-notifications bell + bump Core

### 🐛 Bug Fixes

- *(core)* Bump Core for ResponseBuilder JsonResource list wrap
- *(ai)* Gate CRUD tools by permission, drop approval-for-unpermitted
- *(tests)* Silence file logging during Pest runs

### 📚 Documentation

- *(ai)* Spec RAG R0 — documentation evaluation baseline
- *(ai)* Implementation plan RAG R0 — documentation evaluation baseline
- *(versioning)* Milestone-1 membership-vector plan + confirmed scope
- *(ai)* Document ai:evaluate-documentation baseline
- *(versioning)* Mark syncVersioned done in milestone-1 plan
- *(ai)* Spec RAG R1a — profile-driven assistant scope
- *(ai)* Implementation plan RAG R1a — profile-driven assistant scope
- *(ai)* R1a spec — no-module in-app stays generic (data scope = module, not page)
- *(mes)* Revise API architecture and record cloud provisioning
- *(mes)* Record Tasks 4-5 implementation progress
- *(mes)* Record Tasks 6-8 implementation progress
- *(mes)* Record Tasks 9-13 completion (MES domain complete)
- *(mes)* Close module — Filament resources + manual consumption follow-up
- *(mes)* RAG user and developer docs for the new capabilities
- Reconcile completed Core/ERP quick-win plans with shipped code
- *(ai)* Record verified HasApprovals moderation behavior for CRUD tools
- Index completed SAO phase-0 and phase-1a plans
- *(sao)* Add phase-3a driver-framework foundation spec and plan
- *(sao)* Settle F4 — encrypted-at-rest connection credentials with env override
- *(sao)* Add phase-3b bindings and issues-sync spec and plan
- *(sao)* Add phase-1b ticket-enrichment spec and plan
- Add Core media foundation spec+plan; wire it as SAO 1b attachments prerequisite
- *(core)* Refine media-foundation trait split (HasMedia + MediaFileNamer to Core, HasMultimedia stays CMS)
- *(core)* Move the whole media foundation to Core (nothing is CMS-specific)
- *(core)* Keep HasMultimedia in CMS for now
- Reconcile phase-6 spec/plan indexes with the follow-on slices
- Spec + plan for the live LLM text generator
- Index the SAO/AI transport + model-binding follow-ups
- Mark CRUD facet-counters as implemented (tier 1)
- Scaffold four design specs (fix-attribution, tracker migration, bulk import, in-app notifications)
- *(sao)* Lock fix-attribution decisions + phase plan (spec #1)
- Mark spec #1 (fix-attribution) implemented in specs index
- *(import)* Mark spec #3 decisions shipped + bump Core gitlink
- Reference the SAO submodule in the root README
- Bump Core for interactive-import + notifications documentation

### ⚡ Performance

- *(crud)* Bump Core submodule for CRUD query/discovery optimizations
- *(core)* Bump Core submodule for entity-resolution index

### ⚙️ Miscellaneous Tasks

- Bump modules for seeding review fixes
- *(sao)* Bump SAO through the phase 1a configuration surfaces
- Update submodule references for Core, ERP, and SAO to latest commits
- *(cms)* Bump CMS for content modification soft-keep
- *(cms)* Bump CMS for live edit protection
- *(core)* Bump Core for approve quorum on write
- *(core)* Bump Core for pending approvals inbox endpoint
- *(core)* Bump Core for latest disapproval endpoint
- Update submodule references for CMS and Core to latest commits; enhance SAO module design documentation with product pillars and architectural clarifications
- *(core)* Bump Core submodule to latest tip
- *(ai)* Bump AI submodule for approve-job test fix
- Update submodule references for CMS, Core, and SAO to latest commits
- Update submodule references for AI and Core to latest commits; add draft design for CRUD facet counters utility in CrudService
- *(ai)* Update AI submodule to latest commit fbdae62
- *(core)* Update Core submodule to latest commit 3c2f2ea
- *(sao)* Close slice 1a — the internal ticketing core
- Bump Modules/AI submodule for RAG R0 documentation evaluation baseline
- Bump Modules/AI submodule for documentation evaluation guides
- *(deps)* Update dependencies and submodules
- Update ERP submodule to latest commit 70add06
- Update submodules for AI, CMS, Core, and ERP to latest commits
- Bump Modules/AI + Core for RAG R1a profile-driven assistant scope
- Bump Core/CMS submodules and update crud facet design doc
- Bump Modules/Core submodule to facet counters ACL completion
- *(mes)* Bump MES submodule for Tasks 4-5 (BOM + Routing)
- *(mes)* Bump MES + ERP submodules for Task 6 (production orders)
- *(mes)* Bump MES submodule for Task 7 (operation execution)
- *(mes)* Bump MES submodule for Task 8 (backflush)
- *(mes)* Bump MES submodule for Task 9 (lot/serial traceability)
- *(mes)* Bump MES submodule for Task 10 (quality/non-conformance)
- *(mes)* Bump MES submodule for Task 11 (capacity)
- *(mes)* Bump MES submodule for Task 12 (downtime/OEE)
- *(mes)* Bump MES submodule for Task 13 (shifts/operators)
- *(mes)* Bump MES submodule for Task 14 (domain-action API)
- *(mes)* Bump MES submodule for Tasks 15-17 and record completion
- *(mes)* Bump Core+MES submodules; MES suite green on PHP 8.5
- *(mes)* Bump MES submodule for RoutingOperation factory fix
- *(ai)* Bump AI submodule with approval-verb CRUD tools
- *(ai)* Bump AI submodule with summarize CRUD tool
- Bump Core (TabularPdfExporter) and AI (export CRUD tool) submodules
- *(erp)* Bump ERP submodule (ReportPdfExporter extends Core exporter)
- *(ai)* Bump AI submodule with bulk CRUD tools
- *(ai)* Bump AI submodule with user RAG guide for CRUD data tools
- *(core)* Bump Core submodule with tabular exporters doc
- *(sao)* Bump SAO submodule with developer RAG how-it-works section
- *(sao)* Bump SAO submodule — phase 3a tasks 1-3 (enums, contracts, registry)
- *(sao)* Bump SAO submodule — phase 3a tasks 4-5 (connection, credential resolver)
- *(sao)* Bump SAO submodule — phase 3a complete (conformance, wiring, docs)
- *(sao)* Bump SAO submodule — phase 3b tasks 1-3 (sync enums, BindingContext, config)
- *(sao)* Bump SAO submodule — phase 3b-core complete (bindings, links, internal driver, issue sync)
- Bump Core and CMS to Core-owned media foundation
- Bump SAO to phase 1b; reconcile media + 1b plans
- Bump SAO to 1b UI, 1c board and the Redmine driver
- Bump SAO with Jira, GitHub, GitLab and Bitbucket drivers
- Bump SAO with 3b-ui connection and binding surfaces
- Bump SAO with phase 5 vcs/releases on the git hosts
- Bump Core and SAO with phase 2 signals and fingerprinting
- Bump SAO with the filament signal resource
- Bump SAO with phase 4 ingest and source profiles
- Bump SAO submodule (youtrack/azure/linear + eight logs drivers)
- Bump SAO submodule (inbound webhook transport for logs connections)
- Bump SAO submodule (scheduled inbound issue polling)
- Bump AI submodule (configurable text-generation model binding)
- Bump SAO submodule (ingest-events read-only Filament surface)
- Bump SAO submodule (signal-to-ticket auto-open)
- Bump Core submodule (CRUD facet-counters tier 2)
- Bump Core submodule (facet tier 2 relation labels)
- Bump Core submodule (facet label search/sort)
- Bump SAO submodule (scheduled connection health probe)
- Bump SAO submodule (ingest replay command)
- Bump SAO submodule (accept ownership suggestion action)
- Bump SAO submodule (SourceProfile Filament CRUD)
- Bump Core/CMS submodules for facet label sources + relation facets
- Bump Core submodule for relation facet cross-filter fix
- Bump Core/CMS submodules for translated FK facet labels
- Bump Core/CMS submodules for base-label relation-facet fix + tests
- Bump Core/CMS submodules for to-one related-column facet
- Bump Core/CMS submodules for facet resolvability guard
- Bump CMS submodule — map/tag-graph insight endpoints
- Bump CMS submodule — Filament location geocode action
- Bump Core + CMS submodules — CRUD dotless relation-count fix, map via select
- Bump CMS submodule — map-select test eager-loads place
- Point submodules at Core/CMS master tips after merge
- Bump CMS submodule — content relation-sync endpoint
- Bump CMS submodule — relation-sync served as web route
- Bump Core + CMS submodules — relation-sync generalized into CRUD update
- Bump Core + CMS submodules — authoring surface for drafts
- Bump Core submodule — ACL filter dynamic placeholders
- Bump Core + CMS submodules — validity moves to role-scoped ACLs
- Bump Core/CMS/MES submodules
- Bump Core submodule (PerfCrud test-ordering pollution fix)
- Bump Core/CMS submodules (default-presettable enum fix + test)
- Bump CMS submodule (ContributorFactory locale-translation seeding)
- Bump Core submodule (CRUD read perf + metadata cache invalidation)
- Bump Core submodule (look-ahead pagination totals flag)
- Bump Core submodule (look-ahead default + pagination mode discriminator)
- Bump Core + CMS submodules (user prefs/first-login endpoints + counted-totals test)
- Bump Core/CMS gitlinks for the generic media HTTP API
- Bump Core/CMS gitlinks for media pending-bucket + row ACL
- Bump Core/CMS gitlinks for unified media upload endpoint
- Bump module gitlinks to released versions
- Bump SAO gitlink to v0.3.0
- Bump Core gitlink for optional login module scope
- Bump Core gitlink for permissions.module_name rename
- Bump Core gitlink for scoped-login RAG docs
- Bump SAO gitlink to include HTTP domain actions
- Bump SAO gitlink for tickets transitions read action
- Bump MES gitlink for dev seeder (demo data, roles, users)
- Bump SAO gitlink for dev seeder (demo data, role, user)
- Bump SAO/ERP/CMS gitlinks for explicit m2m pivot models
- Bump SAO gitlink for release-health read-model (#2)
- Bump SAO gitlink for deploy & rollout ingest core (#1)
- Bump SAO gitlink for deploy webhook transport (#1 complete)
- Bump SAO gitlink for attribution core (spec #1 phase 1)
- Bump SAO gitlink for fix-attribution pipeline (spec #1 complete)
- Bump SAO gitlink for RAG glossary updates + release normalization docs
- Bump SAO gitlink for deterministic release promotion (spec #1)
- Bump SAO gitlink for external tracker migration importer (spec #2)
- Bump SAO gitlink for data retention prune
- *(sao)* Bump submodule — resumable tracker import + history
- *(import)* Bump Core+SAO submodules — generic bulk import (Fase A+B)
- *(import)* Bump Core submodule — Filament monitoring + launcher
- *(import)* Bump Core/CMS/ERP submodules — Tier 1 importable entities
- Bump Core+CMS for import relation layer + cms.content pilot
- Bump Core for import relation metadata in field payload
- Update submodule references for AI, CMS, Core, ERP, MES, and SAO modules
- Update submodule references for Core and SAO modules
- Update submodule references for CMS, Core, MES, and SAO modules
- Bump SAO, ERP, and Core module pointers for test fixes
- Update submodule references for Core, ERP, and MES modules

## [1.13.6] - 2026-08-04

### 🐛 Bug Fixes

- *(tests)* Prevent Pest CallsTerminable shutdown failures

### ⚙️ Miscellaneous Tasks

- Update submodule reference for Modules/Core to latest commit
- Bump CMS, Core, and ERP for optional import console output

## [1.13.5] - 2026-08-04

### 🚀 Features

- *(cache)* Add custom PHPStan stubs for Laravel Cache facade with extended methods
- *(filament)* Bump modules for generate→trait merge
- Delegate root DatabaseSeeder to the Core orchestrator
- *(erp)* Add external cash import foundation
- Add sidebar group component with collapsible functionality and icon support

### 🐛 Bug Fixes

- *(erp)* Complete runtime connection affinity
- Preserve migration connection context
- *(core)* Close connection affinity guard bypasses
- Bump Core submodule for the seed orchestrator review fixes
- Close final database connection affinity gaps
- *(core)* Preserve approval authorization
- *(erp)* Harden external cash import foundation
- Finalize database connection affinity
- Update Core seeding reconciliation

### 📚 Documentation

- *(erp)* Design external source reconciliation
- Complete database connection affinity audit
- *(erp)* Plan Nebula cash import
- *(filament)* Specify generate→trait merge for tables and forms
- *(specs)* Align revision-centric draft with verified behaviour
- *(erp)* Design domain actions over HTTP on the /app surface
- *(erp)* Implementation plan for domain action HTTP routes
- *(core)* Seeder orchestration implementation plan
- *(sao)* Design the SAO orchestrator module
- *(sao)* Implementation plan for phase 0 scaffolding
- *(specs)* Index the SAO and seeder orchestration designs
- *(sao)* Record the completed submodule registration
- *(sao)* Add the documentation and RAG task to the phase 0 plan
- *(core)* Fix seeder plan commit protocol for submodules
- *(core)* Extract test stub instead of an inline anonymous class
- *(erp)* Close 3-01, 3-04 and 3-06 in the master backlog
- *(sao)* Design the phase 1a internal ticketing core
- *(sao)* Correct the 1a authorization section — ACL is implemented
- *(sao)* Mark phase 0 criterion 1 as deliberately open
- *(sao)* Implementation plan for slice 1a
- *(sao)* Correct the 1a plan from what Task 2 revealed
- *(sao)* Fold the Laraplate model standard into the 1a plan
- Index the seeder orchestration plan
- *(sao)* Distinguish release candidates from shipped releases
- *(erp)* Reconcile point zero status
- *(rag)* Define guest boundary closure
- *(sao)* Record two migration constraints the plan got wrong
- *(rag)* Plan guest boundary closure
- *(rag)* Close phase one guest boundary
- *(core)* Unblock Task 11 and fix its multi-submodule commit protocol
- Record database connection affinity audit

### 🧪 Testing

- Preserve model connections in database helpers
- *(core)* Reject falsy connection names

### ⚙️ Miscellaneous Tasks

- *(CMS)* Update subproject commit reference to latest version
- Bump Core and CMS for per-model setting name fix
- Bump Core for observer registration and versionable image fixes
- *(sao)* Register SAO as a submodule
- *(sao)* Activate the SAO module
- Bump Core for the seeder dependency graph
- Bump Core for seeder discovery
- Bump Core for seeder graph builder dependsOn edge tests
- *(sao)* Bump SAO to the phase 0 scaffolding state
- Bump Core for settings seeding columns
- Bump Core for SeedDefinition
- Bump Core for SeedDefinition validation fixes
- Bump Core for SeedReconciler
- Remove unused flag images for de, es, gb, it, and sl
- Bump Core for SeedReconciler test hardening
- Bump Core for the single-pass capability scan
- Bump Core for the strengthened capability-scan test
- Bump Core for observable model-skip logging in the capability scan
- Bump Core for single-pass settings reconciliation
- Bump Core for load-bearing no-force-delete test fix
- Bump Core for the seed run ledger
- Update subproject commits for Core and SAO modules
- Bump Core for settings cleanup
- *(core)* Bump Modules/Core — i18n translations (locale meta, user lang, UI keys)
- Update submodule references for AI, CMS, ERP, and SAO modules
- Update submodule reference for Modules/Core
- Update submodule references for Core and SAO modules; add review log for revision-centric aggregate history

## [1.13.4] - 2026-07-29

### 🚀 Features

- *(search)* Configure adaptive matching
- *(search)* Provision portable database indexes
- *(search)* Adopt adaptive matching profiles
- *(search)* Adopt explicit query syntax
- *(rag)* Integrate authorized application content retrieval
- *(versioning)* Implement core version set infrastructure and CMS versioned categories pilot
- *(filament)* Default Toggle fields to stacked label layout
- *(filament)* Ship make-resources docs and App panel discovery

### 🐛 Bug Fixes

- Preserve model connections in core queries
- Preserve model connections during cms imports
- *(core)* Keep validation lookups connection-aware
- *(erp)* Preserve aggregate connection boundaries
- *(filament)* Render stacked locale flags as circles

### 🚜 Refactor

- Replace pxlrbt/filament-environment-indicator with Core plugin and module version dropdown

### 📚 Documentation

- Require module documentation updates
- Add graph documentation checkpoint
- Record graph benchmark harness
- *(search)* Retain query syntax rationale
- *(ai)* Define evidence-gated RAG retrieval roadmap
- *(ai)* Define in-app assistance security boundary
- *(ai)* Plan protected in-app assistance
- *(ai)* Separate authorization from prompt policy
- *(ai)* Define modular application content retrieval
- *(ai)* Plan modular application content retrieval
- *(ai)* Define contextual provider routing
- *(erp)* Close enterprise implementation points
- *(erp)* Update operational backlog status
- *(erp)* Close operational command phase
- *(erp)* Close item-specific pricing backlog
- Require model connection affinity
- *(erp)* Close integration outbox backlog
- *(erp)* Close architecture vision backlog
- *(erp)* Close journal cash movement backlog
- *(erp)* Close cash movement UI backlog
- *(erp)* Close quotation revision backlog
- *(erp)* Close lock-chain trigger backlog
- *(erp)* Close partner pool settlement backlog
- *(erp)* Close payment request provider backlog
- *(rag)* Record application content delivery gates
- *(erp)* Close site place integration backlog
- *(erp)* Close task calendar export backlog
- *(erp)* Close forced version settings backlog
- *(erp)* Close numbering stress backlog
- *(import)* Plan module import framework
- *(import)* Record Core and CMS completion
- *(erp)* Plan external source importers
- *(plans)* Index ERP external importers
- *(erp)* Align master backlog with importer plan
- *(ia)* Update documentation with canonical agent rules and Laravel Boost guidelines
- Add ERP and MES module links to main README

### 🧪 Testing

- *(graph)* Enforce PSR-4 stubs

### ⚙️ Miscellaneous Tasks

- Update submodule references for Core and ERP modules, and remove unused it_40x30.webp file
- Update submodule reference for Core module
- Update submodule references for AI, CMS, Core, ERP, and MES modules
- Update dependencies and submodule references for CMS and ERP modules
- Update filament packages to version 5.7.1 and bump postcss to 8.5.20
- *(erp)* Update module revision
- *(erp)* Advance numbering stress coverage
- *(core)* Advance import framework revision
- *(deps)* Update filament packages to version 5.7.3 and @emnapi/core/runtime to version 2.0.0-alpha.3; update app.css and actions.js for compatibility
- *(deps)* Update ERP subproject to commit a7447e5
- Update Core and AI submodule pointers for PSR-4 test stubs
- *(deps)* Bump ERP submodule for distinct artisan console badge
- *(deps)* Update ERP submodule to latest commit 63e51d1
- *(deps)* Update submodule pointers for CMS, Core, and ERP to latest commits

## [1.13.3] - 2026-07-14

### 📚 Documentation

- Align search plan with database engine parity
- Define advanced search filter and score contracts

### ⚙️ Miscellaneous Tasks

- Update core search submodule
- Update core database vector search
- Update core embeddings migration
- Update core advanced search contracts
- Update core keyword advanced filters
- Document search filter metadata
- Wire indexed relation search filters
- Update submodule references for AI and ERP modules, and enhance global performance optimization documentation
- Refine testing guidelines and remove deprecated libc entries from package-lock.json

## [1.13.2] - 2026-07-12

### 💼 Other

- Revert "docs(erp): align enterprise module documentation"

This reverts commit 11baf1940fe585ba79bbbaf3f26771ed0de57516.

### 📚 Documentation

- *(erp)* Align enterprise module documentation
- *(erp)* Plan supplier payment run export
- *(erp)* Mark supplier payment export complete
- *(erp)* Mark bank difference reconciliation complete
- *(erp)* Mark bank format import complete
- *(erp)* Mark financial CSV export complete
- *(erp)* Close phase 2b implementation
- *(erp)* Move roadmap to phase 2c
- *(erp)* Update rag limitations pointer
- *(erp)* Mark phase 2c schema complete
- *(erp)* Mark phase 2c mapper complete
- *(erp)* Refresh phase 2c plan status
- *(erp)* Close phase 2b verification checklist
- *(erp)* Mark phase 2c xml validation complete
- Update core graph implementation plan
- *(erp)* Mark phase 2c aruba adapter complete
- Update graph provider rules plan
- Update graph materialized edge plan
- Defer graph materialized edges
- *(erp)* Close phase 2c permissions task
- Update graph search plan
- *(erp)* Add operational command backlog

### ⚙️ Miscellaneous Tasks

- *(modules)* Update ERP submodule reference and document progress on Phase 2A and 2B
- Align ERP phase tracking and module test discovery
- Update cms graph submodule
- Remove Kiro specs index, update submodule references, and enhance admin panel with sidebar accordion functionality

## [1.13.1] - 2026-07-10

### 🐛 Bug Fixes

- *(tests)* Stabilize suite bootstrap and bump module refs

### ⚙️ Miscellaneous Tasks

- *(modules)* Bump AI, CMS, Core, and ERP submodules
- *(modules)* Update submodule references for AI, CMS, Core, and ERP
- *(modules)* Update CMS submodule reference to latest commit
- *(modules)* Update Core submodule reference to latest commit and regenerate composer.lock

## [1.13.0] - 2026-07-09

### 🚀 Features

- *(performance)* Enhance performance optimization strategies and add large dataset guidelines

### 📚 Documentation

- Align RAG corpus guide with user vs developer audiences
- Update MES module plans and decisions

### ⚙️ Miscellaneous Tasks

- Bump CMS to v1.36.4 and sync project guidelines

## [1.11.4] - 2026-07-09

### 🐛 Bug Fixes

- *(app)* Guard validation exception context for all throwables

### ⚙️ Miscellaneous Tasks

- Update submodule references for CMS and Core modules
- Plans for graph api implementations
- *(app)* Wire core validation context and translation updates
- Update Pint configuration and enhance laraplate-specific rules
- Update dependencies and submodule references

## [1.11.3] - 2026-07-07

### 🚀 Features

- Add master rule file and enhance existing rules with descriptions
- Implement app structure with authentication and module management
- Add initial Business module plan and structure
- [**breaking**] Point monorepo to ERP submodule (laraplate-erp)
- *(cms)* Add content provenance, references and ai assistance metadata
- *(cms,core)* Wire record origins registry and generic cms:import

### 🐛 Bug Fixes

- Standardize module naming and update dependencies
- *(core)* Bump submodule for MySQL taxonomies migration trigger fix

### 💼 Other

- Update ERP plans and module references for M6.1 to M7.1 milestones

- Enhanced bank reconciliation (M6.1) with ranked payment suggestions and CSV import functionality.
- Improved returns management (M6.2) with explicit DDT handling for customer and supplier returns, and refined inventory posting processes.
- Updated e-invoice (M6.3) to include basic submission workflow and stub for FatturaPA compliance.
- Advanced pricelists (M7.1) now support sales order and quotation integration with pricing rules.
- Incremented version numbers in swagger documentation for AI, CMS, Core, ERP, and MES modules.
- Updated submodule references to latest commits for all modules.
- Update dependencies and module versions for improved functionality

- Bumped versions for filament packages to v5.6.7 in composer.lock.
- Updated package-lock.json with new versions for @napi-rs/wasm-runtime (1.1.5), @oxc-project/types (0.133.0), and @rolldown bindings (1.0.3).
- Adjusted PHPStan analysis level from 1 to 7 for enhanced code quality checks.
- Refined database configuration by removing deprecated PDO options.
- Enhanced seeders to ensure proper handling of module paths.
- Updated documentation for MES module with new tasks and requirements.
- Updated submodule references for AI, CMS, Core, ERP, and MES modules to their latest commits.

### 📚 Documentation

- *(plan)* Mark Filament ERP core slice in Nebula roadmap
- *(plan)* Align Nebula plan with ERP module paths and naming
- Add RAG multi-instance design and Elasticsearch implementation plan
- Add CMS comments with AI moderation design spec
- Revise CMS comments spec per review feedback
- Add preliminary AI disapproval and implementation plan
- Clarify locale read rule and approval Option A/B
- Use HasTranslations for comments with locale overrides
- Add approval_mode config for comment moderation A/B
- Add RTK documentation and update AGENTS.md
- *(erp)* Align roadmap status
- Add module versioning rule
- Require version bump confirmation
- *(erp)* Mark e-invoice stub complete
- *(erp)* Mark m4 operational reports complete
- *(erp)* Reconcile implementation plans
- *(erp)* Plan accounting golden masters
- Optimize agent rule routing
- *(erp)* Add Spec 1 design for v1 hardening (bugs + money math)
- *(erp)* Add Fix 8 (CRUD write guard) to Spec 1 hardening
- *(erp)* Refine Spec 1 hardening scope after code/test verification
- *(erp)* Add Spec 1 hardening implementation plan
- *(erp)* Update plans and specs for ERP hardening progress

### ⚙️ Miscellaneous Tasks

- Update package versions and submodule references
- *(deps)* Bump symfony/process from 7.4.3 to 7.4.5
- Update composer.lock with new package versions and dependencies
- Remove CLAUDE.md and update dependencies
- Update submodule references and composer.lock content-hash
- *(deps)* Bump psy/psysh from 0.12.18 to 0.12.19
- Add Laravel Boost guidelines and update dependencies
- Update submodule references for Cms and Core modules
- Update dependencies and improve component functionality
- Update Core submodule and add pagination component
- Remove IDE helper files and update .gitignore
- Update Cms submodule to latest commit
- Enhance testing setup and update dependencies
- Update submodule references for AI, Cms, and Core modules
- Update filament packages and submodule references
- *(deps)* Bump league/commonmark from 2.8.0 to 2.8.1
- Update phpunit configuration and Core submodule reference
- Update module activators and testing configuration
- Update dependencies and add sidebar scroll functionality
- Update database configuration for read/write semantics
- Enhance coding principles documentation
- Update composer dependencies and configuration settings
- Update filament packages to version 5.3.5 and enhance documentation
- *(deps)* Bump league/commonmark from 2.8.1 to 2.8.2
- Update dependencies and enhance performance optimizations
- Update dependencies and submodule references
- Add new CRM module and update dependencies
- *(deps-dev)* Bump vite from 6.4.1 to 6.4.2
- Update module structure and dependencies
- *(deps-dev)* Bump axios from 1.13.6 to 1.15.0
- *(deps-dev)* Bump follow-redirects from 1.15.11 to 1.16.0
- Update submodule references and enhance seeder functionality
- Add Business module as a subproject
- Update .env.example and submodule references
- Update .gitignore, add cursor settings, and update submodule references
- Bump submodules to B0+B1 milestones and sync ERP plan
- Bump Business submodule to B2 (M0-ERP) and sync plan
- *(business)* Bump submodule for B3a (CoA and fiscal periods)
- *(business)* Bump submodule for B3b (sequences and journal)
- *(business)* Bump submodule — M1 journal and sequences closed
- Bump Business submodule and update Nebula ERP plan
- *(business)* Bump submodule for Filament accounting domain
- Bump ERP and Core submodules for CRM and factory fix
- *(erp)* Bump ERP submodule for CRM, SO, and M3 foundation
- Update dependencies and enhance test configurations
- Add MES module and update configurations
- Update submodule references and clean up package-lock.json
- Add MES requirements revision document
- Update ERP submodule and development plan for M3.5-M5
- Update submodule references for Core and ERP modules
- Add cache duration registry to configuration
- Update dependencies and refine documentation
- *(plans)* Add initial ecommerce module development plan
- *(plans)* Mark ecommerce module development plan as completed
- Update dependencies and add bugfix for HasValidations trait
- Complete performance optimization tasks in documentation
- Update submodule references for AI, CMS, Core, and ERP modules
- *(plans)* Update ecommerce module embryo plan with architectural decisions and todos
- Update dependencies and enhance rich editor functionality
- Update environment and configuration files for MES module integration
- Update submodule references and Swagger documentation for AI, CMS, Core, ERP, and MES modules
- Update submodule references and composer.lock for module dependencies
- Update module versions and Swagger documentation for testing environment
- Add .cursorignore and AGENTS.md, update package-lock.json, and clean up project structure
- Reorganize testing structure and update module configurations
- *(core)* Bump Core module for GELF logging improvements
- Update .env.example and composer.lock for module dependencies
- *(deps)* Bump symfony/html-sanitizer from 8.0.8 to 8.0.13
- *(deps)* Bump symfony/cache from 8.0.10 to 8.0.13
- *(deps)* Bump symfony/http-kernel from 7.4.11 to 7.4.13
- *(deps)* Bump symfony/routing from 7.4.9 to 7.4.13
- *(deps)* Bump symfony/mime from 7.4.9 to 7.4.13
- *(deps)* Bump symfony/mailer from 7.4.8 to 7.4.12
- *(deps-dev)* Bump symfony/yaml from 7.4.11 to 8.0.13
- Update composer.lock and package-lock.json for new dependencies
- Update submodule references and API documentation
- *(deps)* Bump shell-quote and concurrently
- *(erp)* Record returns follow-up implementation
- *(erp)* Update tracing cast submodule pointer
- *(erp)* Update ERP submodule pointer to latest commit
- *(deps-dev)* Bump form-data from 4.0.5 to 4.0.6
- *(deps)* Bump phpseclib/phpseclib from 3.0.53 to 3.0.55
- *(deps)* Update dependencies in composer.lock and package-lock.json
- Bump Core/CMS/AI/ERP for trait Concerns refactor
- *(deps)* Update dependencies and implement MES module tasks
- Update submodule references for AI, CMS, and ERP modules
- *(erp)* Record accounting golden masters
- *(erp)* Record inventory accounting golden masters
- *(erp)* Record vat settlement confirmation guards
- *(erp)* Record document numbering concurrency guard
- Bump module references and align app bootstrap config
- Sync Core, AI, and CMS submodule references
- *(erp)* Record trial balance csv export
- Record shared csv exporter
- Sync module releases and ERP hardening progress
- Update Core module release
- Sync submodule references after PHPDoc formatting cleanup
- Update submodule references for AI and CMS modules

## [1.11.2] - 2026-01-22

### ⚙️ Miscellaneous Tasks

- Update submodule references and enhance Swagger documentation
- Enhance README and update submodule references
- Update submodule references for AI, Cms, and Core modules to latest commits
- Update package versions and submodule references

## [1.11.1] - 2026-01-15

### 💼 Other

- Create laravel.yml

### ⚙️ Miscellaneous Tasks

- Update dependencies and enhance functionality
- Update package-lock.json and enhance CSS styles
- Update IDE helper models and improve documentation
- Update .vscode settings and submodule commits
- Update composer.json and submodule commits
- Update submodule commits and improve version script
- Update submodule commits and enhance version script validation
- Update versioning method in composer.json
- Update submodule commits and enhance version script logic
- Update submodule commits and refine version script logic
- Update submodule commits and enhance version script functionality
- Update submodule commits and enhance version script debugging
- Update submodule commits for Cms and Core modules
- Clean up post-commit hook script
- Update dependencies and remove unused package
- Update dependencies and submodule commits
- Update project configuration and dependencies
- Code lint with pint and rector
- Update coding principles and module structure
- Update submodule URLs to use HTTPS
- Update module links in README
- Update IDE helper and module configurations
- Update IDE helper models and package dependencies
- Update environment configurations and dependencies
- Update dependencies and improve package configurations
- Update IDE helper files and improve cache functionality
- Enhance IDE helper files and add new traits
- Update Cms submodule and add pushurl synchronization script
- Update submodule commits and improve type hinting
- Enhance IDE helper files and update submodule references
- Enhance IDE helper traits and update PHPStan configuration
- Update Laravel standards and enhance test case structure
- Update environment and configuration files for PHP 8.5 compatibility
- Update Laravel standards and enhance performance optimization guidelines
- Enhance IDE helper models and update workspace configuration
- Update dependencies and enhance configuration files
- Enhance IDE helper models and update submodule references
- Update commit parsing rules and enhance version update script
- Update submodule references for Cms and Core modules
- Update commit parsing rules and submodule references
- Update submodule references for Cms and Core modules to latest commits
- Update IDE helper models and configuration files
- Update environment variable names and package versions
- Update package versions in composer.lock and submodule reference for Core module
- Update IDE helper models, environment variables, and package versions
- Add filament/spatie-laravel-media-library-plugin and update package versions
- Add AI module and update submodule references
- Update composer.json and submodule references for AI, Cms, and Core modules

## [1.11.0] - 2025-09-19

### ⚙️ Miscellaneous Tasks

- Update dependencies and enhance configurations

## [1.10.0] - 2025-09-05

### ⚙️ Miscellaneous Tasks

- Update model properties and enhance CSS styles
- Update configurations, remove outdated tests, and enhance file structure

## [1.9.0] - 2025-08-18

### 💼 Other

- Update Core submodule with README documentation
- Update CMS submodule with README documentation

### ⚙️ Miscellaneous Tasks

- Update dependencies and submodules
- Update composer.json and submodule commits
- Update submodule commits and version script
- Update submodule commits for Cms and Core modules
- Update dependencies, submodules, and configuration files
- Remove outdated configuration and rules files
- Update VSCode configuration files and clean up .gitignore
- Update dependencies, submodules, and Swagger documentation
- Update package versions in composer.lock and package-lock.json
- Update model properties and configurations

## [1.8.2] - 2025-06-23

### ⚙️ Miscellaneous Tasks

- Update composer.lock and CSS/JS dependencies

## [1.8.1] - 2025-06-13

### ⚙️ Miscellaneous Tasks

- Update IDE helper models with new properties

## [1.7.0] - 2025-06-13

### ⚙️ Miscellaneous Tasks

- Update submodule commits for Cms and Core modules
- Update Core module submodule commit
- Update rector configuration and Laravel set list
- Update IDE helper files and improve type hinting
- Enhance IDE helper and logging configuration
- Update composer scripts and improve versioning process
- Update IDE helper models and enhance type hinting
- Update testing framework reference in Laravel best practices
- Enhance Filament integration and update resource tests
- Update IDE helper models and enhance resource tests
- Update IDE helper models and enhance configuration

### ◀️ Revert

- Update IDE helper and remove Filament authentication components

## [1.6.6] - 2025-05-05

### ⚙️ Miscellaneous Tasks

- Update IDE helper models and bump Laravel version
- Update IDE helper models and improve type hinting
- Update IDE helper models and configuration files
- Update IDE helper and configuration files
- Enhance IDE support and update configurations
- Update IDE helper models and improve class definitions

## [1.6.5] - 2025-04-07

### ⚙️ Miscellaneous Tasks

- Update submodule commits for Cms and Core modules

## [1.6.3] - 2025-04-06

### ⚙️ Miscellaneous Tasks

- Update IDE helper models and bump Laravel version

## [1.6.1] - 2025-03-31

### ⚙️ Miscellaneous Tasks

- Update IDE helper models

## [1.5.3] - 2025-03-20

### ⚙️ Miscellaneous Tasks

- Update submodule commits for Cms and Core modules

## [1.5.2] - 2025-03-11

### ⚙️ Miscellaneous Tasks

- Update IDE helper models and environment configuration

## [1.5.1] - 2025-03-07

### ⚙️ Miscellaneous Tasks

- Add Swagger documentation for App, Cms, and Core modules

## [1.5.0] - 2025-03-07

### 💼 Other

- Add Laravel 12 support

### ⚙️ Miscellaneous Tasks

- Update project dependencies and configuration files

## [1.4.0] - 2025-03-03

### 🚀 Features

- Add script for comprehensive Composer dependency updates across modules

## [1.3.0] - 2025-03-03

### 🚜 Refactor

- Modernize PHP code and update project configuration

## [1.2.1] - 2025-02-26

### ⚙️ Miscellaneous Tasks

- Update Laravel and dependencies to latest versions

## [1.2.0] - 2025-02-19

### ⚙️ Miscellaneous Tasks

- Update Prettier configuration and upgrade project dependencies
- Update IDE helper models with generic collection type hints

## [1.1.1] - 2025-02-04

### 🚀 Features

- *(swagger)* Expand Core module API documentation with comprehensive routes

## [1.1.0] - 2025-01-29

### 🚀 Features

- *(swagger)* Enhance API documentation with module-specific tags

## [1.0.0] - 2025-01-25

### 🚀 Features

- *(cache)* Imbastite classi cache
- *(inpsector)* Imbastite classi inspector
- *(locking)* Imbastite classi locking
- *(database)* Imbastiti migrations, factories, seeders
- *(traduzioni)* Imbastite traduzioni
- *(env)* Imbastiti env files
- *(fortify)* Imbastite classi fortify
- *(configs)* Imbastiti config files
- *(database)* Imbastite base migrations
- *(commands)* Imbastiti comandi modulo Core
- *(helpers)* Imbastiti helpers modulo Core
- *(app)* Imbastita app base
- *(routes)* Imbastite rotte modulo Core
- *(griglie)* Imbastite classi griglie
- *(config)* Imbastiti Core config
- *(core)* Imbastito modulo core
- *(logs)* Imbastito log viewer
- *(core)* Correzioni rotte, introduzione Gelf logger, personalizzazione rotte fortify
- *(gelf)* Gelf logger custom classes
- *(elasticsearch)* Bozza search route con elastisearch e vector search
- *(elasticsearch)* Mappati operatori where elasticsearch
- *(swagger)* Working on swagger doc generation
- *(cms)* Definizioni modelli cms
- *(cms)* WIP aggiunta relazione belongsToMany con chiavi multiple
- *(vue)* Riaggiunte dipendenze per generazione frontend e viste
- *(scripts)* Enhance version management script

### 🐛 Bug Fixes

- *(versions)* Fixed models versioning configurations
- *(responsebuilder)* Modificato ResponseBuilder per permettere serializzazione in Cache
- *(crud)* Fixed group_by names in crud operations

### 💼 Other

- *(dependencies)* Updated dependencies
- Introduzione modulo cms
- Definizione tabelle e seeders
- Rinominato module cms
- Seeders cms
- Cms factories
- Contenuti
- Cursorrules
- Splitting project into submodules

### 🚜 Refactor

- *(versioning)* Commit prima di rollback a overtrue/laravel-versionable
- *(various)* Work in progress refactoring from L10 to L11
- *(grids)* Dynamic GridUtils injection
- *(crud)* Working on CrudController refactoring
- *(core)* Moved dependencies and classes to Core module
- *(grids)* Working on grids functionalities
- *(modules)* Code adapting for new modules package version
- *(dpeendencies)* Rimosse dipendenze frontend
- *(cms)* Standardize naming conventions, enhance model definitions, and implement new features
- *(cms)* Enhance content retrieval and module configuration

### 📚 Documentation

- *(phpdoc)* Documentazione per phpstan

### ⚡ Performance

- *(cache)* DynamicEntity unmapped models caching for better performances

### 🧪 Testing

- *(pest)* Sostituito phpunit con pest

### ⚙️ Miscellaneous Tasks

- *(references)* References inf oand icons
- *(dependencies)* Removed unused configurations for old dependencies
- *(classes)* Strict type added to main classes
- *(typechecking)* Type cheking with php-stan
- Update IDE helper files, configuration, and dependencies
- Add Core and Cms modules as git submodules
- Add version management and git hooks scripts

<!-- generated by git-cliff -->
