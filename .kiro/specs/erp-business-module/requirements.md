# Documento Requisiti — Modulo ERP (Laraplate)

## Introduzione

Il modulo `Modules/ERP` è il modulo ERP di Laraplate. Questo documento specifica i requisiti funzionali per la roadmap **M2 → M4**, partendo dallo stato attuale (M1 completato) fino al ciclo ERP completo con contabilità, CRM, magazzino, ciclo attivo e passivo, e-invoice, policy di sicurezza e reporting.

**Stack:** PHP 8.5+, Laravel 12, Filament 5, Livewire 4, PestPHP 4, nwidart/laravel-modules 12.

**Lingua di lavoro:** Italiano.

---

## Glossario

- **Company**: Radice tenant ERP; ogni entità transazionale appartiene a una Company tramite `company_id` + `BelongsToCompanyScope`.
- **JournalEntry / JournalEntryLine**: Voucher di partita doppia; le righe sommano a zero su `amount_local`.
- **JournalPostingService**: Unico entry-point per post e reverse di journal.
- **DocumentNumberAllocator**: Allocatore numeri documento con lock pessimistico; `gap_allowed=false` per tipi fiscali.
- **Movement**: Adapter contabile cassa/Tricount che genera `JournalEntry` via `JournalPostingService`.
- **PartnerPool**: Gruppo di partecipanti a una cassa condivisa (Tricount).
- **PoolTransaction**: Singola transazione all'interno di un `PartnerPool`.
- **TaxCode**: Codice IVA/ritenuta immutabile per `(company_id, code)`; la supersessione crea una nuova riga.
- **TaxLineCalculator**: Calcola importi IVA/ritenuta a partire da `TaxCode` attivo alla data di posting.
- **Snapshot fiscale**: Colonne `tax_code`, `tax_rate`, `tax_label` denormalizzate sulle righe documento al momento del posting; immutabili.
- **FiscalYear / FiscalPeriod**: Anno e periodo fiscale con lock progressivo; posting bloccato su periodi chiusi.
- **Lead**: Contatto commerciale in fase di qualificazione; `party_id` opzionale.
- **Opportunity**: Opportunità commerciale qualificata; `party_id` obbligatorio, collegata a uno stage CRM.
- **Party**: Anagrafica unificata (ex `Customer`); può avere ruoli `customer`, `supplier`, o entrambi.
- **SalesOrder**: Ordine di vendita generato da una `Quotation` accettata.
- **SalesOrderLine**: Riga ordine con quantità ordinate, consegnate e fatturate.
- **DeliveryNote**: DDT di vendita; genera `StockMovement` di scarico.
- **Invoice**: Fattura attiva o passiva; ogni fattura postata genera `JournalEntry` automatico.
- **EInvoiceProvider**: Contratto per l'invio di fatture elettroniche (interfaccia, nessuna implementazione concreta nel modulo).
- **PurchaseOrder**: Ordine di acquisto al fornitore.
- **GoodsReceipt**: Bolla di ingresso merce; genera `StockMovement` di carico.
- **Item**: Articolo di magazzino con metodo di costing (`fifo` o `weighted_avg`).
- **Warehouse**: Sede magazzino, opzionalmente collegata a un `Place` Core.
- **StockLevel**: Giacenza per `item × warehouse × company`.
- **StockMovement**: Movimento di magazzino (carico/scarico/trasferimento) con dual-currency e COGS.
- **StockCostLayer**: Layer FIFO per il calcolo del costo di scarico.
- **StockMovementService**: Unico entry-point per movimenti di magazzino; calcola COGS e chiama `JournalPostingService`.
- **BalanceSheetService**: Servizio di reporting Stato Patrimoniale.
- **IncomeStatementService**: Servizio di reporting Conto Economico.
- **VatLedgerService**: Servizio registro IVA vendite/acquisti.
- **SalesPipelineService**: Servizio funnel CRM opportunity → won.
- **StockValuationService**: Servizio valorizzazione magazzino al metodo di costing scelto.
- **BelongsToCompany**: Trait + global scope che filtra ogni query per la company attiva.
- **ERPMigrateUtils**: Helper migration con `moneyColumns()` (genera `amount_doc`, `currency_doc`, `amount_local`, `fx_rate`) e `companyForeign()`.
- **CurrencyConverter**: Facade no-op in M0/M2; `amount_local = amount_doc × fx_rate`, `fx_rate=1.0` per EUR/EUR.
- **VersionStrategy::DIFF**: Strategia di versioning forzata sui modelli contabili; ha priorità assoluta sul record `settings.version_strategy_{table}`.
- **Dual-currency**: Ogni importo monetario ha `amount_doc` + `currency_doc` + `amount_local` + `fx_rate`.
- **Lock pessimistico**: `SELECT ... FOR UPDATE` su `document_sequences` per tipi `gap_allowed=false`.
- **Riconciliazione tre-vie**: Verifica coerenza PO / GoodsReceipt / Invoice al posting ciclo passivo.
- **Amendment SO**: Clonazione righe non evase di un `SalesOrder` in un nuovo SO con `amends_sales_order_id`.

---

## Requisiti

