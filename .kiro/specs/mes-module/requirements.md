# Requirements Document — Modulo MES

## Introduction

Il modulo **MES** (Manufacturing Execution System) di Laraplate è un modulo Laravel che gestisce l'esecuzione, il tracciamento e il controllo della produzione manifatturiera in tempo reale. Copre sia la produzione discreta (pezzi, assiemi) sia quella di processo (batch, ricette).

Il modulo dipende dal modulo **ERP** come dipendenza dichiarata: utilizza direttamente le tabelle `items`, `warehouses` e `stock_movements` dell'ERP tramite relazioni Eloquent standard, evitando duplicazione di dati. Per le operazioni con logica complessa (movimenti di magazzino con costing FIFO/media, posting contabile) il MES usa contratti/interfacce implementati dall'ERP, in modo da non accoppiare il MES alla logica interna dell'ERP. Chi usa un ERP esterno (SAP, Odoo, ecc.) può creare un modulo bridge separato che popola le tabelle ERP di Laraplate e implementa i contratti MES.

Il modulo espone un pannello amministrativo Filament e un'API REST per l'integrazione con sistemi esterni e terminali di produzione.

---

## Glossary

- **MES_System**: Il modulo MES nel suo complesso.
- **WorkCenter**: Risorsa produttiva fisica (macchina, cella, linea, postazione) con capacità e calendario di disponibilità.
- **BOM** (Bill of Materials / Distinta Base): Struttura gerarchica che definisce quali componenti e in quali quantità sono necessari per produrre un articolo finito o semilavorato.
- **BomLine**: Singola riga della distinta base, che associa un componente a una BOM con quantità e unità di misura. Referenzia `item_id` sulla tabella `items` dell'ERP.
- **Routing**: Ciclo di lavorazione: sequenza ordinata di operazioni da eseguire per produrre un articolo.
- **RoutingOperation**: Singola fase del ciclo di lavorazione, associata a un WorkCenter con tempi di setup e ciclo.
- **ProductionOrder**: Ordine di produzione che richiede la fabbricazione di una quantità definita di un articolo. È distinto dall'ordine di vendita (SalesOrder ERP): un ProductionOrder può esistere senza un ordine cliente (produzione su stock) oppure essere collegato a uno o più SalesOrder.
- **ProductionOrderOperation**: Istanza di una RoutingOperation nell'ambito di un ProductionOrder specifico, con stato e tempi pianificati/effettivi.
- **MaterialConsumption**: Registrazione del consumo effettivo di un componente durante la produzione (manuale o backflush).
- **LotNumber**: Identificatore di lotto assegnato a una quantità di materiale prodotto o acquistato, per la tracciabilità.
- **SerialNumber**: Identificatore univoco assegnato a una singola unità prodotta, per la tracciabilità individuale.
- **QualityCheck**: Controllo qualità eseguito su un lotto o su un'operazione, con esito e misurazioni.
- **NonConformance**: Segnalazione di un difetto, scarto o rilavorazione rilevata durante o dopo la produzione.
- **ProductionSchedule**: Piano di produzione che assegna ordini di produzione a work center e finestre temporali.
- **CapacityLoad**: Carico pianificato vs disponibile per un WorkCenter in un dato periodo.
- **Downtime**: Fermo macchina registrato su un WorkCenter (guasto, manutenzione, setup, attesa).
- **Shift**: Turno di lavoro con orari, operatori assegnati e work center coperti.
- **OperatorLog**: Registro delle attività di un operatore durante un turno (operazioni eseguite, tempi, materiali consumati).
- **Item** (da ERP): Articolo anagrafico. Il MES referenzia direttamente la tabella `items` dell'ERP tramite FK e relazioni Eloquent standard.
- **Warehouse** (da ERP): Magazzino. Il MES referenzia direttamente la tabella `warehouses` dell'ERP tramite FK e relazioni Eloquent standard.
- **SalesOrder** (da ERP): Ordine di vendita. Il MES può collegare opzionalmente un ProductionOrder a un SalesOrder tramite FK nullable sulla tabella `sales_orders` dell'ERP.
- **StockMovementRecorder**: Contratto (interfaccia) che il MES usa per registrare movimenti di magazzino (consumo componenti, resa prodotti finiti). Implementato dall'ERP tramite `StockMovementService`, che gestisce la logica FIFO/costing/journal senza che il MES la conosca.
- **Company**: Entità aziendale (multi-tenant). Il MES usa il trait `BelongsToCompany` e la tabella `companies` dell'ERP, identici al pattern già usato in ERP.
- **Backflush**: Modalità di consumo automatico dei materiali al completamento di un'operazione o dell'ordine.
- **UOM**: Unità di misura (Unit of Measure).
- **ERPBridge**: Modulo opzionale separato (non parte del MES) che sincronizza dati da un ERP esterno (SAP, Odoo, ecc.) verso le tabelle ERP di Laraplate e implementa i contratti MES.

