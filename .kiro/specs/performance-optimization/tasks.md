# Implementation Plan: Performance Optimization

## Overview

Piano di implementazione delle ottimizzazioni di performance per Laraplate.
I task seguono l'ordine suggerito: prima le fondamenta (cache key + TTL config), poi le
ottimizzazioni no-downside del Core, poi quelle del CMS, poi le ottimizzazioni con trade-off,
e infine la mitigazione dell'Issue A (cache tag su driver file/database).

Ogni task è atomico, testabile e deployabile indipendentemente.
I sub-task di test sono marcati con `*` e sono opzionali.

---

## Tasks

- [x] 1. Aggiungere TTL nominali in `config/cache.php` e metodo `CacheManager::key()`
  - Aggiungere la sezione `duration` con le chiavi `short` (60), `medium` (300), `long` (3600), `day` (86400), `forever` (null) in `config/cache.php`
  - Aggiungere il metodo statico `key(string $namespace, string ...$parts): string` in `Modules/Core/app/Cache/CacheManager.php` che genera chiavi nel formato `{app_name}:{namespace}:{parts_joined_by_colon}`
  - Il metodo deve usare `config('app.name')` con lo stesso pattern di caching statico già presente in `Repository::getCacheTags()`
  - _Requirements: 6.1, 6.3, 6.4_

  - [x] 1.1 Scrivere property test per `CacheManager::key()`
    - **Property 7: Cache key format is consistent**
    - **Validates: Requirements 6.1**
    - Verificare che per qualsiasi namespace e sequenza di parti il formato sia `{app_name}:{namespace}:{parts}`
    - File: `Modules/Core/tests/Unit/Cache/CacheManagerTest.php`

- [x] 2. Aggiungere static in-memory cache in `HasValidations` per l'esistenza dei permessi
  - Aggiungere `private static array $permission_existence_cache = []` (shape: `array<string, bool>`) in `Modules/Core/app/Helpers/HasValidations.php`
  - Modificare `checkUserCanDo()` per consultare prima la static cache prima di eseguire `$permission_class::whereName($permission)->count()`
  - Aggiungere il metodo pubblico statico `resetPermissionExistenceCache(): void` per i test
  - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

  - [x] 2.1 Scrivere property test per la cache di esistenza permessi
    - **Property 1: Permission existence cache eliminates redundant DB queries**
    - **Validates: Requirements 1.1, 1.2, 1.4**
    - Verificare che la seconda chiamata a `checkUserCanDo()` con lo stesso permission name non emetta query DB
    - File: `Modules/Core/tests/Unit/Helpers/HasValidationsTest.php`

- [x] 3. Aggiungere static in-memory L1 cache in `HasVersions` per la version strategy
  - Aggiungere `private static array $version_strategy_cache = []` (shape: `array<class-string, VersionStrategy|false>`) in `Modules/Core/app/Helpers/HasVersions.php`
  - Modificare `getVersionStrategy()` per consultare prima la static map, poi il persistent cache (L2), poi il DB (L3)
  - Aggiungere il metodo pubblico statico `resetVersionStrategyCache(): void`
  - _Requirements: 3.1, 3.2, 3.3, 13.2_

  - [x] 3.1 Scrivere property test per la L1 cache di version strategy
    - **Property 4: Version strategy L1 cache eliminates repeated deserialization**
    - **Validates: Requirements 3.1, 3.2, 13.2**
    - Verificare che la seconda chiamata a `getVersionStrategy()` per la stessa classe non emetta query DB né acceda al persistent cache
    - File: `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`

