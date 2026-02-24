# Filament Performance – Raccomandazioni

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
