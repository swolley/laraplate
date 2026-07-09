# Filament Performance – Raccomandazioni

> **Canonical spec:** `docs/superpowers/specs/2026-07-09-large-dataset-query-patterns-design.md` (Filament section). This file keeps extended notes and applied-resource history.

## Test vs Produzione: cosa aspettarsi

- **Produzione sarà più veloce** rispetto al tuo ambiente di test, ma **non miracolosamente**.  
  Il motivo principale è:
  - **DB in container (512MB)** in test spesso è più lento (I/O, risorse condivise).
  - **OPcache** in produzione riduce il costo del PHP.
  - **Redis** (se usato per cache/session) è più veloce del file driver.
  - **Meno logging/debug** (es. `APP_DEBUG=false`) riduce I/O e overhead.

- **Filament resta “pesante”** perché:
  - Carica molti componenti Livewire, fa diverse richieste (table, filters, modals).
  - Le list table fanno query + rendering di molte colonne.
  - Se ci sono N+1 o query pesanti in tab/tabella, la lentezza si sente sia in test sia in produzione.

Quindi: **sì, puoi aspettarti un miglioramento in produzione, ma per avere un pannello davvero reattivo servono anche miglioramenti mirati** (query, eager loading, riduzione lavoro inutile).

---

## Interventi ad alto impatto (consigliati)

### 1. ListModifications – Tab con N+1 `count()` ad ogni caricamento

**Problema:** `getTabs()` esegue **1 + N query COUNT** (una per “All” + una per ogni tipo con `HasApprovals`) **ogni volta** che si apre la lista modifiche.

**Soluzione:** Calcolare i badge in una sola query (es. `select modifiable_type, count(*) ... group by modifiable_type`) e mappare i risultati ai tab, oppure **non** mostrare il badge (o mostrarlo solo su richiesta / in modo lazy).  
In alternativa: cache breve (es. 60s) per i conteggi dei tab.

### 2. List Contents – N+1 su relazioni e media

**Problema:** La tabella Contents usa `entity.name`, `preset.name`, `cover`, `getMedia('images')` per ogni riga senza eager loading. Con 25 righe per pagina = 1 + 25×3+ query solo per quelle colonne.

**Soluzione:** In `ListContents` (o nella Page che costruisce la table) sovrascrivere `getTableQuery()` e fare:

```php
return parent::getTableQuery()
    ->with(['entity:id,name', 'preset:id,name', 'media']);
```

(oppure le relazioni esatte che usano le colonne della tabella). Così si evita N+1 su entity, preset e media.

### 3. SearchEngineHealthTableWidget – N query + engine stats

**Problema:** Per ogni model searchable: `new $model()`, `$model::query()->count()`, `$engine->stats()`, `$engine->checkIndex()`. Su CacheHealth page è molto costoso.

**Soluzione:**  
- Limitare i model mostrati (es. primi 5–10) o raggruppare per modulo.  
- Oppure cache (es. 5 minuti) per l’intero `getViewData()`.  
- `models()` è già memoizzato; il costo restante è soprattutto le N query COUNT e le chiamate all’engine.

### 4. Dashboard – Widget e query ripetute

**Problema:** Widget come `CoreStatsWidget` eseguono `User::query()->count()` e una query aggregata su licenze **ad ogni caricamento dashboard**.

**Soluzione:**  
- Impostare `protected static bool $isLazy = true` sui widget statistici (se supportato dalla tua versione Filament), così si caricano dopo il primo paint.  
- Oppure cache breve (30–60s) per `getStats()` (chiave tipo `filament_dashboard_core_stats`).

### 5. Paginazione e default delle tabelle

**Stato attuale:** In `HasTable` hai già `->deferLoading()`, `->deferFilters()`, `->persistFiltersInSession()`: bene.

**Consiglio:** Verificare che tutte le list usino una paginazione ragionevole (es. 25 record) e che non ci siano tabelle con `->paginated(false)` e migliaia di righe.

---

## Interventi a medio impatto

- **Ridurre colonne searchable/sortable** dove non servono: ogni colonna searchable può influire su query e indici.
- **Widget dashboard:** Valutare di mostrare meno widget sulla home (o solo a utenti admin) per ridurre il tempo al first byte.
- **Cache di configurazione:** In produzione `php artisan config:cache` e `php artisan route:cache` riducono il bootstrap dell’app.

---

## Cosa non cambierà molto da solo

- **Filament/Livewire** hanno un overhead intrinseco (JavaScript, round-trip per azioni).  
- **DB lento** (container con poche risorse) resterà il collo di bottiglia principale; in produzione un DB dedicato e ben configurato aiuta più di molti ritocchi PHP.

---

## Priorità pratica

1. **Subito:** Eager loading su List Contents (getTableQuery + `with([...])`).  
2. **Subito:** ListModifications – una sola query per i badge dei tab (o rimozione/cache dei badge).  
3. **Poi:** SearchEngineHealthTableWidget – cache o limitazione modelli.  
4. **Poi:** Widget dashboard – lazy o cache per le statistiche.

