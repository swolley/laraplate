---
name: MES requirements revision
overview: Revisione mirata di .kiro/specs/mes-module/requirements.md per allineare il documento ai pattern e alle realtà del modulo ERP (verificate sul codice in repo e sul piano di produzione ERP), prima di scrivere design.md e poi il piano di sviluppo TDD.
todos:
  - id: patch-req-4-2-numbering
    content: Riscrivere Req 4.2 per usare DocumentType/DocumentSequence ERP (estensione enum + DocumentNumberAllocator) anziché numerazione custom
    status: pending
  - id: patch-req-4-8-so-link
    content: "Riscrivere Req 4.8/4.9 con doppia FK denormalizzata `sales_order_id` (header) + `sales_order_line_id` (riga), entrambe nullable, vincolo applicativo di coerenza tra i due, allineato al pattern DeliveryNote/DeliveryNoteLine"
    status: pending
  - id: patch-req-7-1-tracing-type
    content: Specificare in Req 7.1 il pattern di migration MES che aggiunge `tracing_type` a `items` con up/down completi e default `none`
    status: pending
  - id: patch-req-12-stockmovementrecorder
    content: "Riscrivere Req 12.4/12.5 con interfaccia `StockMovementRecorder` lato MES, adapter `MesStockMovementRecorderAdapter` lato ERP che delega al `StockMovementService` esistente (verificato in ERPServiceProvider riga 53), DTO `StockMovementCommand` con campi tipizzati"
    status: pending
  - id: patch-req-2-3-locks
    content: Esplicitare in Req 2.6 e 3.6 l'uso di `HasLocks` Core + observer per il lock di BOM/Routing al rilascio del ProductionOrder
    status: pending
  - id: add-req-9-7-materialization
    content: Aggiungere Req 9.7 sulla materializzazione via job/cache di CapacityLoad/OEE per evitare ricalcoli on-demand
    status: pending
  - id: add-req-15-events
    content: Aggiungere nuovo Req 15 sugli eventi applicativi MES con elenco esplicito dei domain events e payload tipizzato
    status: pending
  - id: add-req-16-audit-versioning
    content: Aggiungere nuovo Req 16 sull'audit/versioning DIFF dichiarato sulla classe per i modelli ad alto valore (Production, Consumption, Quality, NC, Downtime, Lot, Serial)
    status: pending
  - id: add-constraints-section
    content: Aggiungere in fondo al documento sezione Constraints (multi-currency, Party rename, Place su Warehouse) come vincoli trasversali
    status: pending
  - id: extend-glossary
    content: Estendere Glossary con DocumentNumberAllocator, MesStockMovementRecorderAdapter, VersionStrategy
    status: pending
  - id: self-review-pass
    content: "Spec self-review inline: scan placeholder/TODO, coerenza interna, ambiguità risolte"
    status: pending
  - id: user-review-gate
    content: Presentare il file aggiornato per review umano prima di passare a design.md
    status: pending
isProject: false
---


# Revisione requirements.md MES

Obiettivo: portare il file [requirements.md](.kiro/specs/mes-module/requirements.md) da "abbozzo solido" a "spec implementabile", correggendo divergenze con la realtà ERP e introducendo i requisiti trasversali oggi mancanti. Nessuna modifica di codice o configurazione: solo il file markdown.

## Cosa NON cambia

- Struttura EARS, glossario, numerazione e User Story dei 14 requisiti restano stabili.
- Il glossario rimane in cima e viene esteso con i nuovi termini (`DocumentNumberAllocator`, `MesStockMovementRecorderAdapter`, `VersionStrategy`).

## Modifiche puntuali per requisito