- [x] 4. Prefissare la chiave persistent cache di `HasVersions` con il nome applicazione
  - Modificare `getVersionStrategy()` per usare `CacheManager::key('version_strategies')` al posto della stringa piatta `'version_strategies'`
  - Verificare che il `Setting` observer/`HasCache` invalidi la nuova chiave prefissata quando un `Setting` con `group_name = 'versioning'` viene salvato o eliminato
  - _Requirements: 11.1, 11.2, 11.3_

  - [x] 4.1 Scrivere property test per il prefisso della chiave cache
    - **Property 15: Version strategies cache key includes app name prefix**
    - **Validates: Requirements 11.1**
    - Verificare che la chiave persistent cache contenga il nome applicazione come prefisso
    - File: `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`

  - [x] 4.2 Scrivere property test per l'invalidazione della cache al salvataggio di Setting
    - **Property 16: Versioning settings cache is invalidated on Setting save/delete**
    - **Validates: Requirements 11.2**
    - Verificare che dopo il salvataggio di un `Setting` con `group_name = 'versioning'` la chiave cache sia assente
    - File: `Modules/Core/tests/Unit/Helpers/HasVersionsTest.php`

- [x] 5. Aggiungere static locale cache in `HasTranslations` e ottimizzare il fallback
  - Aggiungere `private static ?string $default_locale_cache = null` in `Modules/Core/app/Helpers/HasTranslations.php`
  - Modificare `getTranslatableFieldValue()` per leggere `config('app.locale')` una sola volta per request e cacharlo nella static property
  - Prima di chiamare `getDefaultTranslation()` (che emette una query), verificare se la relazione `translations` è già caricata in memoria e cercare lì la traduzione di default
  - Aggiungere il metodo pubblico statico `resetLocaleCache(): void`
  - _Requirements: 4.1, 4.2, 4.3, 13.1_

  - [x] 5.1 Scrivere property test per il fallback senza query aggiuntive
    - **Property 5: HasTranslations avoids extra queries when translations collection is loaded**
    - **Validates: Requirements 4.1, 4.2**
    - Verificare che con la relazione `translations` già caricata, `getTranslatableFieldValue()` non emetta query DB
    - File: `Modules/Core/tests/Unit/Helpers/HasTranslationsTest.php`

  - [ ] 5.2 Scrivere property test per la lettura singola del locale di default
    - **Property 18: Default locale is read from config at most once per request**
    - **Validates: Requirements 13.1**
    - Verificare che `config('app.locale')` venga invocato al massimo una volta per N chiamate a `getTranslatableFieldValue()`
    - File: `Modules/Core/tests/Unit/Helpers/HasTranslationsTest.php`

- [x] 6. Aggiungere static permission model cache in `AuthorizationService`
  - Aggiungere `private static array $permission_model_cache = []` (shape: `array<string, Permission>`) in `Modules/Core/app/Services/Authorization/AuthorizationService.php`
  - Modificare `getAclFilters()` per consultare la static map prima di chiamare `Permission::findByName()`
  - Aggiungere il metodo pubblico statico `resetPermissionCache(): void`
  - _Requirements: 9.1, 9.2, 9.3_

  - [ ]* 6.1 Scrivere property test per la cache del modello Permission
    - **Property 13: Permission model cache eliminates repeated findByName queries**
    - **Validates: Requirements 9.1, 9.2**
    - Verificare che la seconda chiamata a `getAclFilters()` con lo stesso permission name non emetta query DB per `Permission::findByName()`
    - File: `Modules/Core/tests/Unit/Services/AuthorizationServiceTest.php`

- [x] 7. Abilitare `Model::preventLazyLoading()` in `CoreServiceProvider` per ambienti local/testing
  - Modificare `configureModels()` in `Modules/Core/app/Providers/CoreServiceProvider.php`
  - Abilitare `Model::preventLazyLoading()` quando `app()->isLocal()` o `app()->runningUnitTests()` è true
  - Aggiungere supporto per la variabile d'ambiente `APP_PREVENT_LAZY_LOADING=true` che abilita il comportamento indipendentemente dall'ambiente
  - NON abilitare in `production` o `staging`
  - _Requirements: 10.1, 10.2, 10.3, 10.4, 10.5_

  - [ ]* 7.1 Scrivere property test per il lazy loading guard
    - **Property 14: Lazy loading detection throws in local/testing environments**
    - **Validates: Requirements 10.1, 10.2**
    - Verificare che l'accesso a una relazione non caricata lanci `LazyLoadingViolationException` quando il guard è attivo
    - File: `Modules/Core/tests/Unit/Providers/CoreServiceProviderTest.php`