---

## Requirements

---

### Requirement 1: Gestione Work Center

**User Story:** Come responsabile di produzione, voglio definire e gestire i work center (macchine, celle, linee), così da avere una mappa precisa delle risorse produttive disponibili con le loro capacità e calendari.

#### Acceptance Criteria

1. THE MES_System SHALL associare ogni WorkCenter a una Company tramite il pattern `BelongsToCompany`.
2. THE MES_System SHALL richiedere per ogni WorkCenter un codice univoco per azienda, un nome, una tipologia (machine, cell, line, manual_station) e una capacità oraria espressa in UOM configurabile.
3. WHEN un WorkCenter viene creato o aggiornato, THE MES_System SHALL validare che il codice sia univoco nell'ambito della stessa Company.
4. THE MES_System SHALL permettere di associare a ogni WorkCenter un calendario di disponibilità settimanale (fasce orarie per giorno della settimana).
5. WHEN un WorkCenter viene disattivato, THE MES_System SHALL impedire la creazione di nuovi ProductionOrderOperation su quel WorkCenter e segnalare il conflitto con eventuali operazioni già pianificate.
6. THE MES_System SHALL esporre i WorkCenter tramite API REST con endpoint di lettura (lista e dettaglio) e scrittura (creazione, aggiornamento, disattivazione).

---

### Requirement 2: Gestione Distinta Base (BOM)

**User Story:** Come ingegnere di produzione, voglio definire le distinte base degli articoli, così da sapere esattamente quali componenti e in quali quantità sono necessari per produrre ogni articolo finito o semilavorato.

#### Acceptance Criteria

1. THE MES_System SHALL associare ogni BOM a un articolo tramite FK su `items.id` (tabella ERP) e a una Company.
2. THE MES_System SHALL supportare BOM multi-livello, dove un componente in una BomLine può essere a sua volta un articolo con una propria BOM.
3. THE MES_System SHALL richiedere per ogni BomLine: articolo componente (FK su `items.id`), quantità (decimale positiva), UOM e metodo di consumo (`backflush` o `manual`).
4. THE MES_System SHALL supportare versioni di BOM: ogni BOM ha un numero di versione e una data di validità (`valid_from`, `valid_to`).
5. WHEN viene richiesta la BOM attiva per un articolo a una data specifica, THE MES_System SHALL restituire la versione con `valid_from` più recente non superiore alla data richiesta e `valid_to` nullo o superiore alla data richiesta.
6. IF una BOM è associata a un ProductionOrder già rilasciato, THEN THE MES_System SHALL impedire la modifica o la disattivazione di quella versione di BOM e restituire un errore descrittivo.
7. THE MES_System SHALL esporre le BOM tramite API REST con endpoint di lettura (lista, dettaglio, esplosione multi-livello) e scrittura.

---

### Requirement 3: Gestione Routing e Cicli di Lavorazione

**User Story:** Come ingegnere di processo, voglio definire i cicli di lavorazione degli articoli, così da specificare la sequenza di operazioni, i work center coinvolti e i tempi standard per ogni fase.

#### Acceptance Criteria

1. THE MES_System SHALL associare ogni Routing a un articolo tramite FK su `items.id` (tabella ERP) e a una Company.
2. THE MES_System SHALL richiedere per ogni RoutingOperation: sequenza numerica (per ordinamento), descrizione, WorkCenter associato, tempo di setup (minuti) e tempo di ciclo per unità (minuti/UOM).
3. THE MES_System SHALL supportare versioni di Routing con lo stesso meccanismo di validità temporale delle BOM (`valid_from`, `valid_to`).
4. WHEN viene richiesto il Routing attivo per un articolo a una data specifica, THE MES_System SHALL restituire la versione con `valid_from` più recente non superiore alla data richiesta.
5. THE MES_System SHALL supportare operazioni parallele nello stesso Routing, identificate dallo stesso numero di sequenza con flag `is_parallel`.
6. IF un Routing è associato a un ProductionOrder già rilasciato, THEN THE MES_System SHALL impedire la modifica della versione di Routing in uso e restituire un errore descrittivo.
7. THE MES_System SHALL esporre i Routing tramite API REST con endpoint di lettura e scrittura.

