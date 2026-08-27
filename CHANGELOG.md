# Changelog

All notable changes to this project will be documented in this file.

## [unreleased]

### 🐛 Bug Fixes

- *(tests)* Force English locale in phpunit and bump CMS/ERP

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

- *(ai)* Gate CRUD tools by permission, drop approval-for-unpermitted
- *(tests)* Silence file logging during Pest runs

### 📚 Documentation

- *(ai)* Spec RAG R0 — documentation evaluation baseline
- *(versioning)* Milestone-1 membership-vector plan + confirmed scope
- *(mes)* Revise API architecture and record cloud provisioning
- *(mes)* Close module — Filament resources + manual consumption follow-up
- *(mes)* RAG user and developer docs for the new capabilities
- Reconcile completed Core/ERP quick-win plans with shipped code
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
- *(import)* Mark spec #3 decisions shipped + bump Core gitlink
- Reference the SAO submodule in the root README

### ⚡ Performance

- *(crud)* Bump Core submodule for CRUD query/discovery optimizations
- *(core)* Bump Core submodule for entity-resolution index

### ⚙️ Miscellaneous Tasks

- *(sao)* Bump SAO through the phase 1a configuration surfaces
- *(sao)* Close slice 1a — the internal ticketing core
- Bump Modules/AI submodule for RAG R0 documentation evaluation baseline
- *(mes)* Bump MES submodule for Tasks 4-5 (BOM + Routing)
- *(mes)* Bump MES + ERP submodules for Task 6 (production orders)
- *(mes)* Bump MES submodule for Tasks 15-17 and record completion
- *(mes)* Bump Core+MES submodules; MES suite green on PHP 8.5
- *(sao)* Bump SAO submodule — phase 3a complete (conformance, wiring, docs)
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
- Bump SAO submodule (ingest-events read-only Filament surface)
- Bump SAO submodule (signal-to-ticket auto-open)
- Bump Core submodule (CRUD facet-counters tier 2)
- Bump Core submodule (facet tier 2 relation labels)
- Bump Core submodule (facet label search/sort)
- Bump SAO submodule (SourceProfile Filament CRUD)
- Bump Core submodule (PerfCrud test-ordering pollution fix)
- Bump module gitlinks to released versions
- Bump SAO gitlink to v0.3.0
- *(sao)* Bump submodule — resumable tracker import + history
- *(import)* Bump Core+SAO submodules — generic bulk import (Fase A+B)
- *(import)* Bump Core/CMS/ERP submodules — Tier 1 importable entities
- Bump SAO, ERP, and Core module pointers for test fixes

## [1.13.6] - 2026-08-04

### 🐛 Bug Fixes

- *(tests)* Prevent Pest CallsTerminable shutdown failures

### ⚙️ Miscellaneous Tasks

- Bump CMS, Core, and ERP for optional import console output

## [1.13.5] - 2026-08-04

### 🚀 Features

- *(filament)* Bump modules for generate→trait merge
- Delegate root DatabaseSeeder to the Core orchestrator

### 🐛 Bug Fixes

- Bump Core submodule for the seed orchestrator review fixes

### 📚 Documentation

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
- *(sao)* Distinguish release candidates from shipped releases
- *(sao)* Record two migration constraints the plan got wrong
- *(core)* Unblock Task 11 and fix its multi-submodule commit protocol

### ⚙️ Miscellaneous Tasks

- Bump Core for observer registration and versionable image fixes
- *(sao)* Register SAO as a submodule
- *(sao)* Bump SAO to the phase 0 scaffolding state

## [1.13.4] - 2026-07-29

### 🚀 Features

- *(filament)* Default Toggle fields to stacked label layout
- *(filament)* Ship make-resources docs and App panel discovery

### 🐛 Bug Fixes

- *(filament)* Render stacked locale flags as circles

### 🚜 Refactor

- Replace pxlrbt/filament-environment-indicator with Core plugin and module version dropdown

### ⚙️ Miscellaneous Tasks

- Update Core and AI submodule pointers for PSR-4 test stubs

## [1.13.2] - 2026-07-12

### ⚙️ Miscellaneous Tasks

- *(modules)* Update ERP submodule reference and document progress on Phase 2A and 2B

