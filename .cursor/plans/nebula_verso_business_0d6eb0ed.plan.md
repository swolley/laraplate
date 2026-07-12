---
name: Laraplate ERP modulo
overview: "P0 e P1 completati (Place+Location; Taxonomy+Category). **MVP v1 (pre-contabilità)**: anagrafica + listino + preventivo/righe + progetto + task + time entry. **Slice Filament** completati (contabile + commerciale). **M3.1 completato** (CRM win). **M3.2 completato** (SO + lock-chain + evasion + amendment). **M3.3 completato** (magazzino + costing). **M3.4 completato** (DDT + COGS/journal + storno). **M3.5 completato (2026-05-07):** posting fattura + numerazione `DocumentNumberAllocator` + pivot Invoice↔DDT + `InvoiceCompactionService`. **M3.6 avanzato:** ciclo acquisti, parties, 3-way match e posting fattura acquisto risultano implementati; resta da mantenere solo cleanup/regressioni se emergono gap. **M4 parziale:** permessi domain e reporting contabile presenti; pipeline/stock reporting restano backlog. **M5 completato:** scadenzario/pagamenti, note credito/debito, registri IVA/liquidazione, bilancio/conto economico. **M6.1 completato:** bank reconciliation v1 CSV/CAMT.053/MT940 minimale + match manuale/suggerimenti + match con differenza e journal contabile. **M6.2 completato:** resi cliente/fornitore con DDT fisico, tracking quantità, NC/ND manuali. **M6.3 completato:** e-invoice stub/submission workflow; FatturaPA completo backlog opzionale. **M7.1 completato:** listini avanzati e resolver prezzi."
todos:
  - id: p0-core-place-location-refactor
    content: "P0 — Place + Location: Core `places` + Place; servizi/rotte geocoding in Core; Cms `locations.place_id` + migration; trait trasparente Location↔Place; test; poi ERP `sites.place_id` / ICS"
    status: completed
  - id: p1-core-taxonomy-category-refactor
    content: "P1 — Taxonomy + Category (dopo P0): tabella `taxonomies` + `abstract class Taxonomy` con `$table = 'taxonomies'`; refactor `Category extends Taxonomy` + migrazione dati Cms; scope/EntityType per contesto; ERP `taxonomy_id` su Task/TimeEntry/listino. (Correzioni PSR-4/tabella/regole `exists` già allineate dove applicabile.)"
    status: completed
  - id: business-anagrafica-v1
    content: "Anagrafica v1: `customers` (+`is_active`), `sites` (`place_id`→`places`); `contacts` **senza** `customer_id` (legame M:N con `customers` via pivot **`contactables`**); modelli Customer/Contact/Site aggiornati."
    status: completed
  - id: mvp-precontabilita-v1
    content: "Meta-MVP pre-contabilità: listino (`price_lists`+valuta, `price_list_items`+`taxonomy_id`+`unit_price` 15,4) + preventivo (`quotations` con `customer_id`+`currency` obbl., righe tabella `quotations_items` + `unit_price`) + `projects` (`customer_id` obbl., **no** `lead_user_id`) + `tasks` (`taxonomy_id` obbl.) + `time_entries` (`started_at`/`ended_at`, più righe per utente consentite; sovrapposizione **solo validazione app**). Modelli MVP + `getRules()` create/update su entità principali; **restano** seed dev tassonomie operativo + validazione sovrapposizione `TimeEntry` (todo `time-domain`) + test/migrate verificati su DB pulito. Escluso: cassa/movimenti/bilanci/Filament pieno (vedi todo dedicati)."
    status: completed
  - id: glossary-map
    content: "Glossario sviluppatori in `Modules/ERP/docs/GLOSSARY.md` (EN): tenant, taxonomies+EntityType, TaxCode/TaxLineCalculator, journal, documenti, MovementType, invoice stub."
    status: completed
  - id: fix-task-activity-migrations
    content: "Schema ERP: aggiunte migration `191728` `price_lists`, `191729` `price_list_items` (prima delle righe preventivo); `191747` `time_entries`; `tasks` usa `taxonomy_id`→`taxonomies`; `quotations` (`customer_id`+`currency`), `quotations_items` (+`unit_price` 15,4), `projects` (`customer_id` obbl.); pivot `contactables` (file `191727_create_contactables_table`); `QuotationItem::$table = 'quotations_items'`. Verifica `migrate` su DB **pulito** ancora da chiudere in CI."
    status: completed
  - id: business-models-rules-casts-relations
    content: "Eloquent MVP: Quotation/Project/Task/TimeEntry/PriceList/PriceListItem/QuotationItem su `Core\\Overrides\\Model` con `fillable`+relazioni+casts principali; Quotation `HasLocks`+`HasValidity`; `getRules()` (create/update) su Project, Task, TimeEntry, PriceList, PriceListItem, QuotationItem (oltre Customer/Site/Quotation già presenti). `Movement` su `Core\\Overrides\\Model` stub (nessun attributo mass-assignable finché la tabella resta vuota). Opzionale backlog: accessor valuta listino→riga, rifiniture relazioni `taxonomy()` vs modello Taxonomy Core."
    status: completed
  - id: enrich-projects-movements
    content: "**ASSORBITO da `accounting-refactor-cash-tricount`** (vedi Roadmap ERP). Resta come rimando storico: nessuna implementazione separata. Per il MVP non-contabile: solo eventuali colonne/lock su progetto↔preventivo già coperti da `quote-revisions-core`; non bloccare MVP su trigger DB."
    status: pending
  - id: time-domain
    content: "Dominio tempo: validazione sovrapposizione `TimeEntry` **applicativa** via regola `TimeEntryOverlap` + `TimeEntryObserver` su update; regole `getRules()` su `Task`/`TimeEntry` (intervalli, `taxonomy_id`, FK opzionali) a livello modello; eventuali scope/query aggregati per `taxonomy_id` restano backlog opzionale. Nessun enum ActivityType."
    status: completed
  - id: business-enums-and-taxonomy-trees
    content: "Chiuso: `EntityType` senza MOVEMENTS, con OPPORTUNITY_STAGES + modello `OpportunityStage`, entity+preset in `ERPDatabaseSeeder`, dev seed `DevERPOpportunityStagesTaxonomySeeder`, enum `MovementType` (income/expense), PHPDoc + GLOSSARY."
    status: completed
  - id: settlements-quotes-lines
    content: Quote lines; movimenti soci/clienti; pool/cassa Tricount + split righe + (opz.) suggerimento settle-up
    status: pending
  - id: etl-legacy-import
    content: "ETL opzionale da gestionale Symfony legacy (path in nota fine piano). Mapping in chiave ERP: `Movement` legacy -> `JournalEntry` via `JournalPostingService`; `Quotation`/`Client`/`Contact`/`Work` -> entita' MVP attuali; `WorkSession` -> `time_entries`; eventuali movimenti magazzino legacy -> `stock_movements` via `StockMovementService`. ETL gira **dopo M2** (contabilita' base) e **dopo M3.3** se include flussi magazzino. Test campione su totali noti."
    status: pending
  - id: payment-requests-stub
    content: Schema/contratti PaymentRequest + provider nullable (PayPal/Satispay) collegabili a movimenti e richieste cliente/socio
    status: pending
  - id: gantt-planning-entity
    content: "Opzionale tardivo: entità pianificazione teorica progetto (Gantt), distinta da Task calendarizzato; solo se serve UI roadmap — non bloccante per MVP timbrature"
    status: pending
  - id: quote-revisions-core
    content: "Post-MVP o incremento dopo MVP: duplica+lineage; HasLocks+lock su bind progetto; HasVersions opz.; trigger opz. Su MVP minimo possono bastare colonne/versione già in migration se sufficienti al flusso interno."
    status: pending
  - id: calendar-ics-export
    content: Generazione ICS/promemoria mobile da Task (DTSTART/END) + LOCATION da Place/Site + partecipanti da Contact/User; eventuale persistenza UID export in seguito
    status: pending
  - id: business-filament-erp-core-slice
    content: "Chiuso (slice): risorse Filament in `Modules/ERP/app/Filament/Resources/` — `CompanyResource`, `TaxCodeResource`, `AccountResource`, `JournalEntryResource` (index/create/edit solo bozza `posted_at` null; pagina `view` + infolist righe; `canEdit`/`canDelete` bloccati se postata), `FiscalYearResource`, `FiscalPeriodResource`, `DocumentSequenceResource`; gruppo nav `ERP`; test `Modules/ERP/tests/Feature/Filament/ERPFilamentResourcesTest.php`. Escluso: posting da UI, magazzino, fatture complete (restano sotto `erp-filament-resources`). **CRM commerciale** (Lead/Opportunity/SO) è negli slice successivi, non in questa risorsa core. **Nota:** anagrafica/preventivo/progetto in Filament sono coperti da `erp-filament-mvp-commercial-slice`."
    status: completed
  - id: erp-filament-mvp-commercial-slice
    content: "Chiuso: Filament gruppo `ERP` — `CustomerResource`, `ContactResource` (sync M:N `contactables` via `customer_ids` in create/edit), `QuotationResource` (repeater `quotations_items`; `opportunity_id` filtrato per `customer_id` con campo `live()`), `ProjectResource` (validità + preventivo opzionale); slugs `business/customers`, `business/contacts`, `business/quotations`, `business/projects`; test `ERPFilamentCommercialResourcesTest.php`. **Agg. 2026-04:** validazione modello `Quotation` su `opportunity_id` (stesso customer/company) + test `QuotationOpportunityAlignmentTest.php`."
    status: completed
  - id: business-filament-final
    content: "Ampliamento UI oltre slice contabile + commerciale MVP: **Leads/Opportunities/SalesOrder** completati; **scaffold Filament** per Items/Warehouses/StockLevels/DeliveryNotes/Invoices/PurchaseOrders/GoodsReceipts completato (2026-04-30); righe DDT/GR/PO/Invoice con repeater in create/edit; tabella PO con aggregati righe. **Rimanente:** UX posting/unposting da azioni Filament dedicate, parties (M3.6), BI dashboard (`erp-reporting-stub`), API mobile opzionale."
    status: pending
  - id: inventory-magazzino-nebula
    content: "**ASSORBITO da `inventory-erp-base`** (M3.3, vedi Roadmap ERP). Non e' piu' un verticale rinviato: il magazzino e' integrato nel piano ERP come prerequisito di DDT vendita (M3.4) e Goods Receipt acquisto (M3.6). Resta come rimando storico per eventuali scelte ETL legacy specifiche."
    status: pending
  - id: erp-vision-roadmap
    content: "Meta-todo Roadmap ERP completo (post-MVP): Laraplate ERP italian-first pluggable, e-invoice solo come interfaccia, multi-company + multi-currency predisposti, ciclo passivo completo, magazzino integrato, accounting-first phasing. Vedi sezione 'Roadmap ERP completo (post-MVP)' nel corpo del piano."
    status: pending
  - id: multi-tenancy-foundations
    content: "M0-ERP — Tabella `companies` (id, name, vat_number, fiscal_code, `functional_currency` default 'EUR', default_locale, ...). Trait `BelongsToCompany` + `BelongsToCompanyScope` (global scope automatico) su tutte le entita' transazionali (quotations, sales_orders, projects, invoices, journal_entries, document_sequences, items, warehouses, stock_movements, ...). 1 Company seed `default` con `functional_currency='EUR'`. **Schema dual-currency da subito**: helper `ERPMigrateUtils::moneyColumns($table, 'amount')` che genera in un colpo solo `amount_doc` (decimal 15,4) + `currency_doc` (char 3) + `amount_local` (decimal 15,4) + `fx_rate` (decimal 18,8 default 1.0); `currency_local` derivata dalla company. Servizio `CurrencyConverter` come facade no-op in M0 (`amount_local = amount_doc * fx_rate`, fx_rate=1.0). FX live e tabella tassi rinviati."
    status: completed
  - id: enforce-versioning-on-accounting-models
    content: "M0-ERP — Dichiarare `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;` (e dove serve `protected bool $softDeletesEnabled = true;`) sulla classe di ogni modello contabile via via creato (Account, JournalEntry, JournalEntryLine, Invoice, InvoiceLine, FiscalYear, FiscalPeriod, DocumentSequence; valutare anche stock_movements/stock_cost_layers per audit). Niente lavoro su seeder/observer/Core. Il branch `property_exists` in [HasVersions::getVersionStrategy()](file:///srv/http/laraplate/Modules/Core/app/Helpers/HasVersions.php) garantisce che la property abbia priorita' assoluta sul record `settings.version_strategy_{table}`. Test: per ogni modello contabile, `assertEquals(VersionStrategy::DIFF, $model->getVersionStrategy())` e tentativo di disattivazione via Setting che resta inefficace. Possibile follow-up Filament (non bloccante): nascondere/disabilitare nel `SettingResource` i record di versioning relativi a modelli che hanno la property dichiarata."
    status: completed
  - id: accounting-coa
    content: "M1 — Chart of Accounts. Tabella `accounts` (id, `code` immutabile, `name`, `kind` enum {asset, liability, equity, revenue, expense}, `parent_id` self-FK, `meta` JSON con `civilistico_code` ed eventuali codifiche italiane, `company_id` obbl., `is_active`). Interfaccia `ChartOfAccountsProvider` con default italiano `ItalianCoaProvider` pluggable per altre giurisdizioni. Seed PDC italiano dev-only. Test: integrita' parent/kind, vincoli company scope."
    status: completed
  - id: accounting-journal
    content: "M1 — Partita doppia (chiuso in modulo): `JournalPostingService::post/reverse`, immutabilità post-`posted_at` su header/righe, `reverses_journal_entry_id`+`reversal_reason`, doppio controllo saldo `amount_local` (validazione + somma persistita). CHECK SQL cross-row non portabile — backlog se si fissa un solo DB di produzione."
    status: completed
  - id: accounting-fiscal-periods
    content: "M1 — Tabelle `fiscal_years` (id, company_id, `year`, `start_date`, `end_date`, `status` ∈ open/closing/closed) + `fiscal_periods` (id, fiscal_year_id, `code` es. M1..M12, `start_date`, `end_date`, `status`, `closed_at`, `closed_by`). Lock progressivo: chiusura periodo blocca posting su entry con `posted_at` nel range. Refattorizzare lo stub `balances` come snapshot legato a `fiscal_periods`. Servizio `FiscalPeriodCloser` con re-open tracciato. Test: posting impossibile su periodo chiuso, reopen registra audit."
    status: completed
  - id: document-sequences
    content: "M1 — Numeratori (chiuso in modulo): `document_sequences` con `format_pattern`+`suffix`, lock pessimistico, `DocumentType::defaultGapAllowed()` (quotation=true, altri false), `DocumentNumberFormatter`. Test unicità su molte allocazioni; stress multi-process 50 worker rimandato a `accounting-test-plan`."
    status: completed
  - id: accounting-vat-withholdings
    content: "M2 base chiusa: `tax_codes`, `TaxKind`, `TaxCode` (immutabilità ORM su chiave fiscale), `TaxLineCalculator`+`TaxCodeSupersessionService` (update DB raw per versioning), `tax_code_id` opzionale su `journal_entry_lines`, tabelle `invoices`/`invoice_lines` stub con snapshot, `ItalianTaxCodesSeeder`. TaxLineCalculator completo in M3.5 con posting fattura."
    status: completed
  - id: accounting-refactor-cash-tricount
    content: "M2 — Refactor di [Movement](file:///srv/http/laraplate/Modules/ERP/app/Models/Movement.php) / `PartnerPool` / `PoolTransaction` come **specializzazioni / adapter contabili** che generano `JournalEntry` via `JournalPostingService`. Eliminare la logica saldo parallela: il saldo cassa torna a essere **derivato** dalle entry contabili (vista o servizio dedicato). Mantenere l'API/UX Tricount lato Filament/Livewire ma sotto-cofano scrive solo in journal. Migrazione dati: per ogni Movement esistente, generare entry equivalente; flag su Movement `posted_journal_entry_id`."
    status: pending
  - id: crm-leads-opportunities
    content: M3.1 — **Completato (repo 2026-04-30):** `leads` + `opportunities` (MVP `customer_id`; `party_id` rimandato a M3.6); `quotations.opportunity_id` + validazione allineamento; Filament `LeadResource`/`OpportunityResource`; `OpportunityLifecycleService` + `QuotationObserver` → opportunità `won` + `won_at` quando `Quotation.status=accepted`; helper test `OpportunityStageTaxonomy`; test `CrmLeadsAndOpportunitiesSchemaTest`, `QuotationOpportunityAlignmentTest`. **Backlog opzionale:** lineage esteso oltre FK, automazioni aggiuntive su altri stati preventivo.
    status: completed
  - id: sales-order
    content: M3.2 — **Completato (repo 2026-05-05):** `sales_orders`/`sales_order_lines`, validazioni dominio, Filament, `DocumentNumberAllocator` su create, titolo record; lock `Quotation` a SO `confirmed`; lock header SO su stati confermati/evasi; blocco `qty_ordered` dopo inizio evasione; `SalesOrderEvasionService` (delivery/invoice qty + stato riga/header) + rollback delivery/invoice; **`SalesOrderAmendmentService` dedicato** (nuovo SO draft con `amends_sales_order_id`, righe su qty residue); wiring evasion da `Invoice` posted/unposted tramite `InvoicePostingService` + `InvoiceObserver`; test estesi.
    status: completed
  - id: inventory-erp-base
    content: M3.3 — **Completato operativo (2026-05-05):** `stock_movements`, `stock_cost_layers`, `StockMovementService`, costing base, test servizi magazzino; integrazione journal/COGS tramite `DeliveryNoteCogsJournalService` (posting + storno); golden master piano esteso rimandato a `accounting-test-plan`.
    status: completed
  - id: sales-delivery
    content: M3.4 — **Completato operativo (2026-05-05):** `delivery_note_lines`, collegamento SO, `DeliveryNoteInventoryService` + observer/posting, **COGS/journal** su posting magazzino DDT (`DeliveryNoteCogsJournalService`, `cogs_journal_entry_id`), **storno completo su annullo DDT** (reverse journal + ripristino stock + rollback `qty_delivered` SO via `unregisterDelivery`), test feature dedicati. Rifiniture UI opzionali (azioni post/unpost da pagina Filament) non bloccanti.
    status: completed
  - id: sales-invoice-document
    content: M3.5 — **Completato (2026-05-07):** `InvoicePostingService` post/unpost con `DocumentNumberAllocator::next()` al posting (`DocumentType::SalesInvoice`/`PurchaseInvoice`, fiscal year scope, gap_allowed=false); colonna `invoices.reference` assegnata al posting e azzerata su unpost; snapshot fiscale righe; wiring qty invoiced SO; pivot `invoice_line_delivery_note_line` per collegamento flessibile Invoice↔DDT con `InvoiceCompactionService` (compact/expand); Filament form con reference disabled + tabella con reference searchable; test `InvoicePostingServiceTest` con asserzioni su reference. **Backlog opzionale:** azioni UI dedicate post/unpost in pagina Filament (non bloccanti).
    status: completed
  - id: einvoice-interface
    content: "Chiuso in modulo (solo contratto): `Modules/ERP/app/Contracts/EInvoiceProvider.php` — `prepare()`, `submit()`, `remoteStatus()` + DTO `EInvoicePayload`, `EInvoiceSubmissionResult`, enum `EInvoiceRemoteStatus`; cast `EInvoiceSubmissionStatus`; modello `EInvoiceSubmission` + relazione `Invoice::eInvoiceSubmissions()`; migration `2026_04_29_150000_create_e_invoice_submissions_table.php`; test `EInvoiceSubmissionSchemaTest.php`. Nessun binding SDI/PEPPOL in `ERPServiceProvider`."
    status: completed
  - id: purchasing-cycle
    content: M3.6 — **Avanzato (2026-05-07):** righe PO/GR, posting GR, stato PO; tabella Filament PO; `DocumentType::PurchaseOrder` + allocator; rename `customers`->`parties` completato (flag `is_customer`+`is_supplier`); `ThreeWayMatchService` tolerance-based implementato (enum `MatchStatus`, colonne `match_status`/`match_discrepancy`/`purchase_order_line_id`/`goods_receipt_line_id` su `invoice_lines`, test); colonne 3-way su migration `invoice_lines` in-place. **Rimanente M3.6:** posting fattura acquisto; wiring `ThreeWayMatchService` in `InvoicePostingService` per direction=purchase.
    status: pending
  - id: erp-policies-permissions
    content: "M4 — Policies + permessi su: chiusura/riapertura periodo, posting/unposting journal, fatturazione (genera/annulla), sblocco quotations/SO, switch company corrente, modifica `tax_codes` (riservata ad amministratori), gestione `document_sequences`. Allineamento al pattern Core (gates + policies). Test feature: utente standard non puo' ri-aprire periodo chiuso, non puo' modificare tax_code, ecc."
    status: pending
  - id: erp-filament-resources
    content: "M4 — Filament **parziale**: slice contabile + MVP commerciale + CRM + risorse M3 con righe DDT/GR/PO (2026-05-04) + colonne riepilogo PO + test `ERPFilamentCommercialResourcesTest` / `FoundationM3SchemaTablesTest` / dominio PO. **Da fare:** UI posting/chiusure contabili complete, parties (M3.6), permessi granulari (`erp-policies-permissions`), BI (`erp-reporting-stub`)."
    status: pending
  - id: erp-reporting-stub
    content: "M4 — Servizi report come query/jobs (no BI completa): `BalanceSheetService`, `IncomeStatementService`, `VatLedgerService` (registro IVA vendite/acquisti), `SalesPipelineService` (funnel opportunity -> won), `StockValuationService` (valore magazzino al costing method scelto). Output strutturato (JSON/array), pagine Filament per consumarli, export CSV finanziario implementato su bilancio di verifica, stato patrimoniale e conto economico. PDF e BI completa restano fuori scope."
    status: pending
  - id: accounting-test-plan
    content: "Trasversale M1-M3 — Suite di golden master + concorrenza + invarianti: (a) partita doppia bilanciata su `amount_local` per ogni Invoice/cassa/refactor Tricount; (b) IVA + ritenute con snapshot fiscale immutabile; (c) numerazione progressiva sotto contesa: 50 process paralleli su `invoice_sale` -> 50 numeri sequenziali univoci, 0 buchi; stesso test su tipo `gap_allowed=true` -> consentire buchi su rollback; (d) scope `company_id`: query da company A non vede dati company B; (e) versioning forzato: per ogni modello contabile `getVersionStrategy()===DIFF` anche dopo aver alterato il record `settings.version_strategy_{table}`; (f) lock-chain SO: confirm SO -> Q lockata; primo DDT su una riga -> riga locked su qty_ordered; chiusura SO impossibile finche' qty_invoiced<qty_delivered; amendment crea nuovo SO con `amends_sales_order_id` valorizzato; (g) magazzino FIFO+media: scarico su item `costing_method=fifo` consuma layer in ordine cronologico; cambio costing_method a item esistente vietato; COGS calcolato dal `StockMovementService` quadra con il journal del DDT; (h) `CurrencyConverter` no-op in M0: ogni `amount_local = amount_doc` con `fx_rate=1.0` su EUR/EUR. Test feature + unit; baseline per regression sui future PR."
    status: pending
  - id: payment-schedule-receivables
    content: "M5.1 — **Completato (2026-05-07).** Scadenzario e Incassi/Pagamenti. Tabella `payment_terms` (company_id, name, rate_lines JSON con [{days, percent, payment_method}]). Tabella `payment_schedule_lines` (invoice_id, due_date, amount_doc, amount_local, currency_doc, fx_rate, status enum [open, partial, paid, cancelled], paid_amount_doc, paid_at). Generazione automatica scadenze al posting fattura (da `InvoicePostingService`) in base al `payment_term_id` su Invoice. Tabella `payments` (party_id, company_id, direction enum [inbound, outbound], payment_date, amount_doc/local, reference, bank_account_id nullable, notes) + pivot `payment_payment_schedule_line` (payment_id, payment_schedule_line_id, allocated_amount). Servizio `PaymentAllocationService::allocate(Payment, array $allocations)` che aggiorna status scadenze. Report **aging AR/AP**: query per fasce scaduto 30/60/90/120+ giorni raggruppato per party. Partitario aperto: elenco scadenze non saldate filtrabili per party/periodo. Filament: form PaymentTerm, tabella scadenze su pagina Invoice view, form registrazione pagamento con allocazione multipla, pagina aging report. Test: generazione scadenze, allocazione, aging corretto."
    status: completed
  - id: supplier-payment-runs-sepa-export
    content: "M5.1 follow-up / Spec 2B-13 — **Completato (ERP 01678cc).** Distinte pagamenti fornitori e export tracciato bancario. Lo scadenzario registra debiti/crediti e allocazioni; l'esecuzione bancaria v1 usa `PartyBankAccount` con IBAN/BIC/default, `PaymentRun` approvabile, `PaymentRunLine` con snapshot beneficiario/importo/scadenza, export SEPA Credit Transfer `pain.001` per bonifici fornitori, checksum/metadati export e resource Filament minima. Non include invio API banca, CBI, Ri.Ba, SDD o formati proprietari nel primo taglio."
    status: completed
  - id: credit-debit-notes
    content: "M5.2 — **Completato (2026-05-07).** Note di Credito e Note di Debito. Estensione modello `Invoice` con `invoice_type` enum [invoice, credit_note, debit_note] (o campo `is_credit_note` + `credited_invoice_id` FK nullable). La nota di credito inverte il segno delle righe e genera journal entry speculare (dare↔avere invertiti). `DocumentType::SalesCreditNote` / `PurchaseCreditNote` per numerazione separata (sezionale NC). Collegamento NC → fattura originale via `credited_invoice_id`. Aggiornamento `PaymentScheduleService`: la NC riduce/azzera scadenze aperte della fattura collegata. Flusso completo: reso merce → DDT reso (M6.2) → NC automatica o manuale; abbuono/errore → NC manuale senza DDT. Validazioni: NC non può eccedere importo residuo fattura originale. Filament: azione 'Crea nota di credito' dalla pagina fattura, form con righe pre-compilate da originale (modificabili). Test: posting NC, storno scadenze, journal invertito, numerazione separata."
    status: completed
  - id: vat-registers-settlement
    content: "M5.3 — **Completato (2026-05-07).** Registri IVA e Liquidazione. Tabella `vat_register_entries` (invoice_id, register_type enum [sales, purchases], protocol_number progressivo per registro/anno, registration_date, tax_code_id, taxable_amount, tax_amount, company_id, fiscal_year_id). Servizio `VatRegisterService`: alla conferma/posting di una fattura (vendita o acquisto), inserisce riga nel registro corrispondente con protocol_number progressivo. `VatSettlementService::compute(company_id, period)`: calcola IVA a debito (registro vendite) − IVA a credito (registro acquisti) − eventuale credito periodo precedente = IVA da versare (o credito). Tabella `vat_settlements` (company_id, fiscal_period_id, vat_sales, vat_purchases, previous_credit, settlement_amount, status enum [draft, confirmed], confirmed_at). Report: stampa registro IVA vendite/acquisti (ordinato per protocol_number), prospetto liquidazione periodica. Filament: pagine registro IVA vendite/acquisti con filtro periodo, pagina liquidazione con calcolo automatico e conferma. Seeder: aliquote IVA italiane standard (22%, 10%, 5%, 4%, esente, non imponibile, escluso). Test: protocollo progressivo, calcolo liquidazione, quadratura IVA registri vs journal."
    status: completed
  - id: financial-statements
    content: "M5.4 — **Completato (2026-05-07).** Bilancio e Conto Economico. Servizio `BalanceSheetService::generate(company_id, date)`: genera bilancio patrimoniale da saldi GL (asset/liability/equity) alla data. Servizio `IncomeStatementService::generate(company_id, fiscal_period_range)`: genera conto economico (revenue − expense) per intervallo periodi. Servizio `TrialBalanceService::generate(company_id, date)`: bilancio di verifica (saldi dare/avere per ogni conto). Output strutturato (array/DTO) consumabile da Filament e export. Integrazione con `FiscalPeriodCloser`: chiusura periodo genera snapshot saldi. Report: pagine Filament con filtro date/periodi, export CSV. Aggiorna `erp-reporting-stub` da M4 a M5.4. Test: quadratura dare=avere su trial balance, coerenza bilancio/conto economico, effetto chiusura periodo."
    status: completed
  - id: bank-reconciliation
    content: "M6.1 — **Riconciliazione Bancaria.** V1 implementata come import CSV, CAMT.053 XML e subset MT940 minimale (`:61:`/`:86:`), conti/estratti/righe banca, match manuale, suggerimenti automatici per importo/data/reference e UI Filament minimale. Il sotto-flusso professionale delle differenze è presente: commissioni, arrotondamenti e scarti possono generare journal entry tramite conto banca con ruolo `bank_cash`, conto expense selezionato in UI e FK audit `difference_journal_entry_id` sulla riga estratto. CBI/Ri.Ba/SDD e API bancarie dirette restano fuori scope. Test: import CSV/CAMT/MT940, match manuale, suggerimenti compatibili, journal differenze e match con differenza."
    status: completed
  - id: returns-management
    content: "M6.2 — **Completato.** Flusso primario su documenti dedicati: `ReturnOrder` per reso cliente e `SupplierReturn` per reso a fornitore, con party, documento origine opzionale, status e righe proprie. Se il reso muove fisicamente merce, il servizio genera/collega un DDT: cliente inbound (`DeliveryNote.direction=inbound`), fornitore outbound (`direction=outbound`). Le bolle non contengono prezzi o costi; i costi restano in `StockMovement`/`StockCostLayer` e, per resi cliente senza movimento originario risolvibile, sulla riga reso come costo manuale esplicito. Completamento reso aggiorna qty rese su invoice/SO o PO/GR quando c'e' una riga sorgente. Le pagine reso espongono azioni manuali NC/ND v1 e collegano i documenti generati a `credit_note_invoice_id` / `debit_note_invoice_id`; automazione resta backlog dopo contratto esplicito per fattura origine e override righe. Test: approve/complete/cancel, DDT reso collegato, stock movement, tracking qty rese, NC/ND manuali."
    status: completed
  - id: einvoice-sdi-binding
    content: "M6.3 — **Completato.** Scope core: bind di `EInvoiceProvider`, provider stub deterministico, persistenza `EInvoiceSubmission`, azioni Filament minime per invio/refresh su fatture vendita postate e test del workflow. Non promette XML FatturaPA validabile, provider Aruba reale, polling/webhook SDI avanzato o conservazione sostitutiva. **Backlog opzionale finale FatturaPA:** XSD ufficiale, mapping completo party/company/trasmittente/cessionario, PEC/codice SDI, regime fiscale, `Natura`, provider reale e stati SDI avanzati."
    status: completed
  - id: advanced-pricelists
    content: "M7.1 — **Listino Prezzi Avanzato.** Estensione `price_list_items` con `valid_from`/`valid_to` (validità temporale). Tabella `party_price_rules` (party_id, item_id nullable, taxonomy_id nullable, discount_type enum [percentage, fixed, cascade], discount_value, priority, valid_from, valid_to) per condizioni di prezzo personalizzate per party. Sconti a cascata: `discount_value` come JSON array `[10, 5, 3]` → -10%, poi -5% sul risultato, poi -3%. Servizio `PriceResolverService::resolve(item_id, party_id, qty, date)`: applica in ordine: listino base → regole per party → sconti volume opzionali → prezzo finale. Integrazione: `QuotationItem`/`SalesOrderLine`/`InvoiceLine` richiamano `PriceResolverService` come suggerimento (prezzo modificabile dall'utente). Filament: gestione regole prezzo per party, preview prezzo calcolato nel form riga. Test: risoluzione con priorità, scadenza temporale, cascade, override manuale."
    status: completed