- [ ] 8. Checkpoint — Verificare che tutti i test del Core passino
  - Eseguire `php artisan test --compact` sui test del modulo Core
  - Verificare PHPStan level max: `composer test:types`
  - Verificare 100% type coverage: `composer test:type-coverage`
  - Chiedere all'utente se ci sono domande prima di procedere.

- [x] 9. Aggiungere `toSearchableWith()` al modello `Content` per eliminare N+1 in batch indexing
  - Aggiungere il metodo `toSearchableWith(): array` in `Modules/CMS/app/Models/Content.php` che restituisce `['contributors', 'categories', 'tags', 'locations', 'translations', 'presettable.entity', 'presettable.preset']`
  - Verificare che `toSearchableArray()` acceda solo a relazioni già caricate (nessun lazy load)
  - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 9.1 Scrivere property test per `toSearchableArray()` senza lazy loading
    - **Property 6: Content toSearchableArray does not trigger lazy loading**
    - **Validates: Requirements 5.1, 5.3**
    - Verificare che con tutte le relazioni eager-loaded e `Model::preventLazyLoading()` attivo, `toSearchableArray()` non lanci `LazyLoadingViolationException`
    - File: `Modules/CMS/tests/Unit/Models/ContentTest.php`

- [x] 10. Aggiungere eager loading mancanti in `ContentResource::table()`
  - Modificare `modifyQueryUsing` in `Modules/CMS/app/Filament/Resources/Contents/ContentResource.php` per aggiungere `'translation'` (o `'translations'`), `'categories'`, e `'contributors'` alla lista di eager loads
  - Usare `modifyQueryUsing` senza modificare il `$with` base del modello
  - _Requirements: 12.1, 12.2, 12.3_

  - [ ]* 10.1 Scrivere property test per il caricamento della tabella Contents senza lazy loading
    - **Property 17: ContentResource table loads without lazy loading violations**
    - **Validates: Requirements 12.1, 12.2**
    - Verificare che il caricamento della tabella Filament Contents con `Model::preventLazyLoading()` attivo non lanci eccezioni
    - File: `Modules/CMS/tests/Feature/Filament/ContentResourceTest.php`

- [x] 11. Aggiungere cache layer in `NominatimService::performSearch()`
  - Modificare `Modules/CMS/app/Services/NominatimService.php` per cachare i risultati di geocoding usando una chiave derivata dai parametri normalizzati (query, city, province, country, limit)
  - Usare `CacheManager::key('geocoding', md5(serialize($params)))` come chiave
  - Aggiungere `geocoding.cache_ttl` in `Modules/CMS/config/config.php` con default `604800` (7 giorni)
  - NON cachare i fallimenti HTTP (restituire null senza scrivere in cache)
  - _Requirements: 7.1, 7.2, 7.3, 7.4, 7.5, 7.6_

  - [ ]* 11.1 Scrivere property test per la cache geocoding
    - **Property 8: Geocoding cache prevents redundant HTTP calls**
    - **Validates: Requirements 7.1, 7.2, 7.4**
    - Verificare che la seconda chiamata con gli stessi parametri non emetta richieste HTTP
    - File: `Modules/CMS/tests/Unit/Services/NominatimServiceTest.php`

  - [ ]* 11.2 Scrivere property test per il non-caching dei fallimenti
    - **Property 9: Failed geocoding requests are not cached**
    - **Validates: Requirements 7.5**
    - Verificare che dopo un fallimento HTTP la cache rimanga vuota per quella chiave
    - File: `Modules/CMS/tests/Unit/Services/NominatimServiceTest.php`