- **Req 2.6 / 3.6 — Lock BOM/Routing.** Esplicitare l'uso del trait Core `HasLocks` (vedi pattern già usato su `Quotation`/`SalesOrder` in ERP) + observer che lockano la versione di BOM/Routing alla prima associazione a un `ProductionOrder` rilasciato.
- **Req 4.2 — Numerazione PO MES.** Sostituire "univoco per Company e anno" con: estensione di `Modules\ERP\Casts\DocumentType` con `case ProductionOrder = 'production_order'` + uso di `DocumentNumberAllocator` con `defaultGapAllowed() = true` (coerente con `purchase_order`/`sales_order`). Pattern di estensione enum già presente: [Modules/ERP/database/migrations/2026_05_04_100000_extend_document_sequences_enum_for_purchase_order.php](Modules/ERP/database/migrations/2026_05_04_100000_extend_document_sequences_enum_for_purchase_order.php).
- **Req 4.8 / 4.9 — `ProductionOrder` ↔ `SalesOrder`.** Decisione presa: doppia FK denormalizzata, entrambe nullable.
  - `production_orders.sales_order_id` (FK opzionale a `sales_orders.id`): legame a livello header per query rapide e per casi PO "su scorta destinata a cliente noto".
  - `production_orders.sales_order_line_id` (FK opzionale a `sales_order_lines.id`): legame riga-a-riga per evasione precisa.
  - Vincolo applicativo: se entrambe valorizzate, `sales_order_line.sales_order_id == sales_order_id` (pattern coerente con `DeliveryNote`/`DeliveryNoteLine`).
  - Cardinalità: 1 riga SO → N PO (consente split lotto), 1 PO → 0..1 riga SO (un PO non evade più righe SO contemporaneamente).
- **Req 7.1 — `tracing_type` su `items`.** Specificare:
  - Migration MES con `up()` che aggiunge colonna `tracing_type` (enum `none|lot|serial`, default `none`) e `down()` che la rimuove.
  - Nessuna modifica delle migration originali ERP.
  - Pattern allineato a P0 Core/Cms (es. `locations.place_id`).
- **Req 12.4 / 12.5 — Contratto `StockMovementRecorder`.** Riformulare:
  - Il MES definisce l'interfaccia `Modules\MES\Contracts\StockMovementRecorder` con un metodo `record(StockMovementCommand $command): StockMovementResult`.
  - L'ERP fornisce un adapter (`Modules\ERP\Services\Inventory\Adapters\MesStockMovementRecorderAdapter`) che internamente delega ai metodi reali di `StockMovementService` ERP (`recordInbound(...)` per resa prodotto finito, `issue(...)` per consumo componenti) — verificato che `StockMovementService` esiste in ERP: registrato come singleton in [Modules/ERP/app/Providers/ERPServiceProvider.php](Modules/ERP/app/Providers/ERPServiceProvider.php) riga 53, iniettato in `DeliveryNoteInventoryService` e `GoodsReceiptInventoryService`, testato in [Modules/ERP/tests/Feature/DeliveryNoteInventoryServiceTest.php](Modules/ERP/tests/Feature/DeliveryNoteInventoryServiceTest.php).
  - DTO `StockMovementCommand` con campi tipizzati (Req 12.5): `item_id`, `warehouse_id`, `quantity`, `unit_cost?`, `direction` (enum `inbound|issue`), `company_id`, `reference` (morph al `ProductionOrder` / `MaterialConsumption`), `occurred_at`.
  - DTO `StockMovementResult`: contiene `stock_movement_id`, `unit_cost_actual` (per scarichi FIFO il costo reale può differire da quello richiesto), `cost_layers_consumed` (debug/audit).
  - Implementazione di default registrata in `MESServiceProvider` con binding condizionale: se ERP è attivo, usa l'adapter ERP; altrimenti il MES non può funzionare e fallisce esplicitamente al boot (Req 12.1: ERP è dichiarato dipendenza required).
  - Apertura a ERP esterni: un modulo bridge separato può fornire un'altra implementazione del contratto `StockMovementRecorder` (rebind nel container).

## Aggiunte trasversali (nuovi requisiti)

