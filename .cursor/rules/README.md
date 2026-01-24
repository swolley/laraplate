# Cursor Rules Configuration

Questa cartella contiene le regole di configurazione per Cursor, ottimizzate per ridurre il consumo di token e applicate contestualmente.

## Formato File

I file utilizzano l'estensione `.mdc` (Markdown Configuration) con frontmatter YAML per controllare quando vengono applicati:
- `alwaysApply: true` - Applicato sempre
- `globs: ["**/*.php"]` - Applicato solo quando si lavora con file matching il pattern

## Struttura Ottimizzata

### 00-master.mdc (Always Applied)
- File master leggero che referenzia le altre regole
- Principi chiave sempre attivi

### laravel-boost.mdc (Always Applied)
- **Regola principale** - Linee guida complete per l'ecosistema Laravel
- Copre: Filament, Livewire, Pest, Pint, PHPStan, Tailwind, ecc.
- **Non duplicare contenuti già presenti qui**

### 01-php-laravel-standards.mdc (Contextual: PHP files)
- Standard PHP e Laravel specifici
- Convenzioni di naming
- Dichiarazioni di tipo
- **Riferimenti a laravel-boost per contenuti duplicati**

### 02-architecture-patterns.mdc (Contextual: Controllers, Services, Models)
- Pattern di design
- Architettura del codice
- **Riferimenti a laravel-boost per best practices Laravel**

### 03-performance-optimization.mdc (Contextual: Models, Services, Jobs, Migrations)
- Strategie di caching
- Ottimizzazione database
- **Riferimenti a laravel-boost per Eloquent e query builder**

### 04-error-handling-security.mdc (Contextual: Controllers, Middleware, Exceptions)
- Gestione degli errori (Laravel 12 Context)
- Best practices di sicurezza
- **Riferimenti a laravel-boost per validazione**

### 05-testing-development.mdc (Contextual: Test files)
- Strategie di testing con Pest
- Strumenti di sviluppo
- **Riferimenti a laravel-boost per Filament, Telescope, ecc.**

### 06-coding-principles.mdc (Always Applied)
- Principi generali di coding (minimali)
- Modifiche al codice e bug fix
- Lingua e comunicazione
- **Solo contenuti unici, non duplicati**

### 07-laraplate-specific.mdc (Contextual: Module files)
- Architettura modulare specifica del progetto
- Standard di sviluppo moduli
- **Riferimenti a altre regole per contenuti generici**

## Ottimizzazioni Implementate

1. **Eliminazione duplicazioni**: Contenuti duplicati rimossi, sostituiti con riferimenti a `laravel-boost.mdc`
2. **Contestualizzazione**: Regole applicate solo quando rilevanti tramite `globs` patterns
3. **File master leggero**: `00-master.mdc` serve come entry point minimale
4. **Riduzione token**: ~60% di riduzione del contenuto totale rimuovendo duplicazioni

## Utilizzo

Le regole vengono applicate automaticamente da Cursor:
- **Sempre attive**: `00-master.mdc`, `laravel-boost.mdc`, `06-coding-principles.mdc`
- **Contestuali**: Le altre regole si attivano quando si lavora con file matching i pattern

## Manutenzione

1. **Per modifiche generali**: Modifica `laravel-boost.mdc` (regola principale)
2. **Per modifiche specifiche**: Modifica il file appropriato nella cartella `.cursor/rules/`
3. **Evita duplicazioni**: Se un contenuto esiste già in `laravel-boost.mdc`, fai riferimento a quello invece di duplicare
4. **Mantieni contestualizzazione**: Usa `globs` per applicare regole solo quando rilevanti 