Dopo questi interventi, in produzione con DB e cache adeguati puoi aspettarti un pannello sensibilmente più reattivo; senza, il guadagno sarà limitato soprattutto dalla combinazione Filament + DB lento.

---

## Interventi applicati (refactoring tabelle)

Le seguenti ottimizzazioni sono state introdotte nelle Resources e Tables:

- **UserResource:** `with('roles')` + `defaultSort('name')`.
- **RoleResource:** `with('permissions')` + `defaultSort('name')`.
- **ACLResource:** `with('permission')` + `defaultSort('sort')`.
- **ModificationResource:** `with('modifier')` (le tab continuano a filtrare per `modifiable_type` nella List page).
- **PermissionResource / PermissionsTable:** opzioni dei filtri `guard_name`, `connection_name`, `table_name` in cache (TTL da `core.filament.tabs_counts_ttl_seconds` o 300s) + `defaultSort('name')`.
- **SettingResource / SettingsTable:** opzioni del filtro `group_name` in cache (stesso TTL).
- **ContentResource:** `with(['entity', 'preset', 'media'])` (ContentsTable aveva già `defaultSort('created_at', 'desc')`).
- **CategoryResource:** `with(['entity', 'preset', 'ancestors'])`.
- **PresetResource:** `with(['entity', 'template'])`.

Entities, Tags, Fields, Locations, Templates, CronJobs, Licenses non usano relazioni nelle colonne della table (o solo attributi in memoria), quindi non è stato aggiunto eager load.

---

## Linee guida per nuove Filament Tables

Checklist da rispettare quando si aggiunge o si modifica una table Filament:

1. **Query e eager loading**
   - Per ogni colonna che usa una relazione (es. `TextColumn::make('entity.name')`, `->relationship()`, badge su relazioni), la Resource deve applicare `->modifyQueryUsing(fn ($query) => $query->with([...]))` con tutte le relazioni necessarie.
   - Preferire `with(['relation1', 'relation2'])` nella Resource piuttosto che caricare le relazioni solo in alcune List pages.

2. **Ordinamento di default**
   - Impostare sempre un `->defaultSort()` sensato (es. `'name'`, `'created_at' desc`) nella Table o nella Resource per evitare ordinamenti impliciti e per migliorare la predicibilità delle query.

3. **Paginazione**
   - Non usare `->paginated(false)` per tabelle che possono crescere; mantenere la paginazione (default 25) già configurata in `HasTable`.

4. **Filtri con opzioni da DB**
   - Se un `SelectFilter` usa `Model::query()->distinct()->pluck(...)` (o query simili), preferire:
     - opzioni in cache con TTL breve (es. `Cache::remember('filament_...', 300, fn () => ...)`),
     - oppure `->relationship()` con `->searchable()->preload()` quando il filtro è su una relazione.
   - Evitare di eseguire query pesanti per le opzioni dei filtri ad ogni caricamento della lista.

5. **Colonne costose**
   - Per colonne con `formatStateUsing` / `getStateUsing` che chiamano metodi sul modello o relazioni, assicurarsi che le relazioni usate siano incluse in `with()`.
   - Per gerarchie (es. `ancestors->count()`), includere `ancestors` (o la relazione equivalente) nell’eager loading.

6. **Toggle e colonne nascoste**
   - Usare `->toggleable(isToggledHiddenByDefault: true)` per colonne secondarie o pesanti (path, JSON, descrizioni lunghe) per ridurre il lavoro di rendering di default.

---

## Come misurare e verificare i miglioramenti

1. **TTFB (Time To First Byte)**
   - Aprire la lista (es. Users, Contents, Categories) nel browser, scheda Network.
   - Leggere il tempo della richiesta che carica la tabella (tipicamente la prima richiesta alla route admin o la richiesta Livewire che restituisce l’HTML/JSON della table).
   - Confrontare prima e dopo le ottimizzazioni (stesso ambiente, stessi dati approssimativi).

2. **Numero di query**
   - Abilitare il log delle query (es. `DB::enableQueryLog()` in un middleware di debug, o usare Laravel Telescope / Debugbar).
   - Caricare una pagina di lista (es. 25 record) e contare le query eseguite.
   - Obiettivo: nessun N+1 (es. da ~1 + 25×3 a ~1 + 2–3 query con eager load).

3. **Pagine prioritarie da tenere d’occhio**
   - List Users (molti ruoli/utenti).
   - List Contents (entity, preset, media).
   - List Categories (entity, preset, ancestors).
   - List Modifications (modifier).
   - List Permissions (filtri: dopo la cache le opzioni non devono più generare 3 query a ogni load).

4. **Ambiente di produzione**
   - Ripetere le stesse verifiche (o solo TTFB) in staging/produzione con OPcache attivo e `APP_DEBUG=false` per stimare il guadagno reale.