isProject: false
---

> **Repo / cartella (2026-04):** il modulo si chiama **ERP** nel monorepo: cartella **`Modules/ERP`**, package Composer **`swolley/laraplate-erp`**, namespace PHP **`Modules\ERP`**, chiave config **`erp`**, remote git **`laraplate-erp`**. I todo YAML con prefisso `business-*` / `business-filament-*` sono **id storici** Cursor; il contenuto si riferisce al modulo ERP.

> **ERP open work (2026-06-30):** Pending frontmatter todos and M4/M6/M7 follow-ups are consolidated in
> [`docs/superpowers/specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`](../../docs/superpowers/specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md).
> **Next implementation target:** Phase 2A (Filament domain actions + state-aware policies). Completed milestones below remain historical record.

### Sync piano ↔ repo (2026-04-30, sera)

Commit submodule ERP: **`0655deb`** (`feat(erp): complete CRM/SO baseline and add M3 foundation resources`). Bump monorepo: **`6fbe51d`**.

**Fatto:** M3.1 (win opportunità su preventivo `accepted`); M3.2 (lock quotation/header/riga, `SalesOrderEvasionService`, test); foundation tabelle M3.3–M3.6 header-level + Filament scaffold; test `FoundationM3SchemaTablesTest` + estensioni commercial/Filament.

**Prossimi passi chiari:** `StockMovementService` + tabelle movimenti/layer; righe DDT/GR; `SalesOrderAmendmentService`; collegare DDT/Invoice a evasion; M3.5 posting fattura; M3.6 `parties`; policies M4 (`erp-policies-permissions`).

### Sync piano ↔ repo (2026-05-04)

**Fatto in questa sessione:** tabella Filament `PurchaseOrder` con colonne aggregate righe (`lines_count`, somme `qty_ordered` / `qty_received`) via `getEloquentQuery()`; `DocumentType::PurchaseOrder` con `defaultGapAllowed()` allineato a SO; `CreatePurchaseOrder` assegna reference da `DocumentNumberAllocator` se vuota; migration MySQL ENUM `document_sequences` per `purchase_order`; test in `PurchaseOrderDomainTest` (numeratore + aggregati query).

**Prossimi passi:** COGS/journal da DDT; `SalesOrderAmendmentService`; M3.5 posting fattura; M3.6 `parties` e 3-way match; policies M4.

### Sync piano ↔ repo (2026-05-04, proseguimento)

**Fatto:** posting contabile **COGS** alla conferma magazzino DDT: conti PdC italiani `146` (magazzino merci, `meta.erp_role=inventory_merchandise`) e `590` (costo merci vendute, `erp_role=cost_of_goods_sold`); `DeliveryNoteCogsJournalService` aggrega costo dagli `StockMovement` outbound delle righe DDT e chiama `JournalPostingService::post` con `reference` = `DeliveryNote`; colonna `delivery_notes.cogs_journal_entry_id`; estensione `JournalPostingService::post(..., ?Model $reference)`; test `DeliveryNoteInventoryServiceTest` + schema `FoundationM3SchemaTablesTest`.

**Prossimi passi:** storno COGS su annullo DDT (se previsto); `SalesOrderAmendmentService`; posting fattura M3.5; `parties` / 3-way M3.6.

### Sync piano ↔ repo (2026-05-05)

**Fatto:** gestione annullo DDT postato: observer chiama `unpostInventory()` quando `posted_at` torna `null`; `DeliveryNoteInventoryService` ripristina stock con movimenti inbound di compensazione, richiama `SalesOrderEvasionService::unregisterDelivery()` e azzera `inventory_posted_at`; `DeliveryNoteCogsJournalService` crea lo storno del journal COGS via `JournalPostingService::reverse()` e azzera `cogs_journal_entry_id`; test feature dedicato.