---

### Requirement 4: Gestione Ordini di Produzione

**User Story:** Come pianificatore di produzione, voglio creare e gestire gli ordini di produzione, così da avviare la fabbricazione di articoli con quantità, date e risorse definite.

#### Acceptance Criteria

1. THE MES_System SHALL associare ogni ProductionOrder a una Company e richiedere: articolo da produrre (FK su `items.id`), quantità pianificata, UOM, data di inizio pianificata e data di fine pianificata.
2. THE MES_System SHALL assegnare automaticamente un numero documento progressivo a ogni ProductionOrder, univoco per Company e anno.
3. THE MES_System SHALL supportare i seguenti stati per un ProductionOrder: `draft`, `released`, `in_progress`, `completed`, `cancelled`.
4. WHEN un ProductionOrder viene creato, THE MES_System SHALL copiare la BOM attiva e il Routing attivo dell'articolo alla data di creazione, congelandoli nell'ordine (snapshot).
5. WHEN un ProductionOrder viene rilasciato (`released`), THE MES_System SHALL generare le ProductionOrderOperation corrispondenti alle RoutingOperation del Routing congelato.
6. WHEN un ProductionOrder viene completato, THE MES_System SHALL registrare la quantità effettivamente prodotta e invocare `StockMovementRecorder` per incrementare la giacenza dell'articolo finito nel magazzino di destinazione (FK su `warehouses.id`).
7. IF la quantità prodotta è inferiore alla quantità pianificata, THEN THE MES_System SHALL permettere il completamento parziale e mantenere traccia dello scarto o della quantità mancante.
8. THE MES_System SHALL permettere di collegare un ProductionOrder a un SalesOrder tramite FK nullable su `sales_orders.id` (tabella ERP), memorizzando il riferimento.
9. WHEN un SalesOrder viene confermato nell'ERP, THE MES_System SHALL poter creare automaticamente un ProductionOrder in stato `draft` per ogni riga dell'ordine che referenzia un articolo con una BOM attiva.
10. THE MES_System SHALL esporre i ProductionOrder tramite API REST con endpoint di lettura (lista, dettaglio, operazioni) e scrittura (creazione, aggiornamento stato, completamento).

---

### Requirement 5: Esecuzione Operazioni di Produzione

**User Story:** Come operatore di produzione, voglio registrare l'avanzamento delle operazioni sul piano di produzione, così da tenere aggiornato lo stato reale della produzione in tempo reale.

#### Acceptance Criteria

1. THE MES_System SHALL associare ogni ProductionOrderOperation a un ProductionOrder, a una RoutingOperation e a un WorkCenter.
2. THE MES_System SHALL supportare i seguenti stati per una ProductionOrderOperation: `planned`, `ready`, `in_progress`, `completed`, `skipped`.
3. WHEN un operatore avvia un'operazione, THE MES_System SHALL registrare il timestamp di inizio effettivo e l'operatore che ha avviato l'operazione.
4. WHEN un operatore completa un'operazione, THE MES_System SHALL registrare il timestamp di fine effettivo, la quantità prodotta in quella fase e calcolare l'efficienza (tempo effettivo vs tempo standard).
5. WHILE un'operazione è in stato `in_progress`, THE MES_System SHALL impedire l'avvio di un'altra operazione sullo stesso WorkCenter se la capacità è esaurita.
6. THE MES_System SHALL permettere di registrare note operative su ogni ProductionOrderOperation.
7. THE MES_System SHALL esporre le operazioni tramite API REST per consentire ai terminali di produzione di aggiornare lo stato in tempo reale.

---

### Requirement 6: Consumo Materiali

**User Story:** Come responsabile di magazzino, voglio tracciare il consumo effettivo dei componenti durante la produzione, così da mantenere le giacenze accurate e rilevare scostamenti rispetto alla distinta base.

#### Acceptance Criteria