- [x] 12. Creare `GeocodeLocationJob` nel modulo CMS
  - Creare `Modules/CMS/app/Jobs/GeocodeLocationJob.php` che implementa `ShouldQueue`
  - Il job deve: accettare un `Location` model nel costruttore, impostare `$deleteWhenMissingModels = true`, usare la queue `geocoding`, avere `$tries = 3` con `$backoff = [30, 60, 120]`
  - Implementare il middleware `ThrottlesExceptions(1, 1)` per rispettare il rate limit di Nominatim (1 req/s)
  - Il metodo `handle()` deve chiamare `NominatimService` e aggiornare `$location->geolocation` con le coordinate risolte
  - Il metodo `failed()` deve loggare l'errore senza modificare le coordinate esistenti
  - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5, 8.6_

  - [ ]* 12.1 Scrivere property test per il dispatch asincrono del job
    - **Property 10: Location save dispatches geocoding job asynchronously**
    - **Validates: Requirements 8.1**
    - Verificare che il salvataggio di un `Location` con campi indirizzo non-vuoti dispatchi `GeocodeLocationJob` alla queue `geocoding` senza chiamare il servizio sincronamente
    - File: `Modules/CMS/tests/Unit/Jobs/GeocodeLocationJobTest.php`

  - [ ]* 12.2 Scrivere property test per l'aggiornamento coordinate al successo
    - **Property 11: Geocoding job updates Location coordinates on success**
    - **Validates: Requirements 8.4**
    - Verificare che `GeocodeLocationJob::handle()` aggiorni il campo `geolocation` del `Location` model
    - File: `Modules/CMS/tests/Unit/Jobs/GeocodeLocationJobTest.php`

  - [ ]* 12.3 Scrivere property test per la preservazione coordinate al fallimento
    - **Property 12: Geocoding job preserves coordinates on failure**
    - **Validates: Requirements 8.5**
    - Verificare che dopo tutti i retry falliti le coordinate originali del `Location` rimangano invariate
    - File: `Modules/CMS/tests/Unit/Jobs/GeocodeLocationJobTest.php`

- [ ] 13. Creare o aggiornare `LocationObserver` per dispatchare `GeocodeLocationJob`
  - Creare `Modules/CMS/app/Observers/LocationObserver.php` (o aggiornarlo se esiste)
  - Nel metodo `saved()`, se i campi indirizzo (`address`, `city`, `province`, `country`) sono stati modificati, dispatchare `GeocodeLocationJob::dispatch($location)`
  - Registrare l'observer nel `CMSServiceProvider` o tramite attributo `#[ObservedBy]` sul modello `Location`
  - _Requirements: 8.1_

- [ ] 14. Checkpoint — Verificare che tutti i test del CMS passino
  - Eseguire `php artisan test --compact` sui test del modulo CMS
  - Verificare PHPStan level max e type coverage per il modulo CMS
  - Chiedere all'utente se ci sono domande prima di procedere.

- [ ] 15. Correggere `AclResolverService::clearCacheForPermission()` con invalidazione mirata
  - Modificare `Modules/Core/app/Services/AclResolverService.php`
  - Cambiare la firma in `clearCacheForPermission(Permission $permission): void`
  - Implementare l'iterazione sugli utenti che hanno quel permesso e cancellare solo le loro chiavi ACL cache (`CacheManager::key('acl', 'user', $user_id, 'perm', $permission->id)`)
  - Aggiungere la soglia configurabile `config('core.acl.clear_threshold', 500)`: se il numero di utenti supera la soglia, fare flush solo delle chiavi con prefisso `acl:`
  - Aggiornare la costante `CACHE_PREFIX` per usare `CacheManager::key('acl', ...)` al posto della stringa piatta
  - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.5_

  - [ ]* 15.1 Scrivere property test per l'invalidazione mirata della cache ACL
    - **Property 2: ACL cache invalidation is targeted**
    - **Validates: Requirements 2.1, 2.3**
    - Verificare che `clearCacheForPermission()` su un permesso lasci intatte le cache entries degli altri N-1 permessi
    - File: `Modules/Core/tests/Unit/Services/AclResolverServiceTest.php`

  - [ ]* 15.2 Scrivere property test per il fallback alla soglia
    - **Property 3: ACL cache invalidation threshold fallback**
    - **Validates: Requirements 2.5**
    - Verificare che quando il numero di utenti supera la soglia, non vengano tentate cancellazioni individuali per chiave utente
    - File: `Modules/Core/tests/Unit/Services/AclResolverServiceTest.php`

