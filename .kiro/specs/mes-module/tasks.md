# Implementation Plan: Modulo MES

## Overview

Piano di implementazione del modulo MES (Manufacturing Execution System) per Laraplate. Il modulo dipende da ERP come dipendenza dichiarata e gestisce: work center, BOM, routing, ordini di produzione, consumo materiali, tracciabilità lotti, controllo qualità, scheduling, fermi macchina e turni.

## Task Dependency Graph

```json
{
  "waves": [
    { "wave": 1, "tasks": ["T1"] },
    { "wave": 2, "tasks": ["T2", "T3"] },
    { "wave": 3, "tasks": ["T4", "T5"] },
    { "wave": 4, "tasks": ["T6"] },
    { "wave": 5, "tasks": ["T7", "T11", "T13"] },
    { "wave": 6, "tasks": ["T8", "T12"] },
    { "wave": 7, "tasks": ["T9", "T10"] },
    { "wave": 8, "tasks": ["T14"] },
    { "wave": 9, "tasks": ["T15"] },
    { "wave": 10, "tasks": ["T16"] },
    { "wave": 11, "tasks": ["T17"] }
  ]
}
```

## Tasks

### T1 — Scaffolding del modulo MES

- [x] 1. Creare la struttura del modulo `Modules/MES/` con `module.json`, `composer.json`, `config/config.php`
- [x] 2. Dichiarare dipendenza da ERP in `module.json` (requires) e `composer.json`
- [x] 3. Creare `MESServiceProvider`, `EventServiceProvider`, `RouteServiceProvider`
- [x] 4. Creare il contratto `Modules\MES\Contracts\StockMovementRecorder`
- [x] 5. Creare il DTO `Modules\MES\Data\StockMovementData`
- [x] 6. Creare `Modules\MES\Services\ErpStockMovementRecorder` (nel MES, non nell'ERP) che implementa il contratto usando `Modules\ERP\Services\Inventory\StockMovementService`
- [x] 7. Registrare il binding `StockMovementRecorder → ErpStockMovementRecorder` nel `MESServiceProvider` (il MES conosce ERP, l'ERP non sa nulla del MES)
- [x] 8. Aggiungere `Modules/MES` a `modules_statuses.json`
- [ ] 9. Creare `Modules/MES/tests/Pest.php` e `TestCase.php`

**Requisiti coperti**: R12

---

### T2 — Migration aggiuntiva su tabella ERP

- [x] 1. Creare migration MES che aggiunge `tracing_type` enum(`none`, `lot`, `serial`) default `none` alla tabella `items`
- [x] 2. Aggiungere `tracing_type` al `$fillable` e ai `casts()` del modello `Item` dell'ERP
- [ ] 3. Scrivere test: `Item` con `tracing_type` corretto viene letto dal MES tramite relazione Eloquent

**Requisiti coperti**: R7

---

### T3 — Work Center

- [x] 1. Creare migration `mes_work_centers` (company_id FK, code, name, type enum, capacity_per_hour, capacity_uom, is_active)
- [x] 2. Creare migration `mes_work_center_calendars` (work_center_id FK, day_of_week, start_time, end_time)
- [x] 3. Creare modello `WorkCenter` (final, BelongsToCompany, getRules, relazioni)
- [x] 4. Creare modello `WorkCenterCalendar` (final, belongsTo WorkCenter)
- [x] 5. Creare Form Request `WorkCenterRequest` con validazione unicità codice per company
- [ ] 6. Scrivere test: creazione, aggiornamento, disattivazione, unicità codice per company

**Requisiti coperti**: R1

---

### T4 — Distinta Base (BOM)

- [x] 1. Creare migration `mes_boms` (company_id FK, item_id FK → items, version, valid_from, valid_to, is_active)
- [x] 2. Creare migration `mes_bom_lines` (bom_id FK, item_id FK → items, quantity, uom, consumption_method enum, sort_order)
- [ ] 3. Creare modello `Bom` (final, BelongsToCompany, relazioni: item ERP, bomLines)
- [ ] 4. Creare modello `BomLine` (final, relazioni: bom, item ERP)
- [ ] 5. Creare `BomExplosionService` con metodi `getActiveBom()` e `explode()`
- [ ] 6. Implementare risoluzione versione attiva (valid_from ≤ date, valid_to null o ≥ date)
- [ ] 7. Implementare lock BOM se associata a ProductionOrder rilasciato
- [ ] 8. Scrivere test: versioning BOM, esplosione multi-livello, lock su ordine rilasciato

**Requisiti coperti**: R2

---

### T5 — Routing e Cicli di Lavorazione

- [ ] 1. Creare migration `mes_routings` (company_id FK, item_id FK → items, version, valid_from, valid_to, is_active)
- [ ] 2. Creare migration `mes_routing_operations` (routing_id FK, work_center_id FK, sequence, description, setup_time_minutes, cycle_time_minutes, is_parallel)
- [ ] 3. Creare modello `Routing` (final, BelongsToCompany, relazioni: item ERP, routingOperations)
- [ ] 4. Creare modello `RoutingOperation` (final, relazioni: routing, workCenter)
- [ ] 5. Implementare risoluzione versione attiva (stesso pattern BOM)
- [ ] 6. Implementare lock Routing se associato a ProductionOrder rilasciato
- [ ] 7. Scrivere test: versioning routing, operazioni parallele, lock su ordine rilasciato

**Requisiti coperti**: R3

---

### T6 — Ordini di Produzione

- [ ] 1. Creare migration `mes_production_orders` (company_id, number, item_id FK, qty_planned, qty_produced, qty_scrapped, uom, status enum, planned/actual timestamps, warehouse_id FK, sales_order_id FK nullable, bom_snapshot json, routing_snapshot json)
- [ ] 2. Creare modello `ProductionOrder` (final, BelongsToCompany, relazioni: item ERP, warehouse ERP, salesOrder ERP, operations, materialConsumptions, lotNumbers)
- [ ] 3. Creare `ProductionOrderService` con metodi: `create()`, `release()`, `complete()`, `cancel()`
- [ ] 4. Implementare snapshot BOM/Routing alla creazione
- [ ] 5. Implementare generazione `ProductionOrderOperation` al rilascio
- [ ] 6. Implementare numerazione progressiva per company+anno
- [ ] 7. Creare `ProductionOrderObserver` per transizioni di stato
- [ ] 8. Creare `HandleSalesOrderConfirmedListener` + `CreateProductionOrderFromSalesOrderJob`
- [ ] 9. Scrivere test: snapshot immutabile, generazione operazioni, completamento parziale, unicità numero

**Requisiti coperti**: R4

---

### T7 — Esecuzione Operazioni

- [ ] 1. Creare migration `mes_production_order_operations` (production_order_id FK, routing_operation_id FK, work_center_id FK, sequence, status enum, planned/actual timestamps, quantity_produced, operator_user_id FK nullable, notes, efficiency)
- [ ] 2. Creare modello `ProductionOrderOperation` (final, relazioni: productionOrder, routingOperation, workCenter, operatorLogs)
- [ ] 3. Creare `ProductionOrderOperationObserver` che al completamento dispatcha `BackflushMaterialsJob` e crea QualityCheck se esiste piano
- [ ] 4. Implementare calcolo efficienza (tempo effettivo / tempo standard × 100)
- [ ] 5. Implementare controllo capacità work center (warning non bloccante)
- [ ] 6. Scrivere test: avvio/completamento, calcolo efficienza, transizioni di stato

**Requisiti coperti**: R5

---

### T8 — Consumo Materiali

- [ ] 1. Creare migration `mes_material_consumptions` (production_order_id FK, operation_id FK nullable, item_id FK, warehouse_id FK, lot_number_id FK nullable, quantity_theoretical, quantity_actual, uom, variance, method enum, stock_shortage)
- [ ] 2. Creare modello `MaterialConsumption` (final, relazioni: productionOrder, operation, item ERP, warehouse ERP, lotNumber)
- [ ] 3. Creare `BackflushMaterialsJob` (queued, ShouldQueue): legge BomLines backflush, crea MaterialConsumption, invoca `StockMovementRecorder`, calcola variance, gestisce stock_shortage
- [ ] 4. Implementare consumo manuale via Form Request
- [ ] 5. Scrivere test: backflush automatico, consumo manuale, calcolo variance, stock_shortage

**Requisiti coperti**: R6

---

### T9 — Tracciabilità Lotti e Numeri Seriali

- [ ] 1. Creare migration `mes_lot_numbers` (company_id FK, item_id FK, code unique per company, production_order_id FK nullable, quantity, uom, produced_at, expires_at)
- [ ] 2. Creare migration `mes_serial_numbers` (company_id FK, item_id FK, code unique per company, production_order_id FK nullable, lot_number_id FK nullable, produced_at)
- [ ] 3. Creare migration `mes_lot_lineages` (parent_lot_id FK, child_lot_id FK, production_order_id FK, quantity_used)
- [ ] 4. Creare modelli `LotNumber`, `SerialNumber`, `LotLineage` (tutti final)
- [ ] 5. Creare `LotTracingService` con metodi: `generateLotCode()`, `forwardTrace()`, `backwardTrace()`
- [ ] 6. Integrare assegnazione lotto in `ProductionOrderService::complete()`
- [ ] 7. Scrivere test: generazione codice, forward/backward trace multi-livello, bidirezionalità

**Requisiti coperti**: R7

---

### T10 — Controllo Qualità e Non Conformità

- [ ] 1. Creare migration `mes_quality_plans` (company_id FK, item_id FK nullable, routing_operation_id FK nullable, name, is_active)
- [ ] 2. Creare migration `mes_quality_plan_parameters` (quality_plan_id FK, name, uom, min_value, max_value, is_required)
- [ ] 3. Creare migration `mes_quality_checks` (operation_id FK, quality_plan_id FK, lot_number_id FK nullable, operator_user_id FK nullable, status enum, checked_at, notes)
- [ ] 4. Creare migration `mes_quality_check_measurements` (quality_check_id FK, parameter_id FK, value, is_within_limits)
- [ ] 5. Creare migration `mes_non_conformances` (company_id FK, production_order_id FK, quality_check_id FK nullable, rework_production_order_id FK nullable, description, quantity_nonconforming, root_cause, corrective_action, disposition enum nullable, status enum)
- [ ] 6. Creare modelli: `QualityPlan`, `QualityPlanParameter`, `QualityCheck`, `QualityCheckMeasurement`, `NonConformance` (tutti final)
- [ ] 7. Implementare creazione automatica QualityCheck in `ProductionOrderOperationObserver`
- [ ] 8. Implementare esecuzione QualityCheck con calcolo esito e creazione automatica NonConformance se failed
- [ ] 9. Implementare creazione ProductionOrder di rilavorazione da NonConformance con disposition=rework
- [ ] 10. Scrivere test: creazione automatica check, esito failed → non conformità, rilavorazione

**Requisiti coperti**: R8

---

### T11 — Scheduling e Pianificazione Capacità

- [ ] 1. Creare `CapacityService` con metodi: `getCapacityLoad()`, `getSchedule()`, `estimateCompletionDate()`, `checkOverload()`
- [ ] 2. Implementare ripianificazione manuale (spostamento operazione a work center/finestra diversa)
- [ ] 3. Scrivere test: calcolo carico, stima data fine, warning sovraccarico, CapacityLoad ≥ 0

**Requisiti coperti**: R9

---

### T12 — Fermi Macchina e OEE

- [ ] 1. Creare migration `mes_downtimes` (work_center_id FK, cause enum, started_at, ended_at nullable, duration_minutes nullable, notes)
- [ ] 2. Creare modello `Downtime` (final, relazione workCenter)
- [ ] 3. Creare `OeeCalculatorService` con metodo `calculate()` (Availability × Performance × Quality)
- [ ] 4. Implementare calcolo durata downtime alla chiusura
- [ ] 5. Scrivere test: OEE ∈ [0,1], calcolo su dati noti, downtime attivo → work center non disponibile

**Requisiti coperti**: R11

---

### T13 — Turni e Operatori

- [ ] 1. Creare migration `mes_shifts` (company_id FK, name, start_time, end_time, days_of_week json, is_active)
- [ ] 2. Creare migration `mes_shift_work_centers` pivot (shift_id FK, work_center_id FK)
- [ ] 3. Creare migration `mes_shift_instances` (shift_id FK, date, operator_user_id FK, work_center_id FK)
- [ ] 4. Creare migration `mes_operator_logs` (operation_id FK, operator_user_id FK, action enum, occurred_at)
- [ ] 5. Creare modelli: `Shift`, `ShiftInstance`, `OperatorLog` (tutti final)
- [ ] 6. Implementare verifica ShiftInstance attivo all'avvio operazione
- [ ] 7. Implementare creazione automatica OperatorLog a ogni avvio/completamento operazione
- [ ] 8. Implementare calcolo efficienza media per operatore e turno
- [ ] 9. Scrivere test: verifica turno attivo, log automatico, calcolo efficienza

**Requisiti coperti**: R10

---

### T14 — API REST

- [ ] 1. Creare `routes/api.php` con tutti gli endpoint sotto `/api/v1/mes/` con autenticazione Sanctum e rate limiting
- [ ] 2. Creare controller API (tutti final, method injection): WorkCenter, Bom, Routing, ProductionOrder, ProductionOrderOperation, MaterialConsumption, LotNumber, QualityCheck, NonConformance, Downtime, Schedule
- [ ] 3. Creare API Resources per ogni entità (struttura: data, meta, errors)
- [ ] 4. Scrivere test feature: 401 senza token, 403 senza permesso, 422 su dati invalidi, 200 su richieste valide

**Requisiti coperti**: R13

---

### T15 — Pannello Filament

- [ ] 1. Creare `WorkCenterResource` con form e calendario inline
- [ ] 2. Creare `BomResource` con repeater BomLines e select Item da ERP
- [ ] 3. Creare `RoutingResource` con repeater RoutingOperations
- [ ] 4. Creare `ProductionOrderResource` con pagina view (tab: Operazioni, Consumi, Qualità, Lotti), azioni (Rilascia, Completa, Cancella), link a SalesOrder ERP
- [ ] 5. Creare `QualityCheckResource` con form esecuzione misurazioni
- [ ] 6. Creare `NonConformanceResource` con workflow stati e azione crea rilavorazione
- [ ] 7. Creare `DowntimeResource` con azione chiudi fermo
- [ ] 8. Creare `ShiftResource` con assegnazione work center e operatori
- [ ] 9. Creare `ProductionDashboardWidget` (ordini in produzione, downtime attivi, quality check pending, OEE medio)
- [ ] 10. Applicare policy Core (BelongsToCompany scope) a tutte le Resources
- [ ] 11. Scrivere test Filament: render Resources, azioni principali, widget

**Requisiti coperti**: R14

---

### T16 — Test suite e property-based testing

- [ ] 1. Creare factory per tutti i modelli MES
- [ ] 2. Scrivere property-based test per le invarianti: snapshot immutabile, tracciabilità bidirezionale, OEE ∈ [0,1], unicità numero ordine, coerenza stati, CapacityLoad ≥ 0, bilanciamento consumi
- [ ] 3. Scrivere test di integrazione end-to-end: ciclo completo produzione
- [ ] 4. Verificare 100% type coverage con `composer test:type-coverage`
- [ ] 5. Eseguire `vendor/bin/pint --dirty` e `composer test:types`

**Requisiti coperti**: tutti

---

### T17 — Documentazione modulo

- [ ] 1. Creare `Modules/MES/docs/GLOSSARY.md` — glossario tecnico completo dei termini MES (WorkCenter, BOM, Routing, ProductionOrder, LotNumber, ecc.) rivolto agli sviluppatori
- [ ] 2. Creare `Modules/MES/docs/MES_GUIDA_SEMPLICE.md` — guida all'utilizzo del modulo per l'utente finale (responsabile produzione, operatore, pianificatore): flussi principali, come creare un ordine di produzione, come registrare avanzamento, come leggere OEE. Senza tecnicismi.
- [ ] 3. Creare `Modules/MES/docs/rag/GLOSSARY.md` — versione RAG-ottimizzata del glossario, con definizioni brevi e precise adatte alla ricerca semantica di un assistente AI
- [ ] 4. Creare `Modules/MES/docs/rag/MODULE.md` — descrizione sintetica del modulo MES per RAG: scopo, entità principali, flussi chiave, integrazione con ERP e ERPBridge

**Nota**: La struttura replica il pattern esistente in `Modules/ERP/docs/` e `Modules/Core/docs/`.

---

## Notes

- Le migration del MES che aggiungono colonne a tabelle ERP (es. `tracing_type` su `items`) devono essere eseguite dopo le migration ERP. Garantire l'ordine tramite timestamp del filename.
- Il binding `StockMovementRecorder` viene registrato nel `MESServiceProvider` del MES (non nell'ERP). L'ERP non ha nessun riferimento al MES — la dipendenza è unidirezionale: MES → ERP.
- Tutti i modelli MES usano il trait `BelongsToCompany` e il global scope `BelongsToCompanyScope` dell'ERP — importare da `Modules\ERP\Concerns\BelongsToCompany`.
- Il prefisso `mes_` su tutte le tabelle evita collisioni con tabelle ERP o Core.
- I job `BackflushMaterialsJob` e `CreateProductionOrderFromSalesOrderJob` devono implementare `ShouldQueue` e usare la queue configurata nel modulo.
- **Strategia di integrazione ERP**: Il MES assume sempre che le tabelle ERP di Laraplate (`items`, `warehouses`, `companies`, `sales_orders`) siano presenti e popolate. Quando si usa un ERP esterno (SAP, Odoo, ecc.), si crea un modulo **ERPBridge** separato — non parte del MES — che: (1) sincronizza i dati dall'ERP esterno verso le tabelle ERP di Laraplate in modo trasparente, e (2) implementa il contratto `StockMovementRecorder` per rimandare i movimenti di magazzino al sistema esterno. Il MES non cambia in nessun modo: vede sempre e solo le tabelle ERP di Laraplate.