- **Nuovo Req 15 — Eventi applicativi MES.** Lista esplicita degli eventi che il modulo emette, allineata al pattern AI/ERP (observer + listener):
  - `ProductionOrderReleased`, `ProductionOrderCompleted`, `ProductionOrderCancelled`
  - `OperationStarted`, `OperationCompleted`, `OperationSkipped`
  - `MaterialConsumed`, `LotCreated`, `LotConsumed`, `SerialNumberAssigned`
  - `QualityCheckPerformed`, `NonConformanceOpened`, `NonConformanceResolved`
  - `DowntimeStarted`, `DowntimeEnded`
  Acceptance: ogni evento dichiara il payload tipizzato e i listener possono essere registrati esternamente.
- **Nuovo Req 16 — Audit/Versioning sui modelli ad alto valore.** Esplicitare che `ProductionOrder`, `MaterialConsumption`, `NonConformance`, `QualityCheck`, `Downtime`, `LotNumber`, `SerialNumber` dichiarano `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;` (stesso pattern del todo `enforce-versioning-on-accounting-models` ERP, vedi [.cursor/plans/nebula_verso_business_0d6eb0ed.plan.md](.cursor/plans/nebula_verso_business_0d6eb0ed.plan.md)). Disattivazione via `Setting` non consentita.
- **Nuovo Req 9.7 (estensione Req 9) — Materializzazione metriche.** I calcoli onerosi (`CapacityLoad`, `OEE`, KPI di Req 11) non sono on-demand: vengono materializzati via job schedulati e cache (es. `production_capacity_snapshots`), per evitare query ricorsive sull'API.

## Annotazioni trasversali (sezione "Constraints" da aggiungere in fondo)

- **Multi-currency**: il MES non aggiunge campi monetari nei propri modelli core in V1. Se in futuro entrano (`cost_at_completion`, `scrap_value`) usano `ERPMigrateUtils::moneyColumns()` ERP per coerenza dual-currency.
- **`Party` (rinomina `Customer`)**: il MES non ha riferimenti a `customers`; quando ERP M3.6 rinominerà in `parties`, MES non subisce impatto.
- **`Place` su `Warehouse`**: opzionale e differibile; eventuale riferimento `WorkCenter.place_id` viene introdotto solo dopo che ERP allinea anche `warehouses.place_id`.

## Ordine di lavoro proposto (3 micro-step)

1. Aggiornare in-place [`.kiro/specs/mes-module/requirements.md`](.kiro/specs/mes-module/requirements.md) con le modifiche puntuali ai Req 2/3/4/7/12 + aggiunta Req 9.7, Req 15, Req 16, sezione Constraints, e i nuovi termini nel Glossary.
2. Spec self-review inline: scan placeholder/TODO, coerenza tra requisiti, nessuna ambiguità tra "MES definisce contratto" e "ERP fornisce adapter".
3. Presentare al tuo review umano. Solo dopo OK passare a popolare `design.md` (modelli, ER, contratti, eventi, layering).

## Decisioni prese in fase di revisione (confermate)

- **Legame PO↔SO**: doppia FK denormalizzata `sales_order_id` + `sales_order_line_id`, entrambe nullable, vincolo applicativo di coerenza. Razionale: coerenza con pattern ERP `DeliveryNote`/`DeliveryNoteLine`, granularità per riga + query rapide all'header, gestione naturale di PO "su scorta" (entrambe null) e PO "su commessa" (entrambe valorizzate).
- **`StockMovementService` ERP esistente**: confermato via ispezione codice (`ERPServiceProvider` registra il singleton, `DeliveryNoteInventoryService`/`GoodsReceiptInventoryService` lo iniettano, test feature lo invocano direttamente). Il MES dichiara l'interfaccia `StockMovementRecorder` e ERP fornisce l'adapter di default. Nessuna assunzione, è una dipendenza accertata.

## Cosa NON faccio in questo step

- Non popolo `design.md` (è il passo successivo).
- Non scrivo il piano di task TDD.
- Non tocco codice, migration, composer.json, configurazioni.
- Non aggiungo file in `Modules/MES/`.