- [ ] 16. Ottimizzare `AclResolverService::resolveAcls()` con batch query su `whereIn`
  - Modificare `resolveAcls()` in `Modules/Core/app/Services/AclResolverService.php` per caricare tutti gli ACL rilevanti in una singola query usando `whereIn` sugli ID dei ruoli dell'utente
  - Eager-load la relazione `permission.roles` nella query batch per evitare N+1 nel matching dei ruoli
  - Mantenere la logica di inheritance (role → ancestor fallback) dopo l'ottimizzazione della query
  - _Requirements: 17.1, 17.2, 17.3, 17.4_

  - [ ]* 16.1 Scrivere property test per la query batch ACL
    - **Property 23: ACL resolution uses a single batch query for multi-role users**
    - **Validates: Requirements 17.1, 17.2**
    - Verificare che per un utente con N ≥ 2 ruoli, `resolveAcls()` emetta al massimo una query DB per caricare gli ACL
    - File: `Modules/Core/tests/Unit/Services/AclResolverServiceTest.php`

  - [ ]* 16.2 Scrivere property test per la preservazione dei risultati ACL dopo l'ottimizzazione
    - **Property 24: ACL resolution results are preserved after batch optimization**
    - **Validates: Requirements 17.3**
    - Verificare che il set di ACL effettivi restituiti dopo l'ottimizzazione sia identico a quello dell'implementazione originale per-ruolo
    - File: `Modules/Core/tests/Unit/Services/AclResolverServiceTest.php`

- [ ] 17. Modificare `HandleModelIndexingListener` per forzare async in contesto web
  - Modificare `Modules/AI/app/Listeners/HandleModelIndexingListener.php`
  - Nel metodo `handle()`, quando `$event->sync === true`, verificare `app()->runningInConsole()`:
    - Se CLI: eseguire sincronamente (comportamento attuale)
    - Se web: dispatchare il job alla queue (ignorare il flag `sync`)
  - Mantenere il comportamento attuale per `$event->sync === false` (sempre async)
  - _Requirements: 14.1, 14.2, 14.3, 14.4_

  - [ ]* 17.1 Scrivere property test per il dispatch asincrono in contesto web
    - **Property 19: Web-context sync indexing is always dispatched asynchronously**
    - **Validates: Requirements 14.1**
    - Verificare che un evento con `sync = true` in contesto web dispatchi il job alla queue senza esecuzione inline
    - File: `Modules/Core/tests/Unit/Listeners/HandleModelIndexingListenerTest.php`

  - [ ]* 17.2 Scrivere property test per l'esecuzione sincrona in CLI
    - **Property 20: CLI-context sync indexing executes synchronously**
    - **Validates: Requirements 14.2**
    - Verificare che un evento con `sync = true` in contesto CLI esegua il job sincronamente
    - File: `Modules/Core/tests/Unit/Listeners/HandleModelIndexingListenerTest.php`

- [ ] 18. Aggiungere scope `forModel()` al modello `ModelEmbedding`
  - Modificare `Modules/Core/app/Models/ModelEmbedding.php` per aggiungere il metodo `scopeForModel(Builder $query, Model $model): Builder`
  - Lo scope deve filtrare su entrambi `model_type` e `model_id` per sfruttare l'indice composito `morphs()`
  - _Requirements: 15.1, 15.4_

  - [ ]* 18.1 Scrivere property test per lo scope `forModel`
    - **Property 21: ModelEmbedding forModel scope uses composite index**
    - **Validates: Requirements 15.4**
    - Verificare che `ModelEmbedding::forModel($model)` generi una query che filtra su entrambi `model_type` e `model_id`
    - File: `Modules/Core/tests/Unit/Models/ModelEmbeddingTest.php`