**Prossimi passi (priorita' attuale):** `SalesOrderAmendmentService`; posting fattura M3.5; `parties` / 3-way M3.6; policies M4.

### Sync piano ↔ repo (2026-05-05, amendment SO)

**Fatto:** introdotto `SalesOrderAmendmentService` (servizio dedicato): ammette amendment solo da SO `confirmed`/`partially_evased`, crea nuovo SO `draft` con `amends_sales_order_id`, genera `reference` via `DocumentNumberAllocator`, clona solo righe con qty residua (`qty_ordered - max(qty_delivered, qty_invoiced)`), status riga `open`; aggiunto anche `unregisterDelivery()` in `SalesOrderEvasionService` e relativo utilizzo nel rollback DDT. Test estesi in `SalesOrderSchemaTest`.

**Prossimi passi:** wiring evasion da `Invoice` posted (M3.2); posting fattura M3.5; `parties` / 3-way M3.6; policies M4.

### Sync piano ↔ repo (2026-05-05, invoice posting)

**Fatto:** completato wiring evasione da invoice (`registerInvoice`/`unregisterInvoice` su `SalesOrderEvasionService`) e implementato posting fattura M3.5 con `InvoicePostingService` + `InvoiceObserver`: journal post/reverse (`invoices.journal_entry_id`), snapshot fiscale righe, link `invoice_lines.sales_order_line_id`, aggiornamento qty invoiced su SO, create/edit Filament con repeater righe.

**Prossimi passi:** numerazione invoice con `DocumentNumberAllocator` (stream dedicati, policy gap), eventuale vincolo invoice↔delivery_notes, `parties` / 3-way M3.6.

### Sync piano ↔ repo (2026-05-07, allineamento piano)

**Allineamento TODO frontmatter al codice effettivo in repo.** Nessuna modifica al codice sorgente.

**Todo aggiornati a `completed`:**
- `sales-order` (M3.2): `SalesOrderAmendmentService`, `SalesOrderEvasionService` (delivery + invoice), `InvoiceObserver` wiring, lock chain, test — tutto presente in repo.
- `inventory-erp-base` (M3.3): `StockMovementService`, `stock_movements`/`stock_cost_layers`, costing base, integrazione COGS/journal completata via `DeliveryNoteCogsJournalService`.
- `sales-delivery` (M3.4): `DeliveryNoteInventoryService`, COGS posting, storno completo su annullo DDT (reverse journal + ripristino stock + rollback `qty_delivered` SO), test feature.

**Todo confermati `pending` con content aggiornato:**
- `sales-invoice-document` (M3.5): posting/unposting implementato (`InvoicePostingService` + `InvoiceObserver`), snapshot fiscale, wiring SO; **manca** numerazione `DocumentNumberAllocator` per `SalesInvoice`/`PurchaseInvoice` in `CreateInvoice` Filament, vincolo opzionale DDT, azioni UI dedicate.
- `purchasing-cycle` (M3.6): righe PO/GR, posting GR, tabella Filament PO, `DocumentType::PurchaseOrder` + allocator; **mancano** `parties`/`party_id`, 3-way match, posting fattura acquisto.
- `business-filament-final`: scaffold Filament M3 completato; **mancano** UX posting/unposting da azioni Filament, parties, BI, API mobile.
- `erp-policies-permissions` (M4): non avviato — nessuna Policy, Gate o permesso granulare nel modulo ERP.
- `erp-filament-resources` (M4): parziale; attende policies e reporting.
- `erp-reporting-stub` (M4): non avviato.
- `accounting-test-plan`: non avviato.

**Confermati `pending` senza modifiche:** `enrich-projects-movements`, `settlements-quotes-lines`, `etl-legacy-import`, `payment-requests-stub`, `gantt-planning-entity`, `quote-revisions-core`, `calendar-ics-export`, `inventory-magazzino-nebula`, `erp-vision-roadmap`, `accounting-refactor-cash-tricount`.

**Prossimi passi (priorità):**
1. Numerazione invoice `DocumentNumberAllocator` (M3.5 — chiusura milestone).
2. `parties`/`party_id` + 3-way match + posting fattura acquisto (M3.6 — chiusura milestone).
3. Policies + permessi granulari (M4 `erp-policies-permissions`).
4. Filament posting/unposting UX + reporting stub (M4).
5. Test plan trasversale (`accounting-test-plan`).

### Decisioni consolidate (2026-05-07)

Le seguenti decisioni sono state discusse e confermate. Sovrascrivono eventuali indicazioni precedenti nel piano.

#### D1 — Rename `customers` -> `parties` con flag booleani

Confermato **opzione A** (anagrafica unificata). Dettagli implementativi:

- Rename in-place `customers` -> `parties` (siamo in fase di creazione, migration in-place consentita).
- **Due flag booleani** `is_customer` (default `true`) + `is_supplier` (default `false`) al posto del JSON `roles`. Motivazione: indicizzabili, queryabili senza JSON parsing, validazione banale (`CHECK (is_customer OR is_supplier)`). Il set di ruoli e chiuso e noto (customer/supplier/both).
- Modello PHP: `Customer` rinominato in `Party` con scope `scopeCustomers()` / `scopeSuppliers()`.
- FK rinominate: `customer_id` -> `party_id` su `quotations`, `projects`, `invoices`, `sales_orders`, `delivery_notes`, `purchase_orders`, `leads`, `opportunities`, `contactables`.
- Filament: `CustomerResource` -> `PartyResource` con filtro per ruolo.
- **Timing:** primo step, prima di chiudere M3.5 (cosi le FK invoice nascono gia come `party_id`).

#### D2 — Numerazione invoice al posting

La `reference` della fattura viene assegnata da `DocumentNumberAllocator::next()` **al posting** (quando `posted_at` viene valorizzato), non alla creazione del draft. Coerente con la prassi fiscale italiana. Implementazione: dentro `InvoicePostingService::post()`, con `DocumentType::SalesInvoice` o `DocumentType::PurchaseInvoice` in base a `Invoice::$direction`. Il draft non ha reference finche non e postato.

#### D3 — Vincolo Invoice <-> DeliveryNote via pivot

Adottato **pattern 3** (pivot flessibile):

- Tabella pivot `invoice_line_delivery_note_line`: `invoice_line_id` FK, `delivery_note_line_id` FK, `quantity` (quanta qty di quella riga DDT e coperta da questa riga fattura).
- Setting per company: `erp.invoice_generation_mode` = `'expanded'` | `'compact'`. Default: `'expanded'`.
  - **Expanded:** 1 `InvoiceLine` per ogni `DeliveryNoteLine`, pivot 1:1.
  - **Compact:** raggruppamento per `item_id` + `unit_price`, 1 `InvoiceLine` con qty sommata, pivot 1:N.
- Servizio `InvoiceCompactionService::compact(Invoice)`: prende una fattura espansa, raggruppa righe per item+prezzo, somma qty, aggiorna la pivot. Operazione reversibile (`expand()` ri-espande leggendo la pivot).

#### D4 — Riconciliazione 3-way PO/GR/Invoice (tolerance-based)

Adottato **approccio B** (tolerance-based, standard SAP/Oracle/NetSuite):

- Setting per company: `erp.three_way_match.price_tolerance_percent` (default **0%**) e `erp.three_way_match.qty_tolerance_percent` (default **0%**). Default conservativi (strict), l'utente allarga se serve.
- Servizio `ThreeWayMatchService::validate(InvoiceLine): MatchResult`:
  - Confronta qty e prezzo unitario con `PurchaseOrderLine` e `GoodsReceiptLine`.
  - Se scarto entro tolerance -> posting consentito, `match_status = 'tolerance'`, scarto tracciato.
  - Se scarto eccede tolerance -> `ValidationException`, serve override esplicito (`force_post` con permesso dedicato da `erp-policies-permissions`).
- Colonne su `invoice_lines` (fattura acquisto): `purchase_order_line_id` FK nullable, `goods_receipt_line_id` FK nullable, `match_status` enum (`matched`, `tolerance`, `forced`, `unmatched`), `match_discrepancy` JSON nullable.

#### D5 — Trigger DB per lock-chain (hardening pre-produzione)

Confermato: trigger DB **dopo** chiusura M3.5/M3.6, come safety net pre-produzione.

- Un unico file migration che installa trigger `BEFORE UPDATE` e `BEFORE DELETE` su tutte le tabelle con `HasLocks` (`quotations`, `quotation_items`, `sales_orders`, `sales_order_lines`).
- Logica trigger: `IF OLD.locked_at IS NOT NULL AND (NEW.locked_at IS NOT NULL OR NEW.locked_at = OLD.locked_at) THEN SIGNAL SQLSTATE '45000'`. Lo sblocco (`UPDATE SET locked_at = NULL`) resta possibile per l'admin.
- Observer e trigger convivono: l'observer **applica** il lock (logica di business), il trigger lo **difende** (safety net contro accessi raw/bug).

#### D5b — Domande residue Tricount/cassa (M2)

Confermate come **backlog non bloccante** per M3.5/M3.6. Saranno affrontate quando si riprende `accounting-refactor-cash-tricount`.

#### D6 — Validazione tipo party su modelli e Filament (2026-05-07)

Aggiunta validazione `saving()` su tutti i modelli sales-side (Quotation, SalesOrder, Lead, Opportunity, Project) che verifica `party->is_customer === true`. Su PurchaseOrder verifica `party->is_supplier === true`. Se il party non ha il flag corretto → `ValidationException`. I form Filament filtrano il dropdown party con `modifyQueryUsing` usando gli scope `->customers()` / `->suppliers()` di Party.

### Sync piano ↔ repo (2026-05-07, nuovi milestone)

**Fatto:** validazione tipo party (D6) su 6 modelli + 6 form Filament. Analisi comparativa con SAP/Odoo/ERPNext completata.

**Nuovi todo aggiunti al frontmatter** (ordinati per priorità / criticità):

| ID | Milestone | Descrizione | Priorità |
|----|-----------|-------------|----------|
| `payment-schedule-receivables` | M5.1 | Scadenzario, payment terms, incassi/pagamenti, aging AR/AP | CRITICO |
| `credit-debit-notes` | M5.2 | Note di credito/debito, storno scadenze, numerazione sezionale NC | CRITICO |
| `vat-registers-settlement` | M5.3 | Registri IVA vendite/acquisti, liquidazione periodica, protocollo progressivo | OBBLIGO LEGALE |
| `financial-statements` | M5.4 | Bilancio patrimoniale, conto economico, bilancio di verifica | IMPORTANTE |
| `bank-reconciliation` | M6.1 | Import estratti conto CSV/CAMT.053/MT940 minimale, riconciliazione bancaria v1, journal differenze e match con differenza | IMPORTANTE |
| `returns-management` | M6.2 | Resi cliente/fornitore con DDT fisico collegato; NC/ND manuali v1 | IMPORTANTE |
| `einvoice-sdi-binding` | M6.3 | Stub `EInvoiceProvider`, submission e azioni UI; FatturaPA completo opzionale finale | IMPORTANTE per go-live IT |
| `advanced-pricelists` | M7.1 | Listino con validità temporale, sconti per party, sconti a cascata | MEDIO |

**Ordine residuo di implementazione suggerito:**
1. M4 follow-up: reporting operativo residuo, policy/permessi mancanti e test dedicati.
2. Accounting golden-master/concurrency test plan.
3. Backlog opzionali: differenze bancarie con journal, FatturaPA completo, reporting operativo avanzato.

**Nota:** `erp-reporting-stub` (ex M4) è stato sostituito da `financial-statements` (M5.4) per i report contabili. `VatLedgerService` migra dentro `vat-registers-settlement` (M5.3). `SalesPipelineService` e `StockValuationService` restano in `erp-reporting-stub` come backlog opzionale.

### Sync piano ↔ repo (2026-05-07, M5 completato)

**Fatto — M5.1 Scadenzario & incassi/pagamenti:**
- Enum `PaymentScheduleStatus` (open/partial/paid/cancelled), `PaymentDirection` (inbound/outbound).
- Migration: `payment_terms`, `payments`, `payment_schedule_lines`, `payment_allocations`, FK `payment_term_id` su `invoices`.
- Modelli: `PaymentTerm`, `Payment`, `PaymentScheduleLine`, `PaymentAllocation`.
- Servizi: `PaymentScheduleGeneratorService` (integrato in `InvoicePostingService::post/unpost`), `PaymentAllocationService`, `AgingReportService` (aging 30/60/90/120+).
- Filament: `PaymentTermResource`, `PaymentResource` (con allocazioni e schedule lines).
- Test: `PaymentScheduleServiceTest`.

**Fatto — M5.2 Note di credito/debito:**
- Enum `InvoiceType` (invoice/credit_note/debit_note).
- `DocumentType` esteso: `SalesCreditNote`, `PurchaseCreditNote`, `SalesDebitNote`, `PurchaseDebitNote`.
- Migration: colonne `invoice_type` e `credited_invoice_id` su `invoices`, estensione ENUM `document_sequences`.
- `CreditNoteService`: crea CN da fattura postata, copia righe, valida totale ≤ residuo creditabile.
- `InvoicePostingService` aggiornato: negazione importi per journal invertito, selezione `DocumentType` per NC/ND, check `abs()` su tax_total.
- Filament: azione "Create Credit Note" su `EditInvoice`, campi `invoice_type`/`credited_invoice_id` condizionali.
- Test: `CreditNoteServiceTest`.

**Fatto — M5.3 Registri IVA e liquidazione:**
- Enum `VatRegisterType` (sales/purchases), `VatSettlementStatus` (draft/confirmed).
- Migration: `vat_register_entries`, `vat_settlements`.
- Modelli: `VatRegisterEntry` (protocol_number progressivo per company/type/year), `VatSettlement`.
- `VatRegisterService`: registrazione automatica al posting fattura (integrato in `InvoicePostingService`), un entry per codice IVA, deregistrazione su unpost.
- `VatSettlementService`: calcolo IVA debito − IVA credito − credito precedente, conferma con carry-forward.
- Filament: `VatRegisterResource` (read-only), `VatSettlementResource` (read-only).
- Test: `VatRegisterServiceTest`.

**Fatto — M5.4 Bilancio e report finanziari:**
- Servizi: `TrialBalanceService`, `BalanceSheetService`, `IncomeStatementService` (derivano tutto da journal postati, nessuna tabella snapshot).
- Filament: pagine custom `TrialBalancePage`, `BalanceSheetPage`, `IncomeStatementPage` con viste Blade tabulari.
- Test: `FinancialStatementsTest`.

**Fatto — Documentazione:**
- Aggiornati `README.md`, `docs/rag/MODULE.md`, `docs/GLOSSARY.md` con tutte le funzionalità M0–M5.

**Prossimi step (2026-06-30):** M0–M7 v1 e golden master sono chiusi. Tutto il lavoro ERP aperto è in
**Spec 2 master backlog** (`docs/superpowers/specs/2026-06-30-erp-hardening-spec2-filament-domain-actions-design.md`).
Priorità: **Phase 2A** (azioni Filament + policy state-aware + permessi M4), poi 2B (Party UI, journal differenze banca, NC/ND auto), 2C (FatturaPA), Phase 3+ (API azioni dominio, architettura).

# Modulo ERP in Laraplate (Nebula)

Scaffolding gestionale agnostico: tutti i path nel piano puntano a **`Modules/ERP`** / **`Modules\ERP`** salvo diversa indicazione.

## MVP v1 — perimetro **pre-contabilità** (primo utilizzabile)

Obiettivo: un **ciclo chiuso dati** commessa / commerciale / tempo **senza** cassa, movimenti IN-OUT, bilanci, settlement o pool soci. Allineato al todo **`mvp-precontabilita-v1`** nel frontmatter.

**Dentro al MVP**

- Anagrafica: `Customer`, `Contact` (M:N con `Customer` via **`contactables`**; niente `customer_id` su `contacts`), `Site` (già in `business-anagrafica-v1`).
- **Listino**: `price_lists` (include **`currency`** ISO 4217), `price_list_items` (**`taxonomy_id` obbl.**, `unit_price` **decimal 15,4** nella valuta del listino).
- **Preventivo**: `quotations` (**`customer_id` obbl.**, **`currency`**), righe in tabella **`quotations_items`** (+ `unit_price` 15,4 opz.), FK opz. verso listino.
- **Commessa**: `projects` con **`customer_id` obbl.** e `quotation_id` opz.; **nessun** `lead_user_id` (decisione prodotto).
- **Pianificazione**: `tasks` con **`taxonomy_id` obbl.** → `taxonomies` (niente tabella `activities` dedicata).
- **Consuntivo**: `time_entries` con `user_id`, **`started_at` / `ended_at`**, `taxonomy_id` obbl., FK opz. a `task`, `project`, `quotation_item`; più righe per utente (pause); sovrapposizione solo **validazione app**.
- **Dominio**: modelli su `Core\Overrides\Model`, `fillable`, `casts`, `$rules`, relazioni Eloquent; **migrate** su DB vuoto + **seed** minimo (albero attività tassonomie nel modulo ERP).

**Fuori dal MVP v1** (contabilità / cassa / chiusure)

- `movements`, `movement_allocations`, `balances`, partner pool, settle-up, `PaymentRequest`, enum cassa a regime, ETL, magazzino.
- **Filament** modulo ERP: **slice contabile ERP** in admin (`business-filament-erp-core-slice`); resto UI MVP commerciale + CRM/magazzino/fatture in `erp-filament-resources` / `business-filament-final`. Per MVP pre-contabilità si intende **backend verificabile** anche senza tutta l'UI.

**Ordine di lavoro suggerito**

1. ~~`fix-task-activity-migrations`~~ (**completato** in repo).
2. ~~`business-models-rules-casts-relations`~~ (**completato**: `getRules()` MVP + `Movement` su Core stub).
3. `time-domain` (sovrapposizione applicativa `TimeEntry`; scope/query aggregati se servono).
4. Chiusura `mvp-precontabilita-v1` quando `time-domain` + seed dev + verifica migrate/test sono **completed**.

**Nota su struttura file migration**: raggruppare o separare file è una **scelta di manutenzione** (rollback, code review). Il vincolo duro resta solo l’**ordine delle dipendenze** tra file e `down()` speculari.

## Principi prodotto (Laraplate ≠ applicazione verticale)

- **Laraplate** è **piattaforma** per costruire applicazioni (gestionali, CMS, ecommerce, agenti, siti): moduli prefatti, estendibili, componibili. **Non** è un singolo prodotto verticale chiuso né un ERP monolitico tipo SAP.
- Il modulo **ERP** deve restare **settore-neutro**: vocabolario da **economia / operatività / amministrazione leggera** comprensibile a **programmatori** che montano soluzioni per clienti diversi. Dove un nome è ambiguo (`Quote`, `Project`), il piano accetta **alias documentati** (config, interfaccia, o subclass) senza cambiare il cuore dati se non serve.
- **Obiettivo di profondità**: core **snello** + punti di estensione (`BillingStrategy`, provider pagamenti, policy lock) + verticali opzionali (professionisti, retail, …) **fuori** dal contratto minimo del modulo.
- **Regole di naming** (per commit e PR degli integratori):
  - evitare nomi o strutture giustificati solo con «così avevamo fatto in un progetto interno» senza valutazione di dominio generico;
  - preferire nomi che **descrivono il ruolo nel dominio generico** (`CommercialDocument` vs `Quote` se un giorno si generalizza; oggi `Quote` resta accettabile come «offerta numerata» in molti gestionali);
  - documentare in **README modulo** (quando si scrive) il **glossario** ufficiale e gli alias consigliati.

## Contesto verificato (repo Laraplate)

- **Modulo ERP** (`[Modules/ERP](file:///srv/http/laraplate/Modules/ERP)`; Composer `swolley/laraplate-erp`; namespace `Modules\ERP`): migration per `price_lists`, `price_list_items`, `time_entries`, `tasks.taxonomy_id`, `quotations`/`quotations_items`, `projects`, pivot `contactables`; modelli MVP su `Core\\Overrides\\Model` incluso **`Movement`** (stub tabella). **`getRules()`** su Project, Task, TimeEntry, PriceList, PriceListItem, QuotationItem (+ anagrafica/preventivo già coperti). Restano seed dev tassonomie operativo, validazione sovrapposizione `TimeEntry`, test/`migrate` su DB pulito. Stub `movements`/`balances` fuori MVP pre-contabilità.

---

## Definizioni entità ERP

Obiettivo: **stessa semantica** in piano, codice, API e UI. I nomi in **grassetto** sono i concetti; tra parentesi il nome attuale in repo se diverso dal piano storico.

### Convenzione naming documento vs codice

- Il modello e la tabella reali sono **`Quotation`** / **`quotations`**; le righe sono **`QuotationItem`** / **`quotation_items`** (stesso schema di naming di `PriceList` / `PriceListItem`). Il termine **Quote** resta solo sinonimo discorsivo di «offerta/preventivo» se serve in prosa, non come nome di tabella.

### Core / Cms (solo confine con ERP)

| Entità | Ruolo | Confine |
|--------|--------|---------|
| **`Place`** | Luogo geografico neutro (indirizzo, coordinate opz.) | ERP **non** duplica colonne indirizzo su `Site`: usa `place_id` → `places`. |
| **`Taxonomy`** (tabella `taxonomies`) | Albero configurabile (parent, traduzioni, preset dove previsto) | **Foglie e rami** = dati applicativi. **`Modules\ERP\Casts\EntityType`** indica **quale albero** (es. attività lavorative vs etichette movimento cassa). **Non** enum per ogni tipo di lavoro del cliente. |
| **`Category` (Cms)** | Tassonomia contenuti CMS | Fuori dal perimetro ERP salvo riuso concettuale del pattern. |

### ERP — anagrafica e sedi

| Entità (modello) | Scopo | Non è | Relazioni note | Stato / gap |
|------------------|--------|-------|----------------|-------------|
| **`Customer`** | Soggetto commerciale **leggero** (anagrafica senza contabilità ufficiale): per **v1** **nessun dato fiscale/commerciale esteso** (niente P.IVA, fatturazione elettronica, registrazioni contabili nel perimetro del modulo). | Un `User` del sistema; non è automaticamente “contatto”. | `hasMany` Contact, Quotation, Project. | Tabella minimale (`name`, …); estensioni fiscali **posticipate**. |
| **`Contact`** | Persona / punto di contatto; legame a **uno o più** `Customer` tramite pivot **`contactables`** (stesso record contatto riusabile su più clienti). Può avere **`User`**. | Non è il “lead progetto” (non esiste più `lead_user_id` su `Project`). | `belongsToMany` Customer via `contactables`; opz. `belongsTo` User. | **Nessun** `customer_id` sulla tabella `contacts`. **Nessun contatto primario** in v1. |
| **`Site`** | Sede operativa **dell’organizzazione** che usa il gestionale (studio, cantiere, filiale), per calendario e contesto fisico. | Un indirizzo cliente (quello resta su Customer/Contact/Place lato anagrafica se serve). | `place_id` (da aggiungere); Task opz. `site_id`. | Migrare da colonne duplicate a `Place`. |

### ERP — commessa e commerciale

| Entità | Scopo | Non è | Relazioni note | Stato / gap |
|--------|--------|-------|----------------|-------------|
| **`Project`** | **Contenitore di lavoro** verso un cliente: obiettivi, stato, collegamento opzionale a offerta accettata. Aggrega task, tempo, (dopo) movimenti. | Non è la singola “sessione” o la singola riga di listino. | `customer_id` **obbl.**, `quotation_id` opz.; **nessun** `lead_user_id`. | Migration `ProjectStatus`; lock preventivo / observer in backlog (`quote-revisions-core`). |
| **`Quotation`** | **Documento offerta** (header): cliente, stato, note, validità, **lineage/revisioni** quando implementato. | Non contiene il dettaglio economico senza righe; non è il consuntivo ore. | `belongsTo` Customer; `hasMany` `QuotationItem` (tabella `quotation_items`). | Estendere colonne revisione + `HasLocks` per bind progetto. |
| **`QuotationItem`** | **Riga offerta**: quantità, `billing_mode`, `unit_price` (15,4), opz. `price_list_item_id`. | Non è una timbratura. | FK a Quotation; opz. PriceListItem. | Tabella DB **`quotations_items`** (`QuotationItem::$table`). |
| **`PriceList`** | Contenitore listino con **`currency`** (ISO 4217); voci ereditano la valuta. | Non è il progetto né il preventivo. | `hasMany` PriceListItem. | Migration `price_lists` + `HasValidity`. |
| **`PriceListItem`** | Voce listino: **`taxonomy_id` obbl.**, `unit_price` 15,4, UOM opz. | Non enum applicativo chiuso nel modulo. | → `taxonomies` / `Activity`. | Migration `price_list_items`. |

### ERP — pianificazione e tempo

| Entità | Scopo | Non è | Relazioni note | Stato / gap |
|--------|--------|-------|----------------|-------------|
| **`Task`** | **Impegno calendarizzato o backlog pianificato**. | **Non** sostituisce le ore consuntive. | `project_id` opz., `site_id` opz., **`taxonomy_id` obbl.** → `taxonomies`. | FK verso `taxonomies` (no tabella `activities`). |
| **`TimeEntry`** | **Lavoro effettivo** / sessione (pause = più righe stesso utente). | Non è movimento di cassa. | **`taxonomy_id` obbl.**; `user_id`; **`started_at` / `ended_at`**; `task_id` / `project_id` / `quotation_item_id` opz. | Tabella `time_entries`; sovrapposizione **solo app**. |

### ERP — contabilità leggera (dopo organizzativo)

| Entità | Scopo | Non è | Nota |
|--------|--------|-------|------|
| **`Movement`** | Registrazione entrata/uscita **cassa** e metadati collegati a progetto/contatto dove serve. | Dettaglio IN/OUT e allocazioni: **da definire** dopo le entità organizzative. | Enum `MovementType` = solo direzione **income/expense**; granularità su **Taxonomy** + `EntityType::MOVEMENTS` se serve. **Oggi** (`movements` stub): **nessuna** FK a `Customer`/`Contact` in DB. **Target** (piano): FK **opzionali** `customer_id` e/o `contact_id` e/o `project_id` a seconda del caso (es. incasso collegato al cliente e al contatto che ha pagato; solo cliente; solo progetto) — cardinalità e vincoli da fissare con il dominio movimenti. |
| **`MovementAllocation`** (prevista) | Quota di riparto su socio / attore. | — | Posticipato con il modello movimenti. |
| **`Balance`** | Snapshot / congelamento periodo (es. anno). | — | Stub; regole lock da affinare con movimenti. |

### ERP — entità ERP roadmap (post-MVP, da Roadmap ERP)

> Riepilogo concettuale delle entita' introdotte nelle fasi M0-ERP -> M3.6. Per il dettaglio operativo (colonne, lock-chain, servizi) vedi sezione **Roadmap ERP completo (post-MVP)** + i todo dedicati nel frontmatter.

| Entità | Fase | Scopo | Note chiave |
|--------|------|-------|-------------|
| **`Company`** | M0-ERP | Tenant aziendale; root del global scope `BelongsToCompanyScope`. | `functional_currency` default `EUR`; tutti i transazionali hanno `company_id`. |
| **`Party`** (rename di `Customer`) | M3.6 | Anagrafica unica cliente/fornitore. | Colonna `roles` (json array: customer/supplier/both); FK `party_id` su tutti i documenti. |
| **`Account`** (Chart of Accounts) | M1 | Voce di piano dei conti italiano-pluggable. | `code` immutabile, `kind` ∈ asset/liability/equity/revenue/expense; assorbe la tassonomia movimenti. |
| **`JournalEntry`** + **`JournalEntryLine`** | M1 | Partita doppia bilanciata su `amount_local`. | Posting via `JournalPostingService`; reverse esplicito; snapshot `tax_code/rate/label` su line. |
| **`FiscalYear`** + **`FiscalPeriod`** | M1 | Periodi contabili con lock progressivo. | `status` open/closing/closed; chiusura blocca posting nel range. |
| **`DocumentSequence`** | M1 | Numerazione progressiva per (company, document_type, fiscal_year). | Lock pessimistico su tipi fiscali (0 buchi); `gap_allowed=true` su tipi non fiscali. |
| **`TaxCode`** | M2 | Aliquote IVA + ritenute italiane (estendibili). | `code` immutabile; cambio rate = nuovo row + `replaced_by_tax_code_id`. |
| **`Lead`** + **`Opportunity`** | M3.1 | Pipeline CRM pre-Quotation. | `stage_taxonomy_id` con `EntityType::OPPORTUNITY_STAGES`; conversione -> Quotation. |
| **`SalesOrder`** + **`SalesOrderLine`** | M3.2 | Ordine cliente (intermediario tra `Quotation` e `Project`). | Cardinalita' 1Q->NSO, 1SO->NProject, NSO->1Project; lock-chain progressivo su qty. |
| **`Item`** + **`Warehouse`** + **`StockLevel`** | M3.3 | Magazzino base + giacenze per item × warehouse. | `costing_method` ∈ fifo/weighted_avg per item; `weighted_avg_cost` su level. |
| **`StockMovement`** + **`StockCostLayer`** | M3.3 | Movimenti carico/scarico/trasferimento + layer FIFO. | Unico entry-point: `StockMovementService`; calcola COGS per il journal posting. |
| **`DeliveryNote`** + **`DeliveryNoteLine`** | M3.4 | DDT vendita; observer scarica magazzino. | Genera `stock_movements` di scarico via `StockMovementService`. |
| **`Invoice`** + **`InvoiceLine`** | M3.5 | Fattura sale/purchase; posting genera journal. | Numerazione `gap_allowed=false`; snapshot fiscale su line. |
| **`EInvoiceSubmission`** | M3.5 | Tracciamento invii e-invoicing (provider esterni). | Solo contratto `EInvoiceProvider` nel modulo; provider concreti = package separati. |
| **`PurchaseOrder`** + **`GoodsReceipt`** | M3.6 | Ciclo passivo: ordine al fornitore + bolla ingresso. | GR carica magazzino via `StockMovementService`; riconciliazione 3-way PO/GR/Invoice. |

### Regole trasversali (da rispettare in analisi)

1. **Tipo di attività lavorativa** = nodo **`Taxonomy`** (dati), discriminato da **`EntityType`** (cast `Modules\ERP\Casts\EntityType`), **mai** enum chiuso nel modulo per le foglie cliente-specifiche.
2. **Pianificazione (`Task`) ≠ consuntivo (`TimeEntry`)**; il secondo può esistere senza il primo (sessione libera).
3. **Listino** è la **fonte** del tipo attività per righe che nascono da `PriceListItem`; **sessione** porta comunque **`taxonomy_id`** per report e casi senza preventivo/riga.
4. **`QuotationItem`** (via `quotation_item_id`) opzionale su **`TimeEntry`** per confronto commerciale, non per sostituire `taxonomy_id` sulle aggregazioni per tipo attività.
5. **`TimeEntry` ha esattamente un’activity (tassonomia)** — cardinalità **1:1** lato sessione verso il nodo scelto; niente tabella pivot tipo `categorizables` per questo legame.
6. **Multi-company sempre attivo** (post-M0-ERP): ogni entità transazionale ERP ha `company_id` + global scope `BelongsToCompanyScope`. In M0 1 sola company `default`, ma il vincolo strutturale c'e' da subito.
7. **Multi-currency predisposto** (post-M0-ERP): ogni amount monetario ha colonne `amount_doc` + `currency_doc` + `amount_local` + `fx_rate`. `amount_local` e' la base per la partita doppia.
8. **Versioning forzato** sui modelli contabili tramite property `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;` sulla classe — vince sempre sul `Setting` (vedi todo `enforce-versioning-on-accounting-models`).
9. **Snapshot fiscale immutabile** sulle righe documento al posting; cambi aliquota = nuovo `tax_code` (no UPDATE retroattivo).
10. **Lock-chain SalesOrder** progressivo (vedi paragrafo "Lock preventivo / lock SO" aggiornato): confirm SO -> Q lockata + SO header lockato; prima evasione -> riga lockata su `qty_ordered`; modifiche radicali via amendment SO.

---

## Glossario: mapping da gestionale Symfony legacy (solo per ETL / analisi)


| Classe / concetto nel gestionale sorgente | Ruolo nel gestionale sorgente | Mappa concettuale Laraplate (non normativa) |
| ----------------------------------------- | ----------------------------- | ------------------------------------------- |
| `Client`, `Contact`, `user_client`        | Anagrafica                    | Mappa concettuale → `Customer` / `Contact`  |
| `Work`                                    | Commessa                      | Mappa → `Project`                           |
| `Appointment` / `WorkSession`             | Pianificazione vs consuntivo  | Mappa → `Task` / `TimeEntry`                |
| `Quotation` (+ parent)                    | Offerta con revisioni         | Mappa → `Quotation` + duplica + lineage     |
| `PriceList`                               | Listino                       | Mappa → `PriceList` / `PriceListItem`       |
| `Movement` IN/OUT, `MovementUser`         | Entrate/uscite e riparti      | Mappa → `Movement`, `MovementAllocation`    |
| `Equipment`                               | Asset                         | **Non** nel core ERP; verticali        |
| `Transfer`                                | Compensazioni tra soci        | Mappa → pool / settlements (piano Tricount) |


## Modello ERP (requisiti) e glossario implementativo


| Concetto di dominio (generico)                                                               | Tabella / classe sorgente (solo ETL)                           | Note implementative                                                                                                                                                                                                                                                                                                                             |
| ------------------------------------------------------------------------------------------ | -------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Customer + Contact                                                                         | `Client`, `Contact`                                            | Già in `[customers](file:///srv/http/laraplate/Modules/ERP/database/migrations/2026_04_08_191717_create_customers_table.php)` / `[contacts](file:///srv/http/laraplate/Modules/ERP/database/migrations/2026_04_08_191726_create_contacts_table.php)` + pivot **`contactables`**. **Nessun** `customer_id` su `contacts` (M:N); v1 senza dati fiscali estesi su `customers`; **nessun contatto primario**.                   |
| Project obbligatorio su Customer, opzionale su offerta, **owner interno** (`lead_user_id`) | `Work` + `client_id`, `quotation` opzionale                    | `lead_user_id` → `users` (Core). Distinto dal contatto principale cliente.                                                                                                                                                                                                                                                                      |
| Task = todo/appuntamento pianificato                                                       | `Appointment` (calendario) + meta su `Work`                    | Non fondere todo e sessione sulla stessa riga: la **pianificazione** (stime, calendario) e l’**effettivo** (ore lavorate) hanno cicli di vita diversi.                                                                                                                                                                                          |
| Sessione / lavoro effettivo                                                                | `WorkSession` (con `event_start`/`event_end` + utenti)         | La “pivot utente–todo” **sola** non basta per parallelismo o fasce orarie: serve una entità **TimeEntry** (o `work_sessions`) con `user_id`, `actual_start`, `actual_end`, **`taxonomy_id`** (catalogo attività), `quotation_item_id` **opzionale**, `task_id` **nullable**, `project_id` **nullable** (denormalizzato da task o libero per lavoro non pianificato). Più righe = più persone o turni. |
| Attività non pianificate                                                                   | Sessioni senza appuntamento collegato                          | `task_id` null + `project_id` opzionale (progetto) o null (solo attività interna organizzazione).                                                                                                                                                                                                                                               |
| Tipologia attività (calendario vs consuntivo)                                              | (nel sorgente: accoppiamento listino / sessione)               | **Nessun enum “ActivityType” nel modulo ERP** (troppo dipendente dalla verticale). **Catalogo = nodi `taxonomies`** con discriminante `Modules\ERP\Casts\EntityType::ACTIVITIES` (e modelli concreti che estendono `Taxonomy`). Sul **Task**: `taxonomy_id` indicativo. Sul **TimeEntry**: `taxonomy_id` consuntivo per aggregati; `quotation_item_id` opzionale per allineamento commerciale. Opzionale: snapshot tipo su `quotation_item` se il listino cambia dopo l’offerta. |
| Movimenti IN/OUT, categorie, riparti                                                       | `Movement`, `MovementType`/`Category`, `MovementUser`          | **Posticipato** dopo il livello organizzativo (progetti, tempo, listino, preventivo). Classificazione cassa: enum `MovementType` (income/expense) + alberi `taxonomies` con `EntityType::MOVEMENTS` dove serve granularità. Dettaglio riparti/IN-OUT da affrontare in conversazione dedicata.                                                                                                                    |
| Versamenti soci per quadrare spese                                                         | `Transfer` + (idea) `Payment`                                  | Modellare come movimenti di **rettifica** o tabella `partner_settlements` che referenzia movimento spesa + movimento versamento, per evitare doppi conteggi nel bilancio.                                                                                                                                                                       |
| Congelamento anno + totali                                                                 | (nel sorgente: report mensili aggregati; lock globale assente) | Tabella `balances` (snapshot: anno, total_in, total_out, crediti/debiti clienti, debiti/crediti soci, `frozen_at`). Flag `movements.locked_by_balance_id` o `locked_at` + anno competenza. Runtime: somma solo righe non lockate + somma snapshot anni chiusi.                                                                                  |


### Luoghi: `Place` (Core) — `Location` (Cms) — `Site` (ERP)

- **P0 — Place + Location** (Core + Cms): **completato**; consolidare il codice ERP su `sites.place_id` / ICS quando si tocca il modulo ERP. Riferimento todo: `p0-core-place-location-refactor`.
- **Non** spostare in Core il modello Cms `**Location`** così com’è: è accoppiato a slug, tag, Typesense, spatial avanzato, pivot con **Content**, Filament.
- **Sì** a `**Place`** in **Core**: identità geografica neutra — etichetta, righe indirizzo testuale, opz. coordinate (decimali o spatial **solo** se accettabile in Core senza dipendenze Cms).
- `**GeoPoint`** separato: **solo** se serve riuso forte dello stesso punto; altrimenti coordinate su `Place`. Composizione **1:1** preferibile a ereditarietà SQL.
- **Cms `Location`**: colonna `**place_id**` (FK Core); slug, search, tag, **Content** restano in Cms.
- **Trait trasparente (stesso spirito di `HasTranslations` / `HasDynamicContents`)**: un trait (nome da scegliere, es. `HasBridgedPlace` o `DefersGeographyToPlace`) su `**Location`** che intercetta **getter/setter** (o `getAttribute` / `setAttribute` con le stesse priorità del pattern esistente) così che i campi “geografici” usati oggi in app (`address`, `city`, `province`, `country`, `postcode`, coordinate…) **leggano e scrivano** sul `**Place`** collegato, creando/aggiornando `Place` al bisogno. Obiettivo: **non rompere** Filament, API e codice che già fa `$location->city = …` / `$location->address`.
- **Rotte e servizi**: spostare in **Core** ciò che è **generico** (interfacce geocoding, HTTP client verso provider, eventuale controller/route registrata dal Core o route Cms che delega a servizio Core) — stesso approccio “logica condivisa in Core, wiring UI in Cms”. Oggi es. rotta Cms `[web.php](file:///srv/http/laraplate/Modules/Cms/routes/web.php)` `locations.geocode` e servizi tipo `AbstractGeocodingService` / `NominatimService` / `GoogleMapsService` vanno **ripacchettizzati** (contratti + implementazioni Core dove non servono dipendenze Cms).
- **`Site` (ERP)**: evolvere con `**sites.place_id**` e deprecazione colonne duplicate (P0 disponibile).
- **ICS / calendario**: dopo `Place` stabile; vedi `calendar-ics-export`.

### Tassonomie: `Taxonomy` astratta (Core), tabella `taxonomies`

- **P1 — Taxonomy + Category** (Core + Cms): **completato**. Todo: `p1-core-taxonomy-category-refactor`.
- **Regola architetturale Laraplate**: tutto dipende da **Core**; **Core non referenzia** altri moduli. I moduli (Cms, ERP, …) **estendono** classi Core.
- `**Entity` + `Preset`** restano il precedente di astrazione Core / concretezza nei moduli.
- **Naming**: in Core la base non si chiama `Category` (nome troppo legato al CMS); si introduce `**Taxonomy`** come `**abstract class`** (o equivalente idiom Laravel) con **tabella unica** `**taxonomies`** (plurale coerente con il resto del framework).
- `**protected $table = 'taxonomies';`** va dichiarato **sul modello base** `Taxonomy` così **tutte le sottoclassi** ereditano lo stesso nome tabella e Laravel **non** inferisce nomi diversi dal nome corto della classe (`categories`, `activity_types`, …). Le sottoclassi (`Modules\Cms\Models\Category`, eventuali classi `ActivityTaxonomy` nel modulo ERP, …) aggiungono solo **scope globali**, **cast**, **relazioni** e trait Cms-specifici dove serve; oppure restano classi “vuote” che servono solo da **type hint** / query builder dedicato.
- **Schema tabella** (indicativo): `parent_id`, `scope` o `domain` (stringa enum applicativa: es. `cms.content`, `erp.activity`, `erp.movement`), ordinamento, `meta` JSON opzionale, soft delete coerente Core; eventuale colonna **STI** `type` se un giorno servono sotto-modelli con cast diversi sulla stessa tabella (valutare vs solo `scope`).
- `**Category` Cms** oggi è ricca (media, traduzioni, approvals, pivot Content, …): **refactor** verso `**Category extends Taxonomy`** (stessa tabella `taxonomies` + scope Cms), mantenendo i trait Cms sulla sottoclasse; **migrazione dati** da `categories` → `taxonomies` dove si unifica lo storage.
- **ERP**: `taxonomy_id` → `taxonomies` per attività su Task/TimeEntry e (in seguito) per etichette movimento cassa; **enum `Modules\ERP\Casts\EntityType`** (`MOVEMENTS`, `ACTIVITIES`, …) indica **quale albero / entità dinamica** (stesso ruolo concettuale di `Modules\Cms\Casts\EntityType` per Cms), non le singole foglie del catalogo.
- **Effetto**: alberi multipli riusabili (attività lavorative, etichette movimento, …) con vincoli per contesto lato app (es. “solo foglie su TimeEntry”). **Non** si introduce `MovementCategory` come tabella separata: la gerarchia è nei nodi tassonomia per lo scope scelto.

### Task vs sessione: significato e flusso UI (calendario + timbrature)

- `**Task`** resta il nome preferito: non equivale a “solo appuntamento fisico”. È una **unità di lavoro calendarizzata** — stesso concetto per (a) slot in studio con cliente, (b) lavoro remoto in finestra oraria, (c) **backlog da chiudere** per un programmatore (“implementare funzione X” con `planned_start`/`planned_end` o scadenza). Il canale (on-site / remote / async) si modella con **tassonomia** e/o **campo leggero** (`fulfillment_mode`, enum) senza rinominare l’entità in `Appointment` (troppo legato al fisico nel linguaggio naturale).
- `**TimeEntry`** = **sessione di lavoro reale** (timbratura inizio/fine, più righe per pause o segmenti): **N sessioni** possono riferire lo **stesso** `Task`; **più utenti** = più `TimeEntry` (stesso `task_id`, `user_id` diversi). Pause: o più righe consecutive o tipo segmento “pause” — da definire in implementazione.
- **Senza Task pianificato**: `TimeEntry` con `task_id` null, `**project_id`** + tassonomia attività scelti da elenco commesse aperte.
- **Sessioni interne** (utilizzo struttura, corrente, riparti): `TimeEntry` con `**project_id` null**, tassonomia attività **interna** (scope dedicato), così si misura **chi** ha occupato lo spazio e **quanto**, senza commessa cliente.
- **Gantt / pianificazione teorica** (milestone, dipendenze, barre lunghe): **entità separata**, **posticipata** (todo `gantt-planning-entity`); non blocca MVP calendario + timbrature.
- **Non** mettere solo `session_start`/`session_end` sul Task: mescola pianificazione e consuntivo.
- **Sì**: Task con `planned_start` / `planned_end` (o equivalente), FK tassonomia attività, `project_id` dove il lavoro è di commessa (cliente noto); `**site_id`** opzionale (sede fisica prescelta per lo slot, allineabile in seguito a `**Place`** Core); eccezioni per slot puramente organizzativi se si modellano come Task senza `project_id` o solo via TimeEntry — da fissare in prodotto. **TimeEntry** con intervallo reale, `user_id`, FK opzionale a `task_id`, `project_id` opzionale, tassonomia consuntiva (può coincidere con quella pianificata o divergere con regole di validazione).

### Cosa rischi di dimenticare (checklist)

**MVP pre-contabilita' (livello organizzativo)**

- **Righe offerta** (`quotation_items`): senza righe strutturate non confronti ore consuntive vs stime per tipo/riga.
- **Listino** (`price_list_items`): utile quando molte offerte riusano le stesse voci tariffarie versionate nel tempo.
- **Allegati** su movimenti (pattern generico tipo `Attachment` / media Core).
- **Periodo competenza** uscite (`period_from`/`period_to`) per spese pluriennali o rate in bilancio.
- **Valuta, IVA, arrotondamenti** (evitare `float` in contabilità).
- **Stati Task** (todo / in corso / fatto / annullato) e regole transizione.
- **Sovrapposizioni** TimeEntry stesso utente (validazione).
- **Permessi** su congelamento anno e chi può sbloccare (se mai).
- **Audit** su lock e modifiche post-facto.

**Roadmap ERP (post-MVP) — rischi specifici**

- **Multi-company a posteriori**: aggiungere `company_id` + scope dopo che il modulo ha gia' dati e' costoso e rischioso. Si fa in **M0-ERP** una volta sola, anche se in v1 esiste 1 sola company default.
- **Multi-currency a posteriori**: aggiungere `amount_local` + `fx_rate` a tabelle gia' transazionali rompe i totali storici. Schema dual-currency in **M0-ERP** anche se EUR/EUR fx=1.0.
- **Snapshot fiscale**: dimenticarsi di denormalizzare `tax_code/rate/label` sulle righe = rischio di rivalutare retroattivamente il passato al cambio aliquota IVA. Snapshot e' **obbligatorio** al posting (M2).
- **Numerazione progressiva sotto contesa**: senza lock pessimistico DB su `document_sequences` per i tipi fiscali, due fatture concorrenti possono ottenere lo stesso numero o lasciare buchi. Tipi fiscali = `gap_allowed=false` sempre. Test concorrenza obbligatorio (M1).
- **Versioning negoziabile via Setting**: per i modelli contabili la property `versionStrategy` direttamente sulla classe vince sul record `settings` (M0-ERP). Disattivazione a runtime via UI = vietata.
- **Magazzino prima del DDT**: il DDT vendita scarica magazzino e calcola COGS; senza `inventory-erp-base` (M3.3) la fattura sale **non** ha valutazione costo. Sequenza M3.3 -> M3.4 -> M3.5 va rispettata.
- **Lock-chain SalesOrder**: senza lock progressivo su righe parzialmente evase, l'utente puo' modificare retroattivamente quantita' gia' fatturate -> rottura del legame contabile. Tutte le modifiche radicali vanno via amendment SO con `amends_sales_order_id`.
- **E-invoice nel modulo ERP**: implementare un provider concreto (es. SDI) **dentro** il modulo accoppia Laraplate a una giurisdizione. Tenere solo il contratto (M3.5).
- **Refactor cassa Tricount**: il saldo parallelo attuale di `Movement`/`PartnerPool` va deprecato in favore del libro giornale derivato (M2). Non lasciare due fonti di verita' del saldo.
- **Anagrafica `parties`**: rinominare `customers` in `parties` deve avvenire **prima** di entrare in build. Dopo, la rinomina richiederebbe migration `ALTER` complessa su FK gia' popolate.

Concetto trasversale utile: **audit** trail sulle entità commerciali — in Laraplate allineare a convenzioni Core (`HasVersions`, blameable, soft delete) già usate nei moduli; per i modelli contabili ERP la property `versionStrategy = DIFF` sul modello e' **obbligatoria** (vedi todo `enforce-versioning-on-accounting-models`).

---

## Modello dati Laraplate (tabelle / entità target)

Riferimento unico per **nome concettuale** → **tabella** prevista nel modulo ERP (salvo `users` in Core). Colonna «Stato» rispetto al repo `[Modules/ERP](file:///srv/http/laraplate/Modules/ERP)`.


| Entità (concettuale)       | Tabella (prevista)     | Ruolo sintetico                                                                                                                           | Stato repo                                 |
| -------------------------- | ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------ |
| Customer                   | `customers`            | Anagrafica cliente                                                                                                                        | Presente                                   |
| Contact                    | `contacts`             | Contatti; `user_id` opz.; legame clienti via **`contactables`** (M:N)                                                                      | Migration + modello                        |
| Taxonomy (abstract)        | `taxonomies`           | Core: albero gerarchico generico; `**abstract class Taxonomy`** con `**$table = 'taxonomies'`**; sottoclassi senza inferenza nome tabella | **P1 completato** (Core+Cms)               |
| Place                      | `places`               | Luogo geografico neutro (Core): indirizzo + opz. coordinate; condiviso da Cms/ERP                                                    | **P0 completato** (Core+Cms)               |
| Site                       | `sites`                | Sede operativa; `place_id` → `places`                                                                                                     | Presente                                   |
| Quotation                  | `quotations`           | Preventivo; `customer_id`+`currency` obbl.; lock colonne (HasLocks); lineage in backlog                                                   | Migration + modello                        |
| QuotationItem             | `quotations_items`     | Voci preventivo (`billing_mode`, `unit_price` 15,4, FK `price_list_item_id` opz.)                                                          | Migration + modello                        |
| PriceList                  | `price_lists`          | Listino + **`currency`** + validità (`HasValidity`)                                                                                      | Migration + modello                        |
| PriceListItem              | `price_list_items`     | Voce: **`taxonomy_id` obbl.**, `unit_price` 15,4, UOM opz.                                                                                 | Migration + modello                        |
| Project                    | `projects`             | Commessa: `customer_id` obbl., `quotation_id` opt.; **nessun** `lead_user_id`                                                               | Migration + modello                        |
| Activity / movement labels | `taxonomies`           | Stesso albero Core (`taxonomy_id` su Task/TimeEntry; movements dopo); discriminante `EntityType` (ERP) + scope app; **no** tabella `activity_types` obbligatoria nel core modulo | P1 fatto (infra); FK del modulo ERP da aggiungere con evoluzione schema |
| Task                       | `tasks`                | **Lavoro calendarizzato**; opz. `**site_id`** per sede; non Gantt teorico                                                                 | Presente (FK da correggere vs tassonomia)  |
| TimeEntry                  | `time_entries`         | Sessione lavoro reale per `user_id`; **`taxonomy_id`** = un solo nodo attività (no pivot); `quotation_item_id` opz. se legame a voce preventivo | Da creare                                  |
| Movement labels (gerarchia)| `taxonomies`           | Macro/tipi cassa come **nodi** sotto `EntityType::MOVEMENTS` (nessuna `movement_categories` dedicata)                                      | Dopo strato organizzativo                  |
| MovementType (enum cassa)| (enum / colonne)       | Solo **direzione contabile** `income` / `expense` in `Modules\ERP\Casts\MovementType` — **non** il catalogo foglie                    | Presente (enum); uso pieno posticipato   |
| Movement                   | `movements`            | Movimento IN/OUT; lock su bilancio                                                                                                        | Stub                                       |
| MovementAllocation         | `movement_allocations` | Quota su socio (OUT) o meta riparto IN                                                                                                    | Da creare                                  |
| PartnerPool                | `partner_pools`        | Cassa soci (v1: tipicamente una riga per org)                                                                                             | Da creare                                  |
| PoolTransaction            | `pool_transactions`    | Versamento / prelievo cassa ↔ socio                                                                                                       | Da creare                                  |
| Balance                    | `balances`             | Congelamento anno fiscale + snapshot                                                                                                      | Stub                                       |
| PaymentRequest             | `payment_requests`     | Richiesta incasso verso cliente/contact o saldo verso socio; stub provider                                                                | Da creare                                  |
| User                       | `users`                | Socio, lead progetto, allocazioni                                                                                                         | **Core** (solo FK)                         |
| Company                    | `companies`            | Tenant aziendale (M0-ERP); root del global scope `BelongsToCompanyScope`; `functional_currency` default `EUR`                            | **Roadmap ERP M0-ERP**                     |
| Party (rename Customer)    | `parties`              | Anagrafica unica cliente/fornitore (M3.6); `roles` json array (customer/supplier/both); FK `party_id` su tutti i documenti                | **Roadmap ERP M3.6** (in-place rename) |
| Account (CoA)              | `accounts`             | Chart of Accounts italian-pluggable; `code` immutabile, `kind` ∈ asset/liability/equity/revenue/expense; assorbe tassonomia movimenti     | **Roadmap ERP M1**                         |
| JournalEntry               | `journal_entries`      | Header partita doppia bilanciata su `amount_local` (M1); reverse esplicito                                                                | **Roadmap ERP M1**                         |
| JournalEntryLine           | `journal_entry_lines`  | Righe partita doppia con `account_id` + dual-currency + snapshot `tax_code/rate/label`                                                    | **Roadmap ERP M1**                         |
| FiscalYear                 | `fiscal_years`         | Esercizio contabile per company (M1)                                                                                                       | **Roadmap ERP M1**                         |
| FiscalPeriod               | `fiscal_periods`       | Periodo contabile (M1..M12) con `status` open/closing/closed; chiusura blocca posting nel range                                            | **Roadmap ERP M1**                         |
| DocumentSequence           | `document_sequences`   | Numerazione progressiva (company, type, fiscal_year); lock pessimistico per tipi fiscali (`gap_allowed=false`)                              | **Roadmap ERP M1**                         |
| TaxCode                    | `tax_codes`            | Aliquote IVA + ritenute italiane (M2); `code` immutabile; cambio rate = nuovo row + `replaced_by_tax_code_id`                                | **Roadmap ERP M2**                         |
| Lead                       | `leads`                | Pipeline CRM (M3.1); `party_id?`, `source`, `status`, `owner_user_id`                                                                       | **Roadmap ERP M3.1**                       |
| Opportunity                | `opportunities`        | Pipeline CRM (M3.1); `stage_taxonomy_id` con `EntityType::OPPORTUNITY_STAGES`; conversione -> Quotation                                    | **Roadmap ERP M3.1**                       |
| SalesOrder                 | `sales_orders`         | Ordine cliente intermediario (M3.2); cardinalita' 1Q->NSO, 1SO->NProject, NSO->1Project; lock-chain progressivo                            | **Roadmap ERP M3.2**                       |
| SalesOrderLine             | `sales_order_lines`    | Righe SO con `qty_ordered`/`qty_delivered`/`qty_invoiced`; lock su `qty_ordered` alla prima evasione                                       | **Roadmap ERP M3.2**                       |
| Item                       | `items`                | Anagrafica articoli (M3.3); `costing_method` ∈ fifo/weighted_avg per item                                                                  | **Roadmap ERP M3.3**                       |
| Warehouse                  | `warehouses`           | Sede magazzino (M3.3); opz. `place_id` Core                                                                                                 | **Roadmap ERP M3.3**                       |
| StockLevel                 | `stock_levels`         | Giacenze per item × warehouse × company (M3.3); `weighted_avg_cost` per costing media                                                      | **Roadmap ERP M3.3**                       |
| StockMovement              | `stock_movements`      | Movimenti carico/scarico/trasferimento (M3.3); `unit_cost` valorizzato; `source_type` morph (GR/DDT/manuale/inventario)                     | **Roadmap ERP M3.3**                       |
| StockCostLayer             | `stock_cost_layers`    | Layer FIFO per item × warehouse (M3.3); `qty_remaining`, `unit_cost`, `source_movement_id`                                                  | **Roadmap ERP M3.3**                       |
| DeliveryNote               | `delivery_notes`       | DDT vendita (M3.4) da SO; observer scarica magazzino via `StockMovementService`                                                              | **Roadmap ERP M3.4**                       |
| DeliveryNoteLine           | `delivery_note_lines`  | Righe DDT con `sales_order_line_id` + `qty`                                                                                                  | **Roadmap ERP M3.4**                       |
| Invoice                    | `invoices`             | Fattura sale/purchase (M3.5); posting genera `JournalEntry` automatico; numerazione `gap_allowed=false`                                      | **Roadmap ERP M3.5**                       |
| InvoiceLine                | `invoice_lines`        | Righe fattura con `tax_code_id` + snapshot fiscale denormalizzato al posting                                                                 | **Roadmap ERP M3.5**                       |
| EInvoiceSubmission         | `e_invoice_submissions`| Tracciamento invii e-invoicing (M3.5); provider concreti SDI/Peppol = package esterni                                                        | **Roadmap ERP M3.5**                       |
| PurchaseOrder              | `purchase_orders`      | Ordine al fornitore (M3.6); party.role=supplier                                                                                              | **Roadmap ERP M3.6**                       |
| PurchaseOrderLine          | `purchase_order_lines` | Righe PO                                                                                                                                     | **Roadmap ERP M3.6**                       |
| GoodsReceipt               | `goods_receipts`       | Bolla di ingresso (M3.6); observer carica magazzino via `StockMovementService`                                                                | **Roadmap ERP M3.6**                       |
| GoodsReceiptLine           | `goods_receipt_lines`  | Righe GR; supportano riconciliazione 3-way PO/GR/Invoice                                                                                      | **Roadmap ERP M3.6**                       |


---

## Diagrammi UML (Mermaid `classDiagram`)

**Nota**: la classe `User` modella la tabella `**users`** del modulo Core; tutte le FK verso utenti restano nel modulo ERP. `**Taxonomy`** è il modello astratto Core sulla tabella `**taxonomies`** (con `$table` esplicito sul base); nel diagramma è mostrato come tipo di dominio per le FK attività e listino. **`Modules\ERP\Casts\EntityType`** (e/o scope su query) distingue **quale albero** si sta usando (attività vs movimenti cassa, ecc.).

### Dominio anagrafica — preventivo — progetto — tempo

**Nota anagrafica (aggiornato 2026-05)**: il rename `customers → parties` (M3.6) è completato: `Party` ha flag `is_customer`/`is_supplier` che distinguono ruolo cliente vs fornitore. `Contact` e `Party` sono in relazione **M:N** tramite pivot **`contactables`** (nessun `party_id` su `contacts`). `Lead` e `Opportunity` (M3.1) sono parte del flusso CRM: `Lead` → `Opportunity` → `Quotation` (l'opportunità diventa `won` quando il preventivo passa a `accepted`).

```mermaid
classDiagram
  direction TB
  class User
  class Party {
    +bool is_customer
    +bool is_supplier
  }
  class Contact
  class Site
  class Lead
  class Opportunity
  class Quotation
  class QuotationItem
  class PriceList
  class PriceListItem
  class Project
  class Taxonomy
  class Task
  class TimeEntry
  Party "*" --> "*" Contact
  Contact --> User
  Party "1" --> "*" Lead
  Party "1" --> "*" Opportunity
  Party "1" --> "*" Quotation
  Party "1" --> "*" Project
  Lead "0..1" --> "0..1" Opportunity
  Opportunity "1" --> "*" Quotation
  Quotation "1" --> "*" QuotationItem
  Quotation --> Quotation
  PriceList "1" --> "*" PriceListItem
  QuotationItem --> PriceListItem
  Taxonomy "1" --> "*" PriceListItem
  Project --> Party
  Project --> Quotation
  Project --> User
  Taxonomy "1" --> "*" Task
  Taxonomy "1" --> "*" TimeEntry
  Project "0..1" --> "*" Task
  Task --> Site
  Project "0..1" --> "*" TimeEntry
  Task "0..1" --> "*" TimeEntry
  TimeEntry --> User
  TimeEntry --> QuotationItem
```



### Dominio scadenzario, pagamenti e allocazioni (M5.1)

**Nota (2026-05)**: il vecchio dominio `Movement` / `MovementAllocation` / `PartnerPool` / `PoolTransaction` / `Balance` / `PaymentRequest` è stato **superato**. Il refactor `accounting-refactor-cash-tricount` resta `pending`: la cassa diventa **derivata** dal journal contabile (vedi M2). Il dominio attuale dei pagamenti è M5.1: `PaymentTerm` (regole rateali in JSON `rate_lines`), `PaymentScheduleLine` generato automaticamente al posting fattura, `Payment` con direzione `inbound`/`outbound` allocato alle scadenze tramite la pivot `PaymentAllocation`. Lo stato della scadenza transita `open → partial → paid` in funzione delle allocazioni; `AgingReportService` fornisce il partitario per fasce 30/60/90/120+ giorni.

```mermaid
classDiagram
  direction TB
  class Party {
    +bool is_customer
    +bool is_supplier
  }
  class Invoice {
    +InvoiceDirection direction
    +InvoiceType invoice_type
  }
  class PaymentTerm {
    +json rate_lines
  }
  class PaymentScheduleLine {
    +PaymentScheduleStatus status
    +date due_date
    +decimal amount_doc
    +decimal paid_amount_doc
  }
  class Payment {
    +PaymentDirection direction
    +date payment_date
    +decimal amount_doc
  }
  class PaymentAllocation {
    +decimal allocated_amount
  }
  class JournalEntry
  Party "1" --> "*" Invoice
  Party "1" --> "*" Payment
  Invoice --> PaymentTerm
  Invoice "1" --> "*" PaymentScheduleLine
  Payment "1" --> "*" PaymentAllocation
  PaymentScheduleLine "1" --> "*" PaymentAllocation
  Payment ..> JournalEntry : posts
  Invoice ..> JournalEntry : posts
```

### Dominio registri IVA + liquidazione (M5.3)

**Nota (2026-05)**: il posting della fattura registra una `VatRegisterEntry` per ogni `tax_code` con un `protocol_number` progressivo per `register_type` (vendite/acquisti) per anno fiscale. Il `VatSettlementService` calcola la liquidazione periodica come differenza IVA a debito − IVA a credito − credito periodo precedente.

```mermaid
classDiagram
  direction TB
  class Invoice {
    +InvoiceDirection direction
    +InvoiceType invoice_type
  }
  class TaxCode
  class FiscalYear
  class FiscalPeriod
  class VatRegisterEntry {
    +VatRegisterType register_type
    +int protocol_number
    +decimal taxable_amount
    +decimal tax_amount
  }
  class VatSettlement {
    +VatSettlementStatus status
    +decimal vat_sales
    +decimal vat_purchases
    +decimal previous_credit
    +decimal settlement_amount
  }
  Invoice "1" --> "*" VatRegisterEntry
  TaxCode "1" --> "*" VatRegisterEntry
  FiscalYear "1" --> "*" VatRegisterEntry
  FiscalYear "1" --> "*" FiscalPeriod
  FiscalPeriod "1" --> "1" VatSettlement
  VatSettlement ..> VatRegisterEntry : aggregates
```



---

## Principio di design: core agnostico + verticali

Il modulo **ERP** espone linguaggio di dominio **riusabile** (commessa, pianificazione, consuntivo, documento commerciale, listino, movimenti, riparti, pool liquidità). I **verticali di settore** restano in **moduli o package** che *usano* il modulo ERP, non nel vocabolario minimo delle migration core:

- **Estensione** via moduli Laraplate, oppure
- **Riferimenti polimorfici** / metadata dove serve agganciare entità esterne senza accoppiare il core a un CRM specifico.

```mermaid
flowchart LR
  subgraph erp_domain [ERP domain - sector-neutral]
    Project[Project]
    Time[TimeEntry]
    Catalog[PriceListItem]
    Quotation[Quotation / Invoice]
    Ledger[JournalEntry]
    Lines[JournalEntryLine]
  end
  subgraph optionalVertical [Verticale opzionale]
    ExtensionAdapter[TenantDomainExtension]
  end
  ExtensionAdapter -->|"morph o FK"| Project
  ExtensionAdapter -->|"policy pricing"| Catalog
  Quotation -.->|"posting"| Ledger
  Ledger --> Lines
```



---

## Cosa tenere (idee/strutture) e come generalizzarle

### 1. Contenitore di lavoro (`Work` → **Project**)

- **Tenere**: stato attivo, descrizione, aggregazione tempo e incassi; **offerta** (`quotation_id`) opzionale (pattern comune: commessa con o senza preventivo accettato).
- **Nel perimetro ERP (progetto Nebula)**: `customer_id` **obbligatorio**, `quotation_id` **nullable**, `lead_user_id` **obbligatorio** → utente/socio responsabile interno. **`Contact`**: `customer_id` **sempre obbligatorio** (nessun contatto orfano). **Contatto primario**: non previsto in v1. **Fornitore / `Party`**: interessante ma **posticipato** (tocca dominio movimenti, non ancora definito).
- **Comportamento**: `isPaid()` / saldo progetto = **servizio dominio** sulle righe movimento IN collegate a `project_id` / `contact_id`, più eventuale confronto con totale `quotations` + `quotation_items`.

### 2. Eventi temporali (`AbstractEvent` → **TimeBlock** unificato o due tipi)

- **Tenere**: `start`, `end`, `day` (denormalizzazione per report), partecipanti many-to-many, descrizione, indici anno/mese (reporting).
- **Generalizzare**: pianificazione vs consuntivo possono essere `kind` enum (`planned`, `actual`) su un’unica tabella o due tabelle; il legame opzionale tra impegno calendario e consuntivo resta una **relazione esplicita**, non ereditarietà tipo ORM legacy.
- **Policy di valorizzazione**: importi / overflow rispetto a offerta e listino → interfaccia `**BillingStrategyInterface`** nel modulo o in estensione; implementazione default generica + implementazioni verticali opzionali (non nel contratto minimo del core).

### 3. Documento commerciale e righe (**`Quotation`** + `quotation_items`)

- **Tenere**: intestazione (date, scadenza, sconto, descrizione), righe con quantità/opzioni e regole economiche per voce.
- **Revisioni (decisione confermata)**: **non** merge ad albero tipo legacy (`EXTEND`/`OVERWRITE` su entità collegate). Flusso: **duplica** documento offerta → nuovo record con **lineage** e **numero revisione** persistito (§ «Duplica + lineage»).
- **Legame al progetto**: `projects.quotation_id` opzionale (allineare naming al repo). **Immutabilità**: alla creazione progetto con preventivo, il **`Quotation`** (e le sue `quotation_items`) viene **bloccato** — vedi § 3quater (non serve tabella pivot progetto–righe solo per congelare: il lock è la fonte di verità). Consuntivo vs offerta: `**time_entries.quotation_item_id`** opzionale + aggregati per **`taxonomy_id` sulla sessione**; con legame preventivo, controlli commerciali anche sulla riga.

### 3bis. Progetto, preventivo e attività (requisito operativo)

- Il **progetto** ha **attività** (Task / TimeEntry): possono **non** rispecchiare il preventivo, essere **di più**, o il preventivo può **decadere** (nuovi accordi): modello da PMI, non da procedure rigide.
- **Con `quotation_id` sul progetto**: le voci restano su `quotation_items` del `Quotation` scelto; il **lock** impedisce retroattività indesiderata senza duplicare righe sul progetto.
- **Senza preventivo**: nessun lock; confronti solo a livello tipo attività / totali progetto.

**Requisito righe offerta**: ogni `quotation_item` definisce **tipo di prezzo** (fisso, a ore, overflow incluso, consuntivo, …).

### 3ter. Duplica + lineage su `quotations` (alternativa al merge ad albero)

Campi previsti sulla tabella **`quotations`** (nomi colonna possono restare suffisso `quote` per compatibilità legacy oppure rinominarsi in `root_quotation_id` / `previous_quotation_id` in una migration dedicata):


| Campo               | Obbligo                                   | Note                                                                                                                                                                                  |
| ------------------- | ----------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `root_quote_id`     | FK, primo della catena                    | Stabile per intestazioni e listing «tutte le revisioni di X». Sull’**originale**: `null` oppure **self** (`id`) dopo insert — una sola convenzione in app.                            |
| `previous_quote_id` | nullable                                  | Da **quale** preventivo è stata creata questa revisione (duplica). **Null** sull’originale.                                                                                           |
| `revision_number`   | int, persistito, readonly in UI post-save | Default **1** sull’originale; su duplica: `max(revision_number ove root_quote_id = stesso root) + 1` in **transazione** (preferito al solo `COUNT` per soft delete / buchi / branch). |
| `revision_scope`    | enum                                      | Es. `amendment` (aggiunta / revisione incrementale) vs `full_replacement` (nuova offerta che sostituisce la precedente a fini commerciali), senza motore di merge automatico.         |


- **UX**: azione «Nuova revisione» = duplica righe + header, precompila lineage, consente edit prima del salvataggio.
- `**HasVersions`** (Core): opzionale su `Quotation` / `QuotationItem` per **audit** sullo stesso record revisione, **ortogonale** al lineage sopra.

### 3quater. Lock preventivo / lock SO (decisione consolidata 2026-04)

> **Aggiornamento Roadmap ERP**: il lock del preventivo **non** scatta piu' alla creazione del `Project`, ma al **confirm del `SalesOrder`** (M3.2). Il `Project` resta modificabile e puo' essere creato anche dopo, con cardinalita' 1 SO -> N Project / N SO -> 1 Project. Per chi sta lavorando alla fase MVP pre-contabilita' (no SO ancora), il vecchio comportamento (`ProjectObserver` -> lock `Quotation` quando `quotation_id` e' valorizzato) **resta valido** in via transitoria fino all'arrivo della Roadmap ERP M3.2.

#### Lock-chain progressivo (target Roadmap ERP M3.2)

1. **`SalesOrder.status = confirmed`** -> **`Quotation` lockata** (intestazione + righe `quotation_items`). Tutta la modifica successiva passa da revisione/duplicazione del Quotation oppure da amendment SO.
2. **Confirm SO** -> **header `SalesOrder` lockato** (party, condizioni commerciali, totali, valuta immutabili dopo confirm).
3. **Prima `DeliveryNote` o prima `Invoice`** su una `SalesOrderLine` -> **riga lockata su `qty_ordered`** + campi anagrafici riga (item, descrizione, prezzo) lockati. Eccessi/difetti si gestiscono con righe nuove o con SO di amendment.
4. **Modifica radicale post-confirm** -> solo via `SalesOrderAmendmentService::amend(SalesOrder)` che clona righe non evase + applica delta in nuovo SO con `amends_sales_order_id` valorizzato (audit trail completo).

#### Implementazione tecnica

- **Trait `[HasLocks](file:///srv/http/laraplate/Modules/Core/app/Locking/Traits/HasLocks.php)`** su `Quotation`, `QuotationItem`, `SalesOrder`, `SalesOrderLine` (colonne `locked_at` / `locked_user_id` da config `[Locked](file:///srv/http/laraplate/Modules/Core/app/Locking/Locked.php)`, lock effettivo = `locked_at` non null).
- **Orchestrazione applicativa**:
  - `SalesOrderObserver` su `confirming -> confirmed` -> in transazione, lock `Quotation` + lock SO header.
  - `DeliveryNoteObserver::created` / `InvoiceObserver::posted` -> chiama `SalesOrderEvasionService::registerDelivery(...)` / `::registerInvoice(...)` -> aggiorna `qty_delivered`/`qty_invoiced` + lock riga sull'`qty_ordered` + ricalcolo `SalesOrder.status` (partially_delivered / partially_invoiced / closed).
- **Trigger DB** (opzionali, hardening): `BEFORE UPDATE` / `BEFORE DELETE` su `quotations`, `quotation_items`, `sales_orders`, `sales_order_lines` che impediscono mutazioni quando il record padre ha `locked_at` NOT NULL. Coprono accessi raw, script, regressioni Eloquent.
- **Sblocco**: per i contabili (Q + SO) tipicamente **nessuno** o solo ruolo amministrativo via `config('core.locking.can_be_unlocked')` / `Locked::classesThatCanBeUnlocked`. La via maestra per modificare e' l'**amendment SO** (audit trail vs unlock manuale).

**Nota**: non e' prevista una **tabella pivot** dedicata tra Project/SalesOrder e righe preventivo: il lock sostituisce la pivot di congelamento. Il legame per voce resta `quotation_item_id` opzionale su `time_entries` / `tasks` dove serve.

### 4. Catalogo prezzi (`PriceList` + `**PriceListItem`**)

- **Modello**: contenitore `**price_lists`** (validità opzionale sul listino) + righe `**price_list_items`** (servizio offerto, prezzo per unità, UOM, validità per voce se serve); **`price_list_items.taxonomy_id`** (o equivalente) = **riferimento catalogo** condiviso con task/sessioni per “che tipo di lavoro è questa voce”. `quotation_item` deriva il tipo dalla voce listino; **snapshot opzionale** sulla riga preventivo se serve immutabilità dopo revisioni listino. Pivot tipo `catalog` / `catalog_service` solo se servono **più cataloghi** con stesso servizio a prezzi diversi (fase successiva).
- **UOM**: tabella o enum con conversione opzionale; regole tipo «8 h = 1 giorno» come dato/config, non hardcoded.

### 5. Movimenti finanziari (`Movement` → **LedgerEntry**)

- **Tenere**: direzione (entrata/uscita), importo totale, data di competenza, tipo classificatorio, allegato, **periodo** per uscite rateizzate/amortizzate (`period_from` / `period_to`), riparto tra attori (`MovementUser` → **EntryAllocation** con `user_id` o morph `participant`).
- **Generalizzare**: niente STI su tabella unica obbligatoria: in Laravel puoi usare **una tabella** + `direction` enum, oppure tabelle separate `inbound_entries` / `outbound_entries` se vuoi vincoli diversi; l’importante è un **unico servizio di registrazione** che valida tipo vs direzione (come `setType` in `MovementIN`/`OUT`).
- `**MovementCalculation`**: resta enum/config su come allocare importo tra partecipanti (equal/presence) — utile e generico.
- **N non nel core ERP**: `MovementOUT` → `Equipment` resta **estensione** (tabella `business_assets` nel verticale o `ledger_entry_id` su equipment).

### 6. Reportistica (viste `month_expenses`, `month_total`)

- **Tenere**: l’**idea** (aggregazioni per mese/categoria/durata), non SQL hardcoded su uno schema legacy fisso.
- **In Laraplate**: query builder / materialized reporting table / job notturno; in alternativa viste DB **nel modulo** con prefisso/convenzione tabelle del modulo ERP, generate da migration.

### 7. `Transfer` e `Payment` (riferimento legacy)

- `**Transfer`**: concetto di **giroconto interno** tra allocazioni — utile se in futuro gestisci split revenue; nel core ERP può essere `internal_transfer` tra due `EntryAllocation` collegate allo stesso o diverso `LedgerEntry`, oppure lasciato al verticale finché non serve.
- `**Payment`**: in schema DB esiste tabella `payment`; nel codice entity gran parte è commentata — in un ERP settore-neutro conviene `**LedgerEntry` di tipo "payment"** o tabella `payments` legata 1–1 a una riga di entrata, senza accoppiamento stretto a `MovementUser` finché non chiarisci il modello contabile.

---

## Cosa non mettere nel core ERP (o solo come plugin)

- **Equipment** e tassonomia type/category hardware.
- **Client** come entità nominata: sostituisci con **Party** generico o integrazione esterna.
- **Logica overflow ore vs righe offerta** nelle implementazioni legacy (mantieni come `**BillingStrategy`** configurabile).
- **Viste SQL** hardcoded su un solo schema database legacy.

---

## Struttura modulo `Modules/ERP` (stato + prossimi file)

- Migration principali: `create_customers_table`, `create_contacts_table`, `create_contactables_table`, `create_price_lists_table`, `create_price_list_items_table`, `create_quotations_table`, `create_quotation_items_table` (`quotations_items`), `create_projects_table`, `create_sites_table`, `create_tasks_table`, `create_time_entries_table`, stub `movements` / `balances`.
- Ordine consigliato **post-MVP**: movimenti / allocazioni / bilanci; resto Filament commerciale/CRM (`business-filament-final`); **slice contabile Filament già presente** (`business-filament-erp-core-slice`).
- `app/Contracts`: `BillingStrategyInterface` per ore vs preventivo; `BalanceCalculatorInterface` per snapshot + runtime.
- **Test**: somme allocazioni, congelamento; se presente ETL legacy, campioni di parità su totali noti (evitare bug tipo closure che non muta accumulatori).
- **Filament**: risorse **accounting-first** in admin (vedi `business-filament-erp-core-slice`); il resto del back-office ERP (`business-filament-final`, `erp-filament-resources`) dopo dominio stabilizzato.

---

## Piano di lavoro per fasi (dettaglio operativo)

### Fase P0 — `Place` + refactor `Location` (Core + Cms) **[completata]**

- Migration Core `places` + modello `Place` (fillable, cast coordinate se presenti).
- Migration Cms `locations.place_id` + backfill da colonne esistenti → `Place`, poi (fase successiva) rendere colonne geografiche su `locations` **virtuali** via trait o rimuoverle quando il trait copre tutto e i test passano.
- Trait **trasparente** su `Location` (vedi sezione Luoghi): allineamento a pattern `**HasTranslations`** / `**HasDynamicContents`** in `[HasTranslations.php](file:///srv/http/laraplate/Modules/Core/app/Helpers/HasTranslations.php)` e `[HasDynamicContents.php](file:///srv/http/laraplate/Modules/Core/app/Helpers/HasDynamicContents.php)` (priorità lettura: attributi nativi → relazione `place` → parent).
- Spostare / duplicare con deprecazione: **servizi geocoding** e **rotte** usate dall’app in **Core**; Cms mantiene resource Filament e relazioni Content ma chiama Core.
- Test regressione: geocode, salvataggio Location da Filament, Content ↔ locations, search index se ancora basato su attributi “flat” (aggiornare mapping se i campi diventano delegati).

### Fase P1 — `Taxonomy` + refactor `Category` (Core + Cms) **[completata]**

- Migration Core `taxonomies` + `**abstract class Taxonomy`** con `**protected $table = 'taxonomies';`**.
- Refactor `**Category extends Taxonomy`**: scope `cms.content` (o equivalente), trait Cms esistenti sulla sottoclasse; migrazione `categories` → `taxonomies` se si unifica lo storage; aggiornare pivot **Content**, Filament, search Typesense, regole validazione.
- Predisporre scope convenzionali `**erp.activity`** / `**erp.movement**` (o equivalente) per FK successive dal modulo ERP (`taxonomy_id` su Task, TimeEntry, movements).
- Test regressione: CRUD Category, contenuti categorizzati, query gerarchiche.

### Fase 0 — Confini dati

- Schema `projects`: **nessun** `lead_user_id` (decisione 2026). Contatti: **`contactables`** (M:N); niente `customer_id` su `contacts`. **`HasValidity`**: su **`PriceList`** (come da migration); altre entità con validità dove già presente in schema.

### Fase 1 — Riparazione schema attuale

- **Completato in repo**: `tasks.taxonomy_id` → `taxonomies`; `time_entries`; listino; vincoli preventivo/progetto; pivot contatti.

### Fase 2 — Projects + quotation items

- Popolare `projects` (customer, quote nullable, lead, descrizione, flags).
- `quotations`: **duplica + lineage**; trait `**HasLocks`** + colonne lock Core; `ProjectObserver` (o equivalente) che esegue `**lock()`** sul `Quotation` quando il progetto viene creato/aggiornato con `quotation_id`; **migration trigger** opzionali su `quotations` / `quotation_items` per enforcement DB.
- `quotation_items` + `**price_lists` / `price_list_items`**; campi **modalità economica per voce**; FK opzionale `price_list_item_id` su `quotation_items`.
- `time_entries`: `quotation_item_id` opzionale (legame consuntivo → voce); niente `project_quotation_items` nel perimetro minimo.

### Fase 3 — Tempo: Task + TimeEntry

- Task pianificati; TimeEntry consuntive con utenti multipli e casi senza task.

### Fase 4 — Movements + allocazioni + settlements

- Tipi/categorie movimento; IN verso contact/project; OUT con allocazioni user; movimenti rettifica soci.

### Fase 5 — Balances (snapshot + lock)

- Record `balances` per anno chiuso; `movements.locked_by_balance_id` (o equivalente); servizio che calcola totali runtime + snapshot.

### Fase 6 — ETL opzionale (es. database legacy)

- Mapping (solo se si importa da DB legacy): entità calendario → `Task` / `TimeEntry`; sessioni lavoro → `TimeEntry`; documenti offerta → `Quotation` / `quotation_items`; movimenti e riparti → `Movement` / `MovementAllocation`; compensazioni interne → pool / settlements (secondo schema sorgente).

### Fase finale — Filament (modulo ERP)

- **Regola di delivery (aggiornata)**: il back-office Filament **contabile** (tenant, PdC, prima nota bozza/view, periodi, numeratori, codici fiscali) è **già iniziato** (`business-filament-erp-core-slice`). Risorse **commerciali/CRM/magazzino/fatture** restano l’ultimo grosso blocco UI (`business-filament-final` / `erp-filament-resources`), dopo migrazioni e modelli allineati.
- **Motivo**: la UI resta consumatore del dominio; la slice contabile è utile per smoke test/admin senza bloccare il resto del modulo.
- **API / mobile**: da pianificare in parallelo o dopo l’ampliamento Filament commerciale, a seconda del primo consumatore reale (vedi todo `business-filament-final`).

---

## Analisi critica: siamo «al buono» con il piano?

**Sì, con riserva consapevole**: il perimetro (clienti/contatti, commessa con owner, **lock offerta** al bind, pianificazione + consuntivo, movimenti + riparti, cassa tipo Tricount, bilanci, pagamenti esterni) è **coerente** come **scaffolding** gestionale agnostico. Le **riserve** sono backlog (complessità, UX split, allineamento Core), non dipendenza da un vertical specifico:

1. **Complessità implementativa**: molte entità interconnesse; conviene delivery incrementale (MVP movimenti + progetti, poi time entry, poi pool Tricount, poi payment provider).
2. **Regola «chi partecipa allo split» per spesa**: richiede UX chiara (Tricount-style) e validazione somme.
3. **Coerenza `revision_number`**: calcolo in transazione alla creazione revisione; vincoli DB/app su `root_quote_id` + `previous_quote_id` per evitare cicli.
4. **Allineamento moduli**: `Quotation` oggi estende `Model` base; per `HasVersions` e soft delete Core allineare a `[Modules\Core\Overrides\Model](file:///srv/http/laraplate/Modules/Core/app/Overrides/Model.php)` ove previsto.

---

## Rischi e decisioni da prendere prima di codificare

- **Moneta e arrotondamenti**: usare **decimal** a livello DB e importi in minor unit o `bcmath` (evitare `float` in contabilità).
- **Soft delete / audit**: allineare a `[Modules\Core\Overrides\Model](file:///srv/http/laraplate/Modules/Core/app/Overrides/Model.php)` (o pattern già usato nei moduli) + versioning dove serve.
- **Filament / API**: **Filament ERP = fase finale** del modulo (vedi § Fase finale — Filament). Il primo consumatore (solo back-office vs anche API mobile) si decide in quella fase; non cambia il modello dati ma influenza ordine di lavoro e permessi.
- **`Quotation` / `QuotationItem`**: modello Core + `**HasLocks`** + `HasVersions` opz.; colonne **duplica + lineage**; `**ProjectObserver`** + **trigger DB** opz. per mutazioni quando locked; niente merge ad albero legacy.

---

## Esito atteso

Modulo **ERP** come **scaffolding** per applicazioni gestionali: **anagrafiche**, **commessa** (`Project`) con **offerta** opzionale (`Quotation`: **duplica + lineage**; **lock** su offerta e righe al bind commessa via `**HasLocks`** + observer + trigger DB opzionali), **owner interno**, **pianificazione** e **consuntivo** (`quotation_item_id` opzionale), **listino** (`price_lists` / `price_list_items`), **movimenti** e **pool soci** (modello Tricount), **bilanci**. Estensioni verticali e **ETL da DB legacy** restano **opzionali**; policy listino/overflow in `**BillingStrategy`**.

## Modello tipo Tricount (cassa soci) — bozza in piano

Ispirazione **Tricount / Splitwise**: ogni spesa ha **quote di riparto** per socio (uguali o importi espliciti); i saldi netti (chi deve a chi) derivano dalla somma algebrica. Estensione richiesta: una **cassa** da cui i soci **versano** e **prelevano** in proporzione (copertura spese + utile lavori).

**Decisioni confermate (questionario):**

- **Una cassa** a livello azienda / attività (`single_org`).
- **Saldo interno in v1**, ma con **predisposizione estensibile** a: provider di incasso (**PayPal**, **Satispay**), **richieste di pagamento** verso clienti, **richieste di saldo / versamento** verso soci (modello dati o interfacce senza integrare subito tutte le API).
- **Partecipanti al split**: scelta **per ogni spesa** del sottoinsieme di soci (`pick_each_time`).
- **Settle-up**: **suggerimento trasferimenti minimi** + possibilità di **registrare/confermare** i versamenti effettivi (`suggest_and_record`).
- **Utile lavori**: **solo movimenti espliciti** di distribuzione (`manual_movement`), non in automatico in v1.

**Predisposizione tecnica (v1 schema / contratti):**

- Tabella o enum `payment_rail` / `external_provider` nullable (`internal`, `paypal`, `satispay`, …).
- Entità leggera `PaymentRequest` (o `OutgoingPaymentIntent`): verso `Contact`/`Customer` (cliente) o verso `User` (socio), stato (`draft`, `sent`, `paid`, `cancelled`), importo, scadenza, `provider` nullable finché resta solo interno.
- `PoolTransaction` (versamento/prelievo cassa) con campo opzionale `payment_request_id` per tracciare che un movimento interno è stato saldato via provider esterno.

### Entità dati probabili (indipendenti dall’UI)

- `PartnerPool` o `Cassa` (nome TBD): saldo logico aggregato; movimenti collegati come sottotipo.
- `ExpenseSplit` / righe `movement_allocations`: `user_id`, `amount` o `weight` (se %), vincolo somma = totale spesa.
- `PoolTransaction`: versamento socio → cassa, prelievo cassa → socio (mirror contabile se serve).
- Servizio `SettlementSuggestionService` (opzionale): calcolo trasferimenti minimi tra coppie di soci da saldi netti.

### Fase 4bis (dopo risposte)

- Estendere Fase 4 con **una cassa** (`partner_pool` o equivalente), **split per spesa** con elenco soci scelto, righe importo (uguali = stesso importo calcolato o righe esplicite), **settle-up** (servizio calcolo minimo + registrazione trasferimenti), **PaymentRequest** stub per futuro PayPal/Satispay e richieste a clienti/soci.

### Domande residue (da chiarire in seguito, non bloccanti per il piano)

- **Arrotondamenti** quando si divide in parti uguali tra N soci (centesimo avanzante: chi lo assorbe?).
- **Chi autorizza** un prelievo dalla cassa o la conferma di un settle-up (solo admin / tutti i soci coinvolti).
- **Spesa pagata da un solo socio** (anticipo): crea credito verso cassa o verso altri? (tipicamente credito del pagatore fino a settle-up).
- **Valuta unica** in v1 o già campo `currency` per righe future.
- **Storno / modifica** di una spesa già inclusa in un periodo “congelato” o collegata a settle-up registrato.

---

## Decisione referente (confermata in conversazione precedente)

- **Capo progetto**: **non** modellato con `lead_user_id` su `Project` (decisione 2026: campo eliminato dal perimetro).
- **Contatto “primario”**: **non** in v1 — concetto rimosso dal piano.
- **Contatto ↔ cliente (v1)**: pivot **`contactables`** (M:N); **nessun** `customer_id` su `contacts` — stesso record `Contact` collegabile a più `Customer`.
- **Customer v1**: anagrafica **senza** perimetro contabile/fiscale “ufficiale” (niente fatture/registrazioni nel modulo in questa fase).
- **Fornitore / ruoli anagrafici**: **posticipato** (impatta movimenti / `Party`, da affrontare con il dominio cassa).

---

## Registro decisioni e posticipi (conversazioni 2026-04)

**Stato milestone**: **P0** e **P1** completati; **anagrafica** aggiornata (pivot `contactables`). **`fix-task-activity-migrations` completato** (listino, preventivo, tasks/tassonomia, `time_entries`, vincoli `customer_id`/`currency` su `quotations`, ecc.). **`business-models-rules-casts-relations` completato** (`getRules()` su Project, Task, TimeEntry, PriceList, PriceListItem, QuotationItem; `Movement` su `Core\\Overrides\\Model` con tabella stub). **Obiettivo corrente**: **`time-domain`** (sovrapposizione applicativa + eventuali scope aggregati), poi chiusura **`mvp-precontabilita-v1`** (seed dev tassonomie operativo + prova `migrate` su DB pulito).

**Implementazione recente (repo, tracciamento)** — schema + modelli MVP pre-contabilità: file migration `191728`–`191729` (listino), `191747` (`time_entries`), aggiornamenti `quotations`/`projects`/`tasks`/`quotations_items`, pivot `contactables` rinominata; modelli `Quotation`, `Project`, `Task`, `TimeEntry`, `PriceList`, `PriceListItem`, `QuotationItem`, `Contact`/`Customer` (M:N), `Activity` (inverse `tasks`/`time_entries`/`price_list_items`); `getRules()` create/update sui modelli MVP elencati; seeder dev stub `DevERPTaxonomySeeder`.

**Definizioni entità**: sezione **[Definizioni entità ERP](#definizioni-entità-erp)** con scopo, anti-pattern, relazioni e gap rispetto al repo (`Task`/`activities`, `Project` senza `lead_user_id`, ecc.).

**Piano unico (fonte di verità)**: per Nebula→ERP usare **solo questo file** (`.cursor/plans/nebula_verso_business_0d6eb0ed.plan.md`). **Non creare piani `.plan.md` paralleli** (né lasciare bozze generate da tool come sostituto): ogni iterazione va **fusa qui** — aggiornare frontmatter `todos` e, se serve, questa sezione Registro.

**Nomenclatura righe offerta (parità listino)**: come `PriceList` → `PriceListItem`, il documento **`Quotation`** ha righe **`QuotationItem`** (tabella target consigliata **`quotation_items`**, FK `quotation_item_id`). Evitare `QuoteLine` / `quote_lines` nei nuovi artefatti. Se in DB la tabella ha altro nome (es. `quotations_items`), **allineare** migration o `protected $table` sul modello — una sola fonte di verità.

**Migration ERP — refactor file separati (2026)**: `quotations` e righe preventivo in **due migration** (`create_quotations_table`, `create_quotation_items_table`) per **rollback** e dipendenze più chiare; pivot **`contactables`** in migration dedicata. **Convenzione piano**: va bene **un file per entità** *oppure* **pivot/figlia nello stesso file della seconda tabella** purché l’**ordine globale** dei filename rispetti tutte le FK; file multipli non sono obbligatori ma sono **accettabili** quando migliorano `down()` e review.

**Processo**: se un tool propone un nuovo file piano, **ignorarlo o incorporarlo** in questo documento; il tracking operativo resta nei **todo YAML** sotto. File duplicato `business_schema_dominio_acf1d12b.plan.md` **eliminato** dopo merge nel piano Nebula.

**Ordine modulo ERP**: **Filament contabile (admin)** come slice anticipata (`business-filament-erp-core-slice` **completed**); **Filament commerciale MVP** Customer/Contact/Quotation/Project (`erp-filament-mvp-commercial-slice` **completed**); **CRM/magazzino/fatture UI complete** restano in (`business-filament-final`, `erp-filament-resources`).

Decisioni **confermate**:

- **`Modules\ERP\Casts\EntityType`**: enum per **discriminare gli alberi** in `taxonomies` (es. `MOVEMENTS`, `ACTIVITIES`), analogamente al ruolo di `EntityType` in Cms — **non** elenca foglie tipo “imbiancatura” / “programmazione”.
- **Nessun enum `ActivityType`** nel modulo ERP: i tipi attività dipendono dalla verticale; restano **nodi di tassonomia** (dati/seed/admin).
- **`Modules\ERP\Casts\MovementType`** (`income` / `expense`): riservato alla parte **cassa / contabile leggera**, non al catalogo attività.
- **`TimeEntry`**: **esattamente un’activity** tramite **`taxonomy_id`** (`belongsTo` tassonomia attività, **senza pivot**); **`quotation_item_id`** opzionale per contesto/confronto con offerta; sessioni senza task/preventivo coperte dal solo nodo tassonomia sulla sessione.
- **`PriceListItem`**: **fonte** del riferimento catalogo per la voce listino; `QuotationItem` → via `price_list_item_id` (eventuale **snapshot** sulla riga se serve immutabilità).
- **Gerarchia movimenti “categoria/tipo”**: su **`taxonomies`** con scope/EntityType movimenti — **no** tabella dedicata `MovementCategory` nel modello target.
- **Anagrafica (v1)**: **nessun contatto primario**; legame cliente↔contatto via **`contactables`** (M:N); **Customer** senza dati fiscali estesi in v1. **`HasValidity`** su **`PriceList`** (v1). **Preventivo**: `quotations.customer_id` obbl.; **listino**: valuta sul contenitore, importi **15,4**; **TimeEntry**: `started_at`/`ended_at`, più righe per utente (pause), sovrapposizione solo app.

**Posticipato** (da affrontare dopo il livello organizzativo: progetti, tempo, listino, preventivo, tassonomie):

- Discorso completo **movimenti in ingresso/uscita**, riparti, controlli e somme oltre l’enum di direzione.
- Uso operativo pieno di `MovementType` e schema `movements` in coerenza con le regole sopra.

### Decisioni Roadmap ERP completo (consolidate 2026-04)

> Conversazione 2026-04: il MVP pre-contabilita' resta condizione d'ingresso, ma il modulo ERP mira a un **ERP completo** (italian-first ma pluggable, no e-invoicing concreto nel modulo, predisposizione multi-company + multi-currency da subito). Decisioni puntuali consolidate:

- **Visione ERP**: modulo ERP punta a coprire CRM (Lead/Opportunity), Quotation, **SalesOrder** intermedio, Project (commessa), DeliveryNote (DDT), Invoice (sale + purchase), Magazzino full-costing FIFO+media, Ciclo passivo (PO/GR/Invoice purchase), Contabilita' italiana (CoA, Journal, Periodi fiscali, IVA + ritenute, Numerazione progressiva). Vedi sezione **Roadmap ERP completo (post-MVP)** + todo dedicati nel frontmatter.
- **`SalesOrder` introdotto come intermediario** tra `Quotation` e `Project` (M3.2). Cardinalita' definitiva: 1 Quotation -> N SalesOrder; 1 SalesOrder -> N Project; N SalesOrder -> 1 Project (un progetto puo' aggregare piu' ordini).
- **Lock-chain SO progressivo** (sostituisce il vecchio "lock al `Project::created`"): confirm SO -> `Quotation` lockata + header SO lockato; prima `DeliveryNote` / prima `Invoice` su una riga -> riga lockata su `qty_ordered`. Modifiche radicali via `SalesOrderAmendmentService::amend(...)` con `amends_sales_order_id` (audit trail). Vedi paragrafo **3quater. Lock preventivo / lock SO** aggiornato.
- **Multi-company predisposto da subito** (M0-ERP): tabella `companies` + trait `BelongsToCompany` + global scope `BelongsToCompanyScope` automatico su tutte le entita' transazionali. In v1 1 sola company `default`, ma il vincolo strutturale c'e'.
- **Multi-currency predisposto da subito** (M0-ERP): helper `ERPMigrateUtils::moneyColumns($table, 'amount')` -> ogni amount ha `amount_doc` + `currency_doc` + `amount_local` + `fx_rate` (decimal 18,8). `amount_local` e' la base per la partita doppia. `CurrencyConverter` no-op in M0 (EUR/EUR fx=1.0). Tabella tassi + provider live rinviati.
- **Versioning forzato sui modelli contabili**: niente lock sul record `settings` (Core non sa del modulo ERP), niente seeder custom. Si dichiara `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;` direttamente sulla classe del modello contabile (Account, JournalEntry, JournalEntryLine, Invoice/InvoiceLine, FiscalYear, FiscalPeriod, DocumentSequence, valutare anche StockMovement/StockCostLayer per audit). Il branch `property_exists` in [HasVersions::getVersionStrategy()](file:///srv/http/laraplate/Modules/Core/app/Helpers/HasVersions.php) garantisce priorita' assoluta della property sul `Setting`. Nessuna modifica a Core. Idem per `softDeletesEnabled`. Possibile follow-up Filament (non bloccante): nascondere/disabilitare i record Setting di versioning relativi a modelli che hanno la property dichiarata.
- **Tassonomia movimenti assorbita dal Chart of Accounts**: rimosso `EntityType::MOVEMENTS`. Ogni `JournalEntryLine` ha `account_id`; tag analitici extra restano colonne (`project_id`, `site_id`). Conservato `EntityType::ACTIVITIES`. Aggiunto `EntityType::OPPORTUNITY_STAGES` per la pipeline CRM (M3.1).
- **`document_sequences`** con chiave composita `(company_id, document_type, fiscal_year)`: lock pessimistico DB (`lockForUpdate`) per i tipi fiscali (`invoice_sale`/`invoice_purchase`/`tax_credit_note`) -> 0 buchi sotto contesa. Per i tipi non fiscali (`quotation`/`sales_order`/`purchase_order`/`delivery_note`/`goods_receipt`) flag `gap_allowed=true` (gap accettati su rollback). Servizio `DocumentNumberAllocator::next(Company, DocumentType, fiscalYear?)`. Test concorrenza obbligatorio (50 process paralleli su `invoice_sale` -> 50 numeri sequenziali univoci).
- **Snapshot fiscale immutabile**: aliquote, codici e label IVA/ritenute denormalizzati al posting su `invoice_lines` e `journal_entry_lines`. Cambio aliquota = nuovo `tax_code` + `replaced_by_tax_code_id` sul vecchio (mai UPDATE retroattivo). Strategy `TaxLineCalculator` interroga solo `tax_codes` attivi alla data di posting.
- **Refactor cassa Tricount come adapter contabile** (M2): `Movement` / `PartnerPool` / `PoolTransaction` ridotti a wrapper che generano `JournalEntry` via `JournalPostingService`. Eliminata la logica saldo parallela; il saldo cassa torna a essere derivato dal libro giornale. UX/Filament restano. Per ogni Movement esistente, generata entry equivalente; flag `posted_journal_entry_id` su Movement.
- **Ciclo passivo completo** (M3.6): `PurchaseOrder`, `GoodsReceipt`, `Invoice direction=purchase`, riconciliazione 3-way PO/GR/Invoice. **Magazzino integrato** (`StockMovementService`) per i carichi GR. Anagrafica unica `parties` con colonna `roles` (json: customer/supplier/both); rename in-place `customers` -> `parties` + rinomina FK `customer_id` -> `party_id` su tutti i documenti.
- **Inventory full costing in v1**: FIFO + media ponderata (scelta per articolo via `costing_method`). Tabelle `items`, `warehouses`, `stock_levels` (con `weighted_avg_cost`), `stock_movements`, `stock_cost_layers`. `StockMovementService` come unico entry-point: in carico apre layer + aggiorna media; in scarico consuma FIFO o legge media; calcola `unit_cost` per il COGS del journal posting.
- **E-invoicing**: solo contratti `EInvoiceProvider` + DTO neutri nel modulo (M3.5). Provider concreti SDI/Peppol restano package separati / verticali. Tabella `e_invoice_submissions` per tracciamento.
- **Migration in-place fino al build**: durante la **fase di creazione** del modulo ERP, le migration di `Modules/ERP/database/migrations/*` possono essere modificate **in-place** invece di creare ALTER (DB ricreato da zero ad ogni passaggio). **Eccezione**: rename `customers` -> `parties` deve avvenire prima del passaggio a build. Le migration di `Modules/Core/*` restano **immutate**.
- **ETL legacy riscritto** in chiave ERP: `Movement` legacy -> `JournalEntry` via `JournalPostingService`; eventuali movimenti magazzino legacy -> `stock_movements` via `StockMovementService`. ETL gira **dopo M2** (contabilita' base) e **dopo M3.3** se include flussi magazzino.
- **Test plan dedicato** (`accounting-test-plan`, trasversale M1-M3): partita doppia bilanciata su `amount_local`, snapshot fiscale immutabile, concorrenza numerazione, scope multi-company, versioning forzato, lock-chain SO, FIFO/avg, currency converter no-op. Suite di golden master che funge da regression baseline per i PR successivi.

---

## Roadmap ERP completo (post-MVP)

> Stato: **roadmap aspirazionale** consolidata 2026-04. L'MVP pre-contabilita' resta condizione di ingresso (chiuse `mvp-precontabilita-v1` + `time-domain`); tutte le fasi M0-ERP -> M4 si lavorano dopo, **in ordine** ma con possibilita' di spezzare un milestone in piu' PR. Vedi i todo ID `erp-vision-roadmap` -> `accounting-test-plan` nel frontmatter.

### Principi di vision

- **Italian-first ma pluggable**: il default e' italiano (Chart of Accounts, IVA, ritenute, formati documento) ma ogni regola fiscalmente specifica passa da un'interfaccia (`ChartOfAccountsProvider`, `TaxLineCalculator`, `EInvoiceProvider`, `DocumentNumberAllocator`). Una giurisdizione diversa = nuova implementazione, **mai** fork del modulo.
- **No fatturazione elettronica nel modulo**: solo i contratti (`EInvoiceProvider` + DTO) restano nel modulo ERP; ogni provider concreto (SDI italiano, Peppol, ecc.) e' package separato/verticale. Test sul modulo coprono solo che il payload neutro sia generato correttamente.
- **Accounting-first phasing**: prima si costruisce la spina dorsale contabile (CoA -> Journal -> Periodi fiscali -> Sequenze documenti), poi i flussi commerciali (CRM, Quotation, SO, DDT, Invoice) e il magazzino (M3.3) **prima** di DDT (M3.4) e Goods Receipt (M3.6). Ogni documento gestionale **deve** poter postare contro il libro giornale.
- **Predisposizione multi-tenant + multi-currency da subito**: aggiungere `company_id` + dual-currency dopo e' costoso e rischioso. Si fa una volta, oggi resta inerte (1 company default, 1 currency = EUR, fx_rate=1.0).
- **Versioning forzato sui modelli contabili**: la verita' contabile non e' negoziabile via `Setting`. La property a livello modello vince sempre.
- **Snapshot fiscale immutabile**: aliquote, codici e label IVA/ritenute sono **denormalizzati** sulle righe documento al posting; cambi rate aliquota = nuovo `tax_code` (no UPDATE retroattivo).
- **Numerazione progressiva**: sequenziale e robusta sotto contesa per i tipi fiscali (lock pessimistico, 0 buchi); piu' tollerante per quelli non fiscali (`gap_allowed=true`).
- **Una sola anagrafica `parties`**: customer + supplier + dual role tramite flag booleani `is_customer` + `is_supplier` (decisione D1, 2026-05-07). Le FK applicative (`party_id`) sono unificate, gli scope di lettura derivano dai flag.
- **Tricount/cassa esistente non viene buttata**: viene **rifondata** come adapter contabile sopra `JournalPostingService` (M2). UX/Filament restano; il saldo cassa torna a essere derivato.

### Regola operativa: migration **in-place** fino al build

Siamo nella **fase di creazione** del modulo **ERP** (`Modules/ERP`). Finche' non si entra in `build` definitivo, le migration del modulo possono essere modificate **in-place** invece di creare nuove migration `ALTER`: il database verra' rigenerato da zero (`migrate:fresh` + seeder) ad ogni passaggio, e questo riduce drammaticamente il rumore in code review e mantiene un solo file per concetto.

- **Ambito**: solo `Modules/ERP/database/migrations/*`. Le migration di `Modules/Core/*` restano **immutate**: Core non sa dell'esistenza del modulo ERP, e modificare migration Core retroattivamente romperebbe ambienti gia' in build.
- **Eccezione su rename `customers` -> `parties`**: rinomina della migration originale + ridenominazione colonne FK su tutte le altre migration ERP che le referenziano, **prima** di entrare in build. Test di migrate-fresh + seeder dev su DB pulito a ogni rinomina.
- **Quando si entra in build**: la regola si chiude. Da quel punto in poi, ogni cambio di schema = nuova migration `ALTER` che si somma in coda.

### Fase M0-ERP — Multi-tenancy + scaffolding cross-cutting

Todo: `multi-tenancy-foundations`, `enforce-versioning-on-accounting-models`.

- Tabella `companies` + 1 row `default` con `functional_currency='EUR'`. Trait `BelongsToCompany` + global scope automatico `BelongsToCompanyScope` su tutte le entita' transazionali (esistenti via in-place migration: `quotations`, `projects`, `tasks`, `time_entries`, `price_lists`, `price_list_items`, `quotations_items`; future: `journal_entries`, `invoices`, `sales_orders`, `delivery_notes`, `tax_codes`, `document_sequences`, `items`, `warehouses`, `stock_movements`, `parties`).
- Helper `ERPMigrateUtils::moneyColumns($table, 'amount')` per generare `amount_doc` + `currency_doc` + `amount_local` + `fx_rate` insieme. Anche se in M0 ogni amount viene salvato con `currency_doc='EUR'` e `fx_rate=1.0`, la colonna esiste e i servizi futuri non dovranno fare retrofit.
- `CurrencyConverter` come facade no-op in M0 (`amount_local = amount_doc * fx_rate` con `fx_rate=1.0` su EUR/EUR). Lo abilitiamo davvero quando arriva un cliente con valuta diversa: aggiunta tabella `fx_rates` + provider live.
- Property `protected VersionStrategy $versionStrategy = VersionStrategy::DIFF;` direttamente sulla classe di ogni modello contabile (Account, JournalEntry, JournalEntryLine, Invoice/InvoiceLine, FiscalYear, FiscalPeriod, DocumentSequence, valutare anche StockMovement/StockCostLayer per audit). Idem `protected bool $softDeletesEnabled = true;` dove serve. **Nessuna modifica a Core**, nessun seeder custom: il branch `property_exists` in [HasVersions::getVersionStrategy()](file:///srv/http/laraplate/Modules/Core/app/Helpers/HasVersions.php) garantisce la priorita' assoluta della property sul record `settings.version_strategy_{table}` (eventualmente popolato in modo inerte da `defaultSettings()` del Core seeder).

### Fase M1 — Accounting backbone

Todo: `accounting-coa`, `accounting-journal`, `accounting-fiscal-periods`, `document-sequences`.

- **Chart of Accounts** (`accounts`) con default italiano via `ItalianCoaProvider` (interfaccia `ChartOfAccountsProvider`). Codici PDC con possibilita' di mappatura `civilistico`. CoA assorbe la **tassonomia movimenti**: ogni `JournalEntryLine` ha `account_id`; tag analitici extra (`project_id`, `site_id`) restano colonne dedicate. `EntityType::MOVEMENTS` viene rimosso.
- **Partita doppia** (`journal_entries` + `journal_entry_lines`) bilanciata sull'`amount_local` (la moneta funzionale company), con check applicativo + DB. Servizio `JournalPostingService::post(...)` come unico entry-point: niente UPDATE/DELETE diretto post-posting, reverse esplicito che crea entry di storno collegato. Tutti i flussi contabili (Invoice, cassa Tricount refattorizzata, eventuali rivalutazioni FX future) **devono** passare da qui.
- **Periodi fiscali** (`fiscal_years` + `fiscal_periods`) con `status` open/closing/closed. Chiusura periodo blocca posting su entry con `posted_at` nel range. Lo stub `balances` viene refattorizzato come snapshot legato a `fiscal_periods`.
- **Sequenze documenti** (`document_sequences`) con chiave composita `(company_id, document_type, fiscal_year)`. Servizio `DocumentNumberAllocator::next(Company, DocumentType, fiscalYear?)` con lock pessimistico DB per i tipi fiscali (`invoice_sale`/`invoice_purchase`/`tax_credit_note`) -> 0 buchi anche sotto contesa. Per i tipi non fiscali (`quotation`/`sales_order`/`purchase_order`/`delivery_note`/`goods_receipt`) flag `gap_allowed=true`: numero allocato in transazione 'best-effort' (gap accettati su rollback).

### Fase M2 — IVA + ritenute + refactor cassa

Todo: `accounting-vat-withholdings`, `accounting-refactor-cash-tricount`.

- **`tax_codes`** immutabili (codice + rate + label + kind ∈ {vat, withholding} + country + effective_from + replaced_by_tax_code_id). Cambio aliquota (es. IVA 22% -> 24% nel 2027) = nuovo row + replaced_by sul vecchio + disattivazione del vecchio. Le righe storiche conservano lo snapshot dell'aliquota originale anche dopo il replace.
- **Strategy `TaxLineCalculator`**: classe responsabile del calcolo imponibile/imposta/totale per riga, interroga solo `tax_codes` attivi alla data di posting. Pluggable per giurisdizione.
- **Snapshot fiscale denormalizzato** su `invoice_lines` e `journal_entry_lines`: `tax_code` (string), `tax_rate` (decimal), `tax_label` (string) congelati al posting. Cambi futuri sul `tax_codes` non toccano il passato.
- **Refactor cassa Tricount** (`Movement` / `PartnerPool` / `PoolTransaction`): da modello con saldo parallelo a **adapter contabili** che generano `JournalEntry` via `JournalPostingService`. UX Filament/Livewire resta; il saldo torna ad essere derivato dal libro giornale. Migrazione dati: per ogni Movement esistente, generare entry equivalente; flag `posted_journal_entry_id` su Movement.

### Fase M3 — Cicli commerciali (vendita, magazzino, acquisto)

Todo: `crm-leads-opportunities` (M3.1), `sales-order` (M3.2), `inventory-erp-base` (M3.3), `sales-delivery` (M3.4), `sales-invoice-document` (M3.5; **contratto/tab tracciamento e-invoice già in modulo** → todo `einvoice-interface` **completed**), `purchasing-cycle` (M3.6).

#### M3.1 — CRM (Lead + Opportunity)

- `leads` (party_id?, source, status, owner_user_id, company_id, primi contatti) + `opportunities` (lead_id?, party_id obbl., `stage_taxonomy_id` con `EntityType::OPPORTUNITY_STAGES`, `expected_close_date`, importi dual-currency, probability, won/lost tracking).
- Conversione `Opportunity -> Quotation` con lineage: `quotations.opportunity_id` opt. + observer che chiude opportunity al win.

#### M3.2 — SalesOrder + lock-chain progressivo

`Quotation` accettata -> `SalesOrder`. Cardinalita' definitiva:

- 1 `Quotation` -> N `SalesOrder` (cliente puo' confermare in piu' tranche da una stessa offerta);
- 1 `SalesOrder` -> N `Project` (un ordine puo' generare piu' commesse interne);
- N `SalesOrder` -> 1 `Project` (piu' ordini possono confluire nella stessa commessa).

`SalesOrder.status` ∈ {draft, confirmed, partially_delivered, partially_invoiced, closed, cancelled, amended} + colonna `amends_sales_order_id` (self-FK). `SalesOrderLine.status` ∈ {open, partially_evased, fully_evased, cancelled} con colonne `qty_ordered`/`qty_delivered`/`qty_invoiced`.

**Lock-chain progressivo**:

1. **Confirm SO** -> `Quotation` lockata (sostituisce il lock alla creazione `Project`: aggiornare `ProjectObserver`/trigger).
2. **Confirm SO** -> header SO lockato (cliente, condizioni, totali immutabili).
3. **Prima delivery o prima invoice** su una riga -> riga lockata su `qty_ordered`; campi anagrafici riga (item, descrizione, prezzo) lockati anche loro. Quantita' in eccesso/difetto si gestiscono o con righe nuove o con SO di amendment.
4. **Modifica radicale post-confirm**: solo via `SalesOrderAmendmentService::amend(SalesOrder)` che clona righe non evase + applica delta in nuovo SO con `amends_sales_order_id` valorizzato (audit trail completo).

`SalesOrderEvasionService::registerDelivery(SalesOrder, lines, qty)` e `::registerInvoice(...)` chiamati dagli observer di `DeliveryNote::created` e `Invoice::posted`; aggiornano qty di riga, ricalcolano lo `status` SO.

#### M3.3 — Magazzino (PRIMA di DDT/GR)

Costing in v1: **FIFO + media ponderata** (scelta per articolo). Tabelle: `items`, `warehouses`, `stock_levels` (qty + `weighted_avg_cost`), `stock_movements` (direction in/out/transfer + source morph), `stock_cost_layers` (FIFO).

`StockMovementService` come unico entry-point:

- in carico (GR / inventario iniziale / rettifica positiva): apre layer FIFO + aggiorna media ponderata;
- in scarico (DDT vendita / consumo / rettifica negativa): consuma layer FIFO o legge media a seconda del `costing_method` dell'item; calcola `unit_cost` da passare al `JournalPostingService` per il COGS.

Cambio `costing_method` su item con stock esistente: vietato (test esplicito).

#### M3.4 — Delivery Notes (DDT vendita)

`delivery_notes` da SO + `delivery_note_lines` (sales_order_line_id, qty). Observer su `DeliveryNote::created` -> `StockMovementService::issue(...)` -> `SalesOrderEvasionService::registerDelivery(...)`. Il `unit_cost` valorizzato dallo scarico sara' usato dal `JournalPostingService` quando la Invoice viene postata (COGS).

#### M3.5 — Invoice + e-invoice interface

`invoices` (header) + `invoice_lines` con `direction` ∈ {sale, purchase}. Posting Invoice -> `JournalEntry` automatico via `JournalPostingService`; righe con `tax_code_id` + snapshot fiscale al posting. Numerazione progressiva via `DocumentNumberAllocator` (`gap_allowed=false`). Vincolo a `delivery_notes` opt. (fattura accompagnatoria/differita).

E-invoicing nel modulo: solo `EInvoiceProvider` (interfaccia) + DTO neutri + tabella `e_invoice_submissions` (invoice_id, provider_code, external_id, status, last_payload_path, submitted_at, response_payload). Provider concreti SDI/Peppol restano package separati.

#### M3.6 — Ciclo passivo + anagrafica unificata `parties`

- **Rename `customers` -> `parties`** in-place (siamo ancora in fase di creazione modulo). Due flag booleani `is_customer` (default true) + `is_supplier` (default false) al posto del JSON `roles` (decisione D1, 2026-05-07): indicizzabili, queryabili senza JSON parsing, set di ruoli chiuso. `CHECK (is_customer OR is_supplier)`. Modello PHP `Party` con scope `scopeCustomers()`/`scopeSuppliers()`. Rinomina FK `customer_id` -> `party_id` su `quotations`, `projects`, `invoices`, `sales_orders`, `delivery_notes`, `purchase_orders`, `leads`, `opportunities`, `contactables`.
- `purchase_orders` (header + lines, party.role=supplier).
- `goods_receipts` (bolla di ingresso): observer -> `StockMovementService::receive(...)` -> aggiorna stock + costo.
- `invoices` direction=`purchase`: collegabile a uno o piu' GR; postaggio journal automatico.
- **Riconciliazione tre-vie PO/GR/Invoice**: validazione coerenza prezzi/quantita' al posting con scarti tracciati.

### Fase M4 — Policies + Filament + Reporting

Todo: `erp-policies-permissions`, `erp-filament-resources`, `erp-reporting-stub`.

- **Policies**: chiusura/riapertura periodo, posting/unposting journal, generazione/annullamento fatture, sblocco quotations/SO, switch company, modifica `tax_codes` (admin-only), gestione `document_sequences`.
- **Filament**: risorse per Companies, CoA, JournalEntries (read-only post-posting), FiscalPeriods, DocumentSequences, Leads, Opportunities, SalesOrders, DeliveryNotes, Invoices (sale + purchase), Parties (filtri per role), PurchaseOrders, GoodsReceipts, Items, Warehouses, StockLevels (read-only). Pagine BI minime: bilancio, registro IVA, sales pipeline funnel.
- **Reporting**: servizi come query/jobs (no BI completa) — `BalanceSheetService`, `IncomeStatementService`, `VatLedgerService`, `SalesPipelineService`, `StockValuationService`. Output strutturato JSON/array, pagine Filament per consumarli, export CSV finanziario implementato; PDF e BI completa restano fuori scope.

### Fase trasversale — Test plan dedicato

Todo: `accounting-test-plan`. Vedi descrizione dettagliata nel todo: invarianti partita doppia, snapshot fiscale, concorrenza numerazione, scope multi-company, versioning forzato, lock-chain SO, FIFO/avg, currency converter no-op.

### Diagramma — Flusso documenti -> Journal (M3.1-M5.3)

**Note (aggiornato 2026-05)**: il flusso include ora i blocchi M5.1 (scadenzario+pagamenti), M5.2 (note credito/debito), M5.3 (registri IVA + liquidazione), M3.6 (3-way match PO/GR/Invoice tramite `ThreeWayMatchService` con tolerance e `match_status` su `InvoiceLine`), e l'interfaccia e-invoice (`EInvoiceProvider` + `EInvoiceSubmission`).

```mermaid
flowchart TB
  subgraph crm [M3.1 CRM]
    Lead
    Opportunity
  end
  subgraph sales [M3.2-3.5 Vendita]
    Quotation
    SalesOrder
    SalesOrderLine
    DeliveryNote
    InvoiceSale["Invoice direction=sale"]
    CreditNote["Invoice invoice_type=credit_note"]
  end
  subgraph inventory [M3.3 Magazzino]
    StockMovementOut["StockMovement direction=out"]
    StockMovementIn["StockMovement direction=in"]
    StockCostLayer
  end
  subgraph purchasing [M3.6 Ciclo passivo]
    PurchaseOrder
    GoodsReceipt
    InvoicePurchase["Invoice direction=purchase"]
    ThreeWay[ThreeWayMatchService]
  end
  subgraph payments [M5.1 Scadenzario + pagamenti]
    PaymentTerm
    PaymentScheduleLine
    Payment
    PaymentAllocation
  end
  subgraph vat [M5.3 Registri IVA]
    VatRegisterEntry
    VatSettlement
  end
  subgraph accounting [M1-M2 Backbone contabile]
    JournalEntry
    Account[Chart of Accounts]
    TaxCode
    DocumentSequence
  end
  subgraph einvoice [E-invoice interface]
    EInvoiceSubmission
  end

  Lead --> Opportunity
  Opportunity -->|"conversione"| Quotation
  Quotation -->|"confirm SO -> Q lockata"| SalesOrder
  SalesOrder --> SalesOrderLine
  SalesOrderLine -->|"delivery -> qty_delivered++"| DeliveryNote
  DeliveryNote -->|"StockMovementService"| StockMovementOut
  StockMovementOut -.->|"consuma FIFO"| StockCostLayer
  SalesOrderLine -->|"invoice -> qty_invoiced++"| InvoiceSale
  DeliveryNote -.->|"fattura differita pivot"| InvoiceSale
  InvoiceSale -->|"posting"| JournalEntry
  StockMovementOut -.->|"COGS unit_cost"| JournalEntry
  InvoiceSale -.->|"CreditNoteService"| CreditNote
  CreditNote -->|"posting reverse"| JournalEntry

  PurchaseOrder --> GoodsReceipt
  GoodsReceipt -->|"StockMovementService"| StockMovementIn
  StockMovementIn -.->|"apre layer / aggiorna media"| StockCostLayer
  PurchaseOrder --> ThreeWay
  GoodsReceipt --> ThreeWay
  ThreeWay -->|"match_status su InvoiceLine"| InvoicePurchase
  InvoicePurchase -->|"posting"| JournalEntry

  InvoiceSale -->|"PaymentScheduleGeneratorService"| PaymentScheduleLine
  PaymentTerm -.->|"rate_lines"| PaymentScheduleLine
  Payment -->|"PaymentAllocationService"| PaymentAllocation
  PaymentAllocation -->|"updates status"| PaymentScheduleLine
  CreditNote -.->|"riduce scadenze aperte"| PaymentScheduleLine

  InvoiceSale -->|"VatRegisterService"| VatRegisterEntry
  InvoicePurchase -->|"VatRegisterService"| VatRegisterEntry
  VatRegisterEntry --> VatSettlement
  TaxCode --> VatRegisterEntry

  InvoiceSale -.->|"submit"| EInvoiceSubmission

  JournalEntry --> Account
  JournalEntry --> TaxCode
  InvoiceSale -.->|"next number"| DocumentSequence
  InvoicePurchase -.->|"next number"| DocumentSequence
  CreditNote -.->|"next number sezionale NC"| DocumentSequence
```

### Diagramma — Multi-company + versioning forzato lato modello

**Nota (aggiornato 2026-05)**: il subgraph `models` riflette ora anche i modelli M5 (`Payment`, `PaymentScheduleLine`, `PaymentAllocation`, `PaymentTerm`, `VatRegisterEntry`, `VatSettlement`) e l'interfaccia e-invoice (`EInvoiceSubmission`), tutti soggetti a `BelongsToCompany` e versionati.

```mermaid
flowchart TB
  subgraph models [Modelli contabili ERP]
    Account
    JournalEntry
    JournalEntryLine
    Invoice
    InvoiceLine
    FiscalYear
    FiscalPeriod
    DocumentSequence
    TaxCode
    Payment
    PaymentScheduleLine
    PaymentAllocation
    PaymentTerm
    VatRegisterEntry
    VatSettlement
    EInvoiceSubmission
  end
  subgraph traits [Trait/scope cross-cutting]
    BelongsToCompanyScope
    HasVersions
  end
  subgraph core [Core inalterato]
    SettingsTable["settings table: version_strategy_*, soft_deletes_*"]
    HasVersionsCore[HasVersions trait]
    CompaniesTable[companies table]
  end

  models -->|"global scope automatico"| BelongsToCompanyScope
  BelongsToCompanyScope -->|"company_id = current_company_id"| CompaniesTable
  models -->|"use trait"| HasVersions
  HasVersions -.->|"getVersionStrategy resolution"| HasVersionsCore
  HasVersionsCore -->|"1 - legge property modello"| ModelProperty["protected VersionStrategy versionStrategy = DIFF"]
  HasVersionsCore -.->|"2 - fallback"| SettingsTable
  ModelProperty -->|"priorita' assoluta"| HasVersionsCore
  SettingsTable -->|"inerte per modelli con property dichiarata"| HasVersionsCore
```

---

## Nota per maintainers — fonte informazioni iniziali (solo questo documento di piano)

Le prime analisi sono state confrontate con un **gestionale Symfony legacy** dell’autore, percorso file: `/srv/http/utilities/nebula_old` (nome storico interno del repo: **Nebula**). **Non** citare questo nome, quel path o quel progetto nel modulo `**Modules/ERP`**, nel suo README rivolto agli utenti, né nel codice. Questa sezione serve solo a tracciare l’origine delle idee per chi cura il piano.