1. THE MES_System SHALL supportare due modalità di consumo per ogni BomLine: `backflush` (automatico) e `manual` (esplicito dall'operatore).
2. WHEN una ProductionOrderOperation viene completata e la BomLine associata ha metodo `backflush`, THE MES_System SHALL creare automaticamente un MaterialConsumption e invocare `StockMovementRecorder` per decrementare la giacenza del componente tramite `StockMovementService` dell'ERP.
3. WHEN un operatore registra un consumo manuale, THE MES_System SHALL richiedere: componente (FK su `items.id`), quantità consumata, UOM, magazzino di prelievo (FK su `warehouses.id`) e opzionalmente il LotNumber del componente prelevato.
4. THE MES_System SHALL calcolare e memorizzare lo scostamento tra quantità consumata effettiva e quantità teorica da BOM per ogni MaterialConsumption.
5. IF la giacenza disponibile del componente è insufficiente al momento del consumo, THEN THE MES_System SHALL registrare il consumo con flag `stock_shortage` e notificare il responsabile tramite evento applicativo.
6. THE MES_System SHALL esporre i consumi tramite API REST con endpoint di lettura per report e analisi.

---

### Requirement 7: Tracciabilità Lotti e Numeri Seriali

**User Story:** Come responsabile qualità, voglio tracciare i lotti e i numeri seriali dei prodotti finiti e dei componenti utilizzati, così da poter risalire alla storia completa di ogni unità prodotta in caso di non conformità o richiami.

#### Acceptance Criteria

1. THE MES_System SHALL supportare due livelli di tracciabilità per articolo: `lot` (tracciabilità per lotto) e `serial` (tracciabilità per numero seriale), configurabili tramite un campo `tracing_type` sulla tabella `items` dell'ERP (aggiunto tramite migration del modulo MES).
2. WHEN un ProductionOrder viene completato, THE MES_System SHALL richiedere l'assegnazione di un LotNumber o SerialNumber al prodotto finito, se l'articolo richiede tracciabilità.
3. THE MES_System SHALL generare automaticamente il codice del LotNumber secondo un formato configurabile per Company (es. `{ANNO}{MESE}{GIORNO}-{SEQUENZA}`).
4. THE MES_System SHALL permettere di associare a ogni LotNumber i LotNumber dei componenti consumati, costruendo un albero di tracciabilità bidirezionale (forward e backward tracing).
5. WHEN viene richiesta la tracciabilità forward di un LotNumber, THE MES_System SHALL restituire tutti i prodotti finiti in cui quel lotto è stato utilizzato come componente.
6. WHEN viene richiesta la tracciabilità backward di un LotNumber, THE MES_System SHALL restituire tutti i componenti (con i loro lotti) utilizzati per produrre quel lotto.
7. THE MES_System SHALL esporre la tracciabilità tramite API REST con endpoint dedicati per forward e backward tracing.

---

### Requirement 8: Controllo Qualità e Non Conformità

**User Story:** Come responsabile qualità, voglio registrare i controlli qualità e le non conformità durante e dopo la produzione, così da garantire che solo prodotti conformi vengano rilasciati e da tracciare le cause di difettosità.

#### Acceptance Criteria

1. THE MES_System SHALL permettere di definire piani di controllo qualità associati a un articolo o a una RoutingOperation, con i parametri da misurare e i limiti di accettazione.
2. WHEN una ProductionOrderOperation viene completata, THE MES_System SHALL verificare se esiste un piano di controllo qualità associato e, in caso affermativo, creare un QualityCheck in stato `pending`.
3. WHEN un QualityCheck viene eseguito, THE MES_System SHALL registrare: operatore, timestamp, misurazioni effettuate e esito (`passed`, `failed`, `conditional`).
4. IF un QualityCheck ha esito `failed`, THEN THE MES_System SHALL bloccare il rilascio del lotto associato e creare automaticamente una NonConformance.
5. THE MES_System SHALL richiedere per ogni NonConformance: descrizione del difetto, quantità non conforme, causa radice (da lista configurabile), azione correttiva proposta e stato (`open`, `under_review`, `resolved`, `closed`).
6. THE MES_System SHALL supportare le seguenti disposizioni per una NonConformance: `scrap` (scarto), `rework` (rilavorazione), `use_as_is` (uso in deroga), `return_to_supplier`.
7. WHEN una NonConformance ha disposizione `rework`, THE MES_System SHALL permettere la creazione di un nuovo ProductionOrder di rilavorazione collegato alla NonConformance originale.
8. THE MES_System SHALL esporre QualityCheck e NonConformance tramite API REST.

---

### Requirement 9: Scheduling e Pianificazione della Capacità

**User Story:** Come pianificatore di produzione, voglio visualizzare e gestire il piano di produzione con il carico dei work center, così da ottimizzare l'utilizzo delle risorse e rispettare le date di consegna.

#### Acceptance Criteria

1. THE MES_System SHALL calcolare il CapacityLoad per ogni WorkCenter come somma dei tempi pianificati delle ProductionOrderOperation assegnate in un dato periodo.
2. THE MES_System SHALL esporre una vista del ProductionSchedule che mostri, per ogni WorkCenter e per ogni giorno, le operazioni pianificate con i relativi ordini di produzione.
3. WHEN viene pianificata una ProductionOrderOperation su un WorkCenter, THE MES_System SHALL verificare che la capacità disponibile del WorkCenter nel periodo non sia superata e segnalare il sovraccarico senza bloccarlo (warning non bloccante).
4. THE MES_System SHALL supportare la ripianificazione manuale: un pianificatore può spostare una ProductionOrderOperation a un WorkCenter alternativo o a una finestra temporale diversa, purché l'operazione sia in stato `planned` o `ready`.
5. THE MES_System SHALL calcolare la data di fine stimata di un ProductionOrder sommando i tempi standard delle operazioni pianificate, tenendo conto della capacità disponibile dei WorkCenter.
6. THE MES_System SHALL esporre il ProductionSchedule e il CapacityLoad tramite API REST per integrazione con strumenti di pianificazione esterni.

---

### Requirement 10: Gestione Turni e Operatori

**User Story:** Come responsabile di produzione, voglio definire i turni di lavoro e registrare le attività degli operatori, così da avere visibilità sulla presenza e sull'efficienza del personale di produzione.

#### Acceptance Criteria

1. THE MES_System SHALL permettere di definire Shift con: nome, orario di inizio, orario di fine, giorni della settimana applicabili e WorkCenter coperti.
2. THE MES_System SHALL permettere di associare operatori (utenti Core) a un Shift per una data specifica, creando un'istanza di turno (`ShiftInstance`).
3. WHEN un operatore avvia un'operazione di produzione, THE MES_System SHALL verificare che esista uno ShiftInstance attivo per quell'operatore e WorkCenter nella finestra temporale corrente.
4. THE MES_System SHALL creare automaticamente un OperatorLog per ogni operazione avviata e completata da un operatore, registrando i tempi effettivi.
5. THE MES_System SHALL calcolare per ogni operatore e per ogni turno: numero di operazioni completate, tempo produttivo totale, efficienza media (tempo effettivo vs tempo standard).
6. THE MES_System SHALL esporre Shift e OperatorLog tramite API REST.

---

### Requirement 11: Gestione Fermi Macchina (Downtime)

**User Story:** Come manutentore, voglio registrare i fermi macchina con causa e durata, così da analizzare l'affidabilità dei work center e pianificare la manutenzione preventiva.

#### Acceptance Criteria

1. THE MES_System SHALL permettere di registrare un Downtime su un WorkCenter con: timestamp di inizio, timestamp di fine (nullable se in corso), causa (da lista configurabile: `breakdown`, `planned_maintenance`, `setup`, `waiting_material`, `quality_issue`, `other`) e note.
2. WHILE un WorkCenter ha un Downtime attivo (fine non registrata), THE MES_System SHALL segnalare il WorkCenter come non disponibile nel ProductionSchedule.
3. WHEN un Downtime viene chiuso (fine registrata), THE MES_System SHALL calcolare la durata totale e aggiornare il CapacityLoad del WorkCenter per il periodo interessato.
4. THE MES_System SHALL calcolare per ogni WorkCenter e per un periodo configurabile: OEE (Overall Equipment Effectiveness) come prodotto di Availability × Performance × Quality, dove Availability = (tempo disponibile − downtime) / tempo disponibile.
5. THE MES_System SHALL esporre i Downtime e le metriche OEE tramite API REST.

---

### Requirement 12: Dipendenza da ERP e pattern di integrazione

**User Story:** Come sviluppatore, voglio che il modulo MES dipenda dal modulo ERP in modo dichiarato e pulito, usando direttamente le sue tabelle per i dati condivisi e contratti solo per le operazioni con logica complessa.

#### Acceptance Criteria

1. THE MES_System SHALL dichiarare il modulo ERP come dipendenza required in `module.json` e `composer.json`.
2. THE MES_System SHALL referenziare direttamente tramite FK e relazioni Eloquent le tabelle ERP condivise: `items` (articoli), `warehouses` (magazzini), `companies` (multi-tenancy), `sales_orders` (collegamento opzionale ordini di vendita).
3. THE MES_System SHALL usare il trait `BelongsToCompany` e il global scope `BelongsToCompanyScope` dell'ERP su tutti i propri modelli, identicamente al pattern già usato nell'ERP.
4. THE MES_System SHALL definire il contratto `StockMovementRecorder` per delegare all'ERP la registrazione dei movimenti di magazzino (consumo componenti, resa prodotti finiti), in modo che la logica FIFO/costing/journal resti incapsulata nell'ERP.
5. WHEN `StockMovementRecorder` viene invocato, THE MES_System SHALL passare: `item_id`, `warehouse_id`, `quantity`, `direction` (in/out), `company_id`, riferimento morph all'ordine di produzione e timestamp.
6. THE MES_System SHALL aggiungere tramite proprie migration le colonne necessarie sulle tabelle ERP esistenti (es. `tracing_type` su `items`) senza modificare le migration originali dell'ERP.
7. WHERE un ERP esterno è in uso, THE MES_System SHALL supportare l'integrazione tramite un modulo bridge separato (non parte del MES) che popola le tabelle ERP di Laraplate e implementa il contratto `StockMovementRecorder`.

---

### Requirement 13: API REST

**User Story:** Come sviluppatore di sistemi di automazione, voglio accedere a tutte le funzionalità del MES tramite API REST autenticata, così da integrare terminali di produzione, SCADA e altri sistemi esterni.

#### Acceptance Criteria

1. THE MES_System SHALL esporre tutte le risorse principali (WorkCenter, BOM, Routing, ProductionOrder, ProductionOrderOperation, MaterialConsumption, LotNumber, QualityCheck, NonConformance, Downtime) tramite endpoint REST versionati sotto `/api/v1/mes/`.
2. THE MES_System SHALL autenticare tutte le richieste API tramite Laravel Sanctum con token di tipo `api`.
3. THE MES_System SHALL restituire le risposte in formato JSON utilizzando Laravel API Resources con struttura consistente: `data`, `meta` (per liste paginate) e `errors` (per errori).
4. WHEN una richiesta API non è autenticata, THE MES_System SHALL restituire HTTP 401 con messaggio descrittivo.
5. WHEN una richiesta API non è autorizzata per il ruolo dell'utente, THE MES_System SHALL restituire HTTP 403 con messaggio descrittivo.
6. WHEN una richiesta API contiene dati non validi, THE MES_System SHALL restituire HTTP 422 con la lista dettagliata degli errori di validazione per campo.
7. THE MES_System SHALL implementare rate limiting sugli endpoint API, configurabile tramite il file di configurazione del modulo.

---

### Requirement 14: Pannello Amministrativo Filament

**User Story:** Come responsabile di produzione, voglio gestire tutte le entità del MES tramite un pannello amministrativo Filament, così da avere un'interfaccia grafica completa senza dover usare l'API direttamente.

#### Acceptance Criteria

1. THE MES_System SHALL esporre Filament Resources per tutte le entità principali: WorkCenter, BOM, Routing, ProductionOrder, QualityCheck, NonConformance, Downtime, Shift.
2. THE MES_System SHALL implementare nella Filament Resource dei ProductionOrder una pagina di dettaglio con: stato dell'ordine, lista delle operazioni con stato e avanzamento, consumi materiali e controlli qualità associati.
3. THE MES_System SHALL implementare un widget Filament di tipo dashboard che mostri: ordini in produzione, work center con downtime attivi, controlli qualità in attesa e OEE medio del giorno corrente.
4. THE MES_System SHALL applicare le policy di autorizzazione Core (permessi e ruoli) a tutte le Filament Resources, in modo che ogni utente veda e modifichi solo le risorse della propria Company.
5. WHERE il modulo ERP è attivo, THE MES_System SHALL mostrare nelle Filament Resources dei ProductionOrder il collegamento all'ordine di vendita ERP con link navigabile.
