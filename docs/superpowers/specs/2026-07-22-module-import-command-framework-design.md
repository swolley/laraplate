# Module-owned import commands on Core infrastructure

**Status:** Core/CMS implemented; ERP entry point and source integration pending

**Date:** 2026-07-22

**Modules:** Core, CMS, ERP

**Related backlog:** ERP `4-09`

## Decision summary

Laraplate will extract the source-agnostic execution mechanics currently owned by the CMS bulk-import command into Core. Core will provide an abstract, non-runnable command plus neutral execution contracts and support services. It will not expose a generic `core:import` command.

Each destination module owns a concrete `ImportCommand` in its namespace:

```text
Modules\CMS\Console\ImportCommand  -> cms:import
Modules\ERP\Console\ImportCommand  -> erp:import
```

Both commands inherit the same arguments and options from Core. They differ through their Artisan name, colored module suffix, injected module resolver, accepted marker interface, destination pipeline, and documentation.

The command name selects the destination module. `--importer` selects the concrete source adapter. The importer owns the source-specific entity sequence and mapping. Core does not infer entities from class names, directories, tables, or module internals.

ERP `4-09` is split into two deliverables:

1. the reusable Core command framework plus CMS migration and ERP entry point;
2. the concrete Symfony legacy adapter, implemented only after the real source schema, sample data, and reconciliation totals are available.

## Why no runnable Core import command

`core:import` would be operationally ambiguous. An operator should see the destination boundary before executing a mutation:

```bash
php artisan cms:import --importer='Acme\Importers\AcmeApiImporter'
php artisan erp:import --importer='Legacy\Importers\SymfonyErpImporter'
```

Module-owned command names provide:

- an explicit destination domain;
- module-specific help and colored listing suffix;
- module-specific importer validation;
- a stable place for future module-only preflight checks;
- no dynamic routing from a user-provided module name.

Laravel's registered module commands are the command registry. No second global importer registry is required for this design.

## Existing behavior to preserve

The current CMS command contract remains the baseline:

```text
--importer=
--bootstrap=
--arg=*
--dry-run
--limit=
--no-search
```

The Core parent defines these options programmatically through `getOptions()`. Concrete commands define `$name`, not `$signature`; Laravel then calls `specifyParameters()` and inherits the parent options. There are currently no positional arguments.

The following CMS behavior must remain compatible:

- explicit importer FQCN resolution;
- optional external Composer bootstrap;
- repeated `key=value` constructor arguments;
- `dryRun` and `limit` named constructor parameters;
- interactive sibling `laraplate-importers` discovery;
- importer class validation;
- optional Scout suppression;
- imported record count and exit-code behavior;
- existing `cms:import` command name.

Do not add `--entity`, `--resume`, or other speculative options in this extraction. A future importer can first receive source-specific values through `--arg`; a common option is added only after at least two module commands share stable semantics.

## Ownership boundaries

### Core owns

- `AbstractImportCommand`, which is not registered as an Artisan command;
- neutral `BulkImporterInterface` with the current `import(): int` contract;
- `BulkImportRunner`;
- neutral importer resolution and external plugin discovery contracts;
- parsing and validation of common options;
- bootstrap loading;
- common console messages and exit codes;
- importer-selected-or-default single-connection transactional dry-run orchestration;
- temporary Scout suppression requested by `--no-search` or `--dry-run`.

Core must contain no CMS/ERP entity names, DTOs, model references, table names, source mappings, or module-specific configuration.

### CMS owns

- concrete `Modules\CMS\Console\ImportCommand` with `$name = 'cms:import'`;
- CMS importer resolver and CMS marker interface;
- CMS import DTOs and `ImportGraphDto`;
- source-to-CMS mapper contracts;
- CMS pipeline, upserters, preset provisioning, reference resolution, and post-processing;
- CMS-specific importer tests and documentation.

### ERP owns

- concrete `Modules\ERP\Console\ImportCommand` with `$name = 'erp:import'`;
- ERP importer resolver and ERP marker interface;
- ERP destination DTOs only where services cannot accept existing typed inputs;
- validation and ordering of ERP entity imports;
- calls to ERP domain services for projects, tasks, time entries, quotations, journals, inventory, and other supported targets;
- ERP-specific importer tests and documentation.

### External importer packages own

- source credentials and connection configuration;
- API clients, SQL readers, dump parsers, and source-specific DTOs;
- source normalization and mapping into the destination module contract;
- concrete importer classes;
- source fixtures and source-specific tests.

External packages may depend on Core and the destination module at runtime. Laraplate modules must not depend on a specific external importer package.

## Core command shape

Conceptual API:

```php
abstract class AbstractImportCommand extends Command
{
    public function __construct(
        private readonly BulkImportRunner $runner,
        private readonly BulkImporterResolverInterface $resolver,
        private readonly ImportPluginDiscoveryInterface $discovery,
    ) {
        parent::__construct();
    }

    final public function handle(): int;

    #[Override]
    protected function getOptions(): array;
}
```

The concrete module command injects concrete module-aware objects and passes them to the parent:

