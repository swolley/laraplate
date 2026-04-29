<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://raw.githubusercontent.com/swolley/images/refs/heads/master/logo_laraplate.png?raw=true" width="400" alt="Laraplate Logo" />
    </a>
</p>
<p>&nbsp;</p>

> ⚠️ **Caution**: This package is a **work in progress**. **Don't use this in production or use at your own risk**—no guarantees are provided... or better yet, collaborate with me to create the definitive Laravel boilerplate; that's the right place to instroduce your ideas. Let me know your ideas...

## About Laraplate

### Requirements

-   PHP >= 8.5
-   Laravel 12.x
-   Composer merge-plugin for modular loading (`composer.*.json`, `Modules/*/composer.json`)
-   Node.js for Vite build (`npm run dev` / `npm run build`)

### Core Dependencies (project root)

-   `laravel/framework` ^12
-   `laravel/sanctum` ^4 for API auth
-   `nwidart/laravel-modules` ^12 (<12.0.4) + `coolsam/modules` + `joshbrw/laravel-module-installer` for modular structure
-   `spatie/fork` for parallel tasks
-   `symfony/http-client` for HTTP integrations

### Install

```sh
# vendor packages
composer install

# db prepare
php artisan migrate:fresh
php artisan db:seed
php artisan module:seed --all
```

### Laraplate Modules

-   ⚡ **Core**: main common boilerplate functionalities. About [Core Module](https://github.com/swolley/laraplate-core).
-   ✎ **CMS**: common content management functionalities. About [CMS module](https://github.com/swolley/laraplate-cms).
-   ✨ **AI**: artificial intelligence capabilities (embeddings, vector search, automatic translation). About [AI Module](https://github.com/swolley/laraplate-ai).

### Environment (principali variabili)

-   `APP_*`: ambiente, URL, porta, localizzazione, logo.
-   `DB_*`: connessione (default `pgsql`), host/porta, credenziali.
-   `SESSION_*`: driver (default `redis`), lifetime e dominio.
-   `CACHE_STORE`, `QUEUE_CONNECTION`, `FILESYSTEM_DISK`, `BROADCAST_CONNECTION`: store/driver predefiniti (failover/redis/local/log).
-   `REDIS_*`, `MEMCACHED_HOST`: configurazioni cache/sessioni.
-   `LOG_*`: canale/stack/livello log.
-   `MAIL_*`, `AWS_*`: mail e S3 (commentati di default).
-   `ELASTIC_*`, `SCOUT_QUEUE`: ricerca avanzata (commentati o disattivati di default).
-   `OPENAI_API_KEY`: chiave AI (commentata).
-   Core toggles: `ENABLE_USER_REGISTRATION`, `ENABLE_SOCIAL_LOGIN`, `ENABLE_USER_LICENSES`, `ENABLE_USER_2FA`, `VERIFY_NEW_USER`, `ENABLE_DYNAMIC_ENTITIES`, `ENABLE_DYNAMIC_GRIDUTILS`, `EXPOSE_CRUD_API`, `FORCE_HTTPS`, `SOFT_DELETES_EXPIRATION_DAYS`, `VECTOR_SEARCH_ENABLED`, `VECTOR_SEARCH_PROVIDER`.

<br>
<br>

<p align="center">
	<a href="https://laravel.com" target="_blank">
		<img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo">
	</a>
</p>

## About Laravel

### Framework

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

-   [Simple, fast routing engine](https://laravel.com/docs/routing).
-   [Powerful dependency injection container](https://laravel.com/docs/container).
-   Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
-   Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
-   Database agnostic [schema migrations](https://laravel.com/docs/migrations).
-   [Robust background job processing](https://laravel.com/docs/queues).
-   [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

### Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

You may also try the [Laravel Bootcamp](https://bootcamp.laravel.com), where you will be guided through building a modern Laravel application from scratch.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## License

Laraplate and its modules are open-sourced software licensed under the [GNU AGPL v3](https://www.gnu.org/licenses/agpl-3.0.html).

## TODO and FIXME

For a complete and detailed list of TODOs, read:
- [Core Module TODO](Modules/Core/README.md#todo-and-fixme)
- [CMS Module TODO](Modules/CMS/README.md#todo-and-fixme)
- [AI Module TODO](Modules/AI/README.md#todo-and-fixme)