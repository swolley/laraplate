# Cursor Rules Configuration

Questa cartella contiene le regole di configurazione per Cursor, organizzate in file tematici per una migliore gestione e manutenzione.

## Formato File

I file di configurazione utilizzano l'estensione `.mdc` (Markdown Configuration) per una migliore integrazione con Cursor e per seguire le best practices della community.

## Struttura dei File

### 01-php-laravel-standards.mdc
- Standard PHP e Laravel
- Convenzioni di naming
- Dichiarazioni di tipo
- Dipendenze e versioni

### 02-architecture-patterns.mdc
- Pattern di design (Singleton, Factory, Middleware, Controller)
- Best practices Laravel
- Architettura del codice (Controller, Model, Services, Routing)
- Punti chiave architetturali

### 03-performance-optimization.mdc
- Strategie di caching
- Elaborazione in background
- Ottimizzazione database
- Ottimizzazione del codice
- Monitoraggio e debugging

### 04-error-handling-security.mdc
- Gestione degli errori
- Best practices di sicurezza
- Validazione e integrità dei dati

### 05-testing-development.mdc
- Strategie di testing
- Strumenti di sviluppo
- Qualità del codice
- Workflow di sviluppo
- Organizzazione del codice

### 06-coding-principles.mdc
- Principi generali di coding
- Modifiche al codice e bug fix
- Struttura del codice
- Lingua e comunicazione
- Contesto e requisiti

### 07-laraplate-specific.mdc
- Architettura modulare Laravel
- Standard di sviluppo moduli
- Integrazione Filament
- Strumenti di sviluppo e script
- Strategie di testing specifiche
- Gestione database e migrazioni
- Configurazione e API
- Performance e sicurezza
- Organizzazione del codice
- Deployment e CI/CD

## Utilizzo

Queste regole vengono applicate automaticamente da Cursor durante lo sviluppo per garantire:
- Consistenza nel codice
- Aderenza agli standard PHP/Laravel
- Best practices di sicurezza e performance
- Qualità e manutenibilità del codice

## Manutenzione

Per modificare le regole:
1. Apri il file appropriato nella cartella `.cursor/rules/`
2. Modifica le regole secondo necessità
3. Salva il file
4. Le modifiche saranno applicate automaticamente nelle prossime sessioni di Cursor 