```php
final class ImportCommand extends AbstractImportCommand
{
    protected $name = 'cms:import';

    public function __construct(
        BulkImportRunner $runner,
        CmsBulkImporterResolver $resolver,
        CmsImportPluginDiscovery $discovery,
    ) {
        parent::__construct($runner, $resolver, $discovery);
    }
}
```

ERP uses the same class name under its own namespace and injects ERP equivalents.

The exact split between resolver and discovery may be collapsed during implementation if one module-aware resolver can own both responsibilities without losing testability. The architectural requirement is that Core depends on neutral contracts and each concrete command supplies destination-specific validation objects.

## Importer contracts and module safety

Core defines the executable minimum:

```php
interface BulkImporterInterface
{
    public function import(): int;
}
```

Destination modules define marker contracts:

```php
interface CmsBulkImporterInterface extends CoreBulkImporterInterface {}
interface ErpBulkImporterInterface extends CoreBulkImporterInterface {}
```

The CMS resolver accepts only `CmsBulkImporterInterface`; the ERP resolver accepts only `ErpBulkImporterInterface`. Passing a CMS importer to `erp:import`, or the reverse, fails before execution with a clear error.

The existing `Modules\CMS\Import\Contracts\BulkImporterInterface` remains as the CMS marker during the compatibility period. Existing Acme importers therefore remain valid after it is changed to extend the Core contract. A later major release may rename it to `CmsBulkImporterInterface`; that rename is outside this extraction.

## How entities are selected

Core does not decide what to import.

The destination is selected by the command:

```text
cms:import -> CMS only
erp:import -> ERP only
```

The concrete importer decides its supported source and entity flow. For example, the Symfony ERP importer may process parties before projects, projects before tasks and time entries, and fiscal records only after all referenced master data exists. Source-specific filters can use the existing repeated `--arg=key=value` mechanism.

The destination module validates every write through its own pipeline and domain services. An external importer must not use raw writes to bypass ERP posting, numbering, lock, inventory, accounting, or audit rules.

## Dry-run boundary

The runner wraps execution in a transaction on the connection returned by an optional `ConnectionAwareBulkImporterInterface`. Legacy importers fall back to the current default connection. This guarantees rollback only for database writes made on that selected connection.

It does not automatically roll back:

- writes on any other connection;
- files or object storage;
- queues already dispatched;
- HTTP calls;
- search indexing outside the disabled Scout driver;
- provider-specific external side effects.

Therefore documentation and console output must refer to the selected database transaction, not claim that every possible side effect was reverted. Importers executed with `--dry-run` must inspect the injected `dryRun` constructor parameter and suppress non-transactional side effects. Multi-connection dry-run support requires explicit importer orchestration and tests; it is not implied by the Core runner.

## Error and result contract

This extraction preserves `import(): int` to avoid an unnecessary breaking change for existing external importers. The command reports the returned imported count and maps expected validation/resolution failures to `Command::FAILURE`.

A richer `ImportResult` with read/imported/updated/skipped/failed counters is a valid future evolution, but it is not required to extract the command. It must be introduced as a separate compatibility decision rather than hidden inside ERP `4-09`.

## ERP Symfony adapter gate

The concrete legacy importer is not implementable responsibly from the conceptual mapping alone. Before that task starts, provide:

- source database engine and supported access method;
- schema or schema dump;
- anonymized representative records;
- stable source identifiers;
- expected row counts and financial/inventory control totals;
- source timezone, currency, and decimal conventions;
- rules for deleted, duplicate, incomplete, and historical records;
- decision on one-shot migration versus repeatable synchronization.

The current conceptual mapping remains guidance, not an executable contract:

| Legacy concept | ERP destination |
|---|---|
| Client / Contact | parties and contacts |
| Work | projects |
| Appointment | tasks |
| WorkSession | time entries |
| Quotation | quotations and revision lineage |
| PriceList | price lists and items |
| Movement | journal entries through accounting services |
| Warehouse movement | stock movements through inventory services |
| Transfer | partner pool and settlements |

## Rejected alternatives

### Runnable `core:import`

Rejected because the destination is not visible in the command name and Core would need dynamic module routing.

### Duplicate standalone commands

Rejected because bootstrap loading, argument parsing, dry-run, limit, discovery, errors, and output would diverge between modules.

### Put CMS DTOs and pipelines in Core

Rejected because content graphs, presets, contributors, tags, and locations are CMS domain concepts.

### One abstract importer pipeline for every module

Rejected because CMS graph upserts and ERP transactional/accounting imports have materially different ordering and invariants. The shared abstraction is execution orchestration, not destination business logic.

### Infer module from importer namespace

Rejected because namespaces are not a security or domain contract. Marker interfaces and module resolvers provide explicit validation.

## Acceptance criteria

- Core exposes no runnable import command.
- `cms:import` remains backward compatible.
- `erp:import` exposes the same common options.
- Parent options are defined through `getOptions()`; concrete commands use `$name`, not `$signature`.
- `artisan list` shows distinct colored CMS and ERP suffixes.
- Each module rejects importers implementing another module's marker contract.
- Existing external bootstrap and sibling discovery still work.
- Core contains no CMS or ERP domain dependency.
- CMS DTOs/pipeline remain in CMS.
- ERP writes are delegated to ERP services.
- Dry-run limitations are documented and tested honestly.
- The Symfony adapter remains gated until source evidence is available.