- [ ] 19. Creare il comando artisan `core:cache:warm`
  - Creare `Modules/Core/app/Console/WarmCacheCommand.php` con signature `core:cache:warm`
  - Il comando deve pre-popolare: tutti i `Setting` (tutti i gruppi), cron jobs, version strategies, permission existence map (tutti i nomi di `Permission` → `true`)
  - Ogni step deve essere wrappato in try/catch: se uno step fallisce, loggare l'errore e continuare
  - Il comando deve riportare il numero di cache entries popolate e il tempo impiegato
  - Uscire con `Command::FAILURE` solo se tutti gli step falliscono
  - _Requirements: 16.1, 16.2, 16.3_

  - [ ]* 19.1 Scrivere property test per l'idempotenza del comando
    - **Property 22: Cache warming command is idempotent**
    - **Validates: Requirements 16.3**
    - Verificare che eseguire `core:cache:warm` due volte produca lo stesso stato finale della cache
    - File: `Modules/Core/tests/Unit/Console/WarmCacheCommandTest.php`

- [ ] 20. Aggiungere `cache.warm_on_boot` in `config/core.php` e hook in `CoreServiceProvider`
  - Aggiungere la chiave `cache.warm_on_boot` (default: `false`) in `Modules/Core/config/config.php`
  - Modificare `CoreServiceProvider::boot()` per registrare un hook `$this->app->booted()` che, se `config('core.cache.warm_on_boot')` è `true`, esegue il warming tramite `WarmCacheCommand`
  - _Requirements: 16.4, 16.5_

- [ ] 21. Mitigare Issue A: aggiungere guard `Cache::supportsTags()` in `Repository`
  - Modificare `Modules/Core/app/Cache/Repository.php`
  - In `clearByEntity()`: wrappare la chiamata `$this->tags(...)` con un check `Cache::supportsTags()`; se i tag non sono supportati, usare `Cache::forget($model->getCacheKey())` come fallback
  - Applicare lo stesso pattern a `clearByRequest()`, `clearByUser()`, `clearByGroup()`
  - Applicare lo stesso pattern a `tryByRequest()` per la lettura con tag
  - _Requirements: Issue A (mitigazione)_

  - [ ]* 21.1 Scrivere integration test per `clearByEntity()` con driver database
    - Verificare che `clearByEntity()` non lanci `BadMethodCallException` quando il driver cache è `database`
    - File: `Modules/Core/tests/Unit/Cache/RepositoryTest.php`

  - [ ]* 21.2 Scrivere integration test per `clearByEntity()` con driver file
    - Verificare che `clearByEntity()` non lanci `BadMethodCallException` quando il driver cache è `file`
    - File: `Modules/Core/tests/Unit/Cache/RepositoryTest.php`

- [ ] 22. Checkpoint finale — Verificare che tutti i test passino
  - Eseguire `composer test` per l'intera pipeline (type-coverage, unit, lint, types)
  - Verificare PHPStan level max su tutti i moduli modificati
  - Verificare 100% type coverage
  - Chiedere all'utente se ci sono domande prima di considerare il lavoro completato.

---

## Notes

- I sub-task marcati con `*` sono opzionali e possono essere saltati per un MVP più rapido
- Ogni task referenzia i requisiti specifici per la tracciabilità
- I checkpoint garantiscono la validazione incrementale
- I property test validano le proprietà universali di correttezza definite nel design
- I test unitari validano esempi specifici e casi limite
- L'ordine di implementazione segue la dipendenza logica: le fondamenta (Task 1) devono essere completate prima dei task che usano `CacheManager::key()`
- Il Task 7 (`preventLazyLoading`) deve essere completato prima dei Task 9 e 10 per sfruttare il guard durante lo sviluppo