## [1.13.1] - 2026-07-10

### 🐛 Bug Fixes

- *(tests)* Stabilize suite bootstrap and bump module refs

### ⚙️ Miscellaneous Tasks

- *(modules)* Bump AI, CMS, Core, and ERP submodules
- *(modules)* Update submodule references for AI, CMS, Core, and ERP
- *(modules)* Update CMS submodule reference to latest commit

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

### 📚 Documentation

- *(plan)* Mark Filament ERP core slice in Nebula roadmap
- *(plan)* Align Nebula plan with ERP module paths and naming
- Add CMS comments with AI moderation design spec
- Revise CMS comments spec per review feedback
- Add preliminary AI disapproval and implementation plan
- Clarify locale read rule and approval Option A/B
- Use HasTranslations for comments with locale overrides
- Add approval_mode config for comment moderation A/B
- Add RTK documentation and update AGENTS.md
- *(erp)* Add Spec 1 design for v1 hardening (bugs + money math)
- *(erp)* Add Fix 8 (CRUD write guard) to Spec 1 hardening
- *(erp)* Refine Spec 1 hardening scope after code/test verification
- *(erp)* Add Spec 1 hardening implementation plan
- *(erp)* Update plans and specs for ERP hardening progress

### ⚙️ Miscellaneous Tasks

- Update package versions and submodule references
- Update composer.lock with new package versions and dependencies
- Remove CLAUDE.md and update dependencies
- Update submodule references and composer.lock content-hash
- Add Laravel Boost guidelines and update dependencies
- Update submodule references for Cms and Core modules
- Update dependencies and improve component functionality
- Update Core submodule and add pagination component
- Remove IDE helper files and update .gitignore
- Update Cms submodule to latest commit
- Enhance testing setup and update dependencies
- Update submodule references for AI, Cms, and Core modules
- Update filament packages and submodule references
- Update phpunit configuration and Core submodule reference
- Update module activators and testing configuration
- Update dependencies and add sidebar scroll functionality
- Update database configuration for read/write semantics
- Enhance coding principles documentation
- Update composer dependencies and configuration settings
- Update filament packages to version 5.3.5 and enhance documentation
- Update dependencies and enhance performance optimizations
- Update dependencies and submodule references
- Add new CRM module and update dependencies
- Update module structure and dependencies
- Update submodule references and enhance seeder functionality
- Add Business module as a subproject
- Update .env.example and submodule references
- Update .gitignore, add cursor settings, and update submodule references
- Bump submodules to B0+B1 milestones and sync ERP plan
- Bump Business submodule to B2 (M0-ERP) and sync plan
- Bump Business submodule and update Nebula ERP plan
- *(business)* Bump submodule for Filament accounting domain
- Update dependencies and enhance test configurations
- Add MES module and update configurations
- Update submodule references and clean up package-lock.json
- Add MES requirements revision document
- Update ERP submodule and development plan for M3.5-M5
- Add cache duration registry to configuration
- Update dependencies and refine documentation
- *(plans)* Add initial ecommerce module development plan
- *(plans)* Mark ecommerce module development plan as completed
- Update dependencies and add bugfix for HasValidations trait
- Complete performance optimization tasks in documentation
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
- Update composer.lock and package-lock.json for new dependencies
- Update submodule references and API documentation
- Bump Core/CMS/AI/ERP for trait Concerns refactor
- Bump module references and align app bootstrap config
- Sync module releases and ERP hardening progress
- Sync submodule references after PHPDoc formatting cleanup
- Update submodule references for AI and CMS modules

## [1.11.2] - 2026-01-22

### ⚙️ Miscellaneous Tasks

- Update submodule references and enhance Swagger documentation
- Enhance README and update submodule references
- Update package versions and submodule references

## [1.11.1] - 2026-01-15

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

- *(scripts)* Enhance version management script

### 🚜 Refactor

- *(cms)* Standardize naming conventions, enhance model definitions, and implement new features
- *(cms)* Enhance content retrieval and module configuration

### ⚙️ Miscellaneous Tasks

- Update IDE helper files, configuration, and dependencies
- Add Core and Cms modules as git submodules
- Add version management and git hooks scripts

<!-- generated by git-cliff -->
