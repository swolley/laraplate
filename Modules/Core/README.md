<p>&nbsp;</p>
<p align="center">
	<a href="https://github.com/swolley" target="_blank">
		<img src="https://github.com/swolley/images/blob/master/swolley-1.jpg?raw=true" />
    </a>
</p>
<p>&nbsp;</p>

## Table of Contents

-   [Description](#description)
-   [Installation](#installation)
-   [Configuration](#configuration)
-   [Features](#features)
-   [Contributing](#contributing)
-   [License](#license)

## Description

The Core Module contains all the necessary functionalities to build a new Laravel application.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

Add repository to your `composer.json` file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core
```

Then, you can install the module by running the following command:

```bash
php artisan module:install Core
```

## Configuration

```env
#users
VERIFY_NEW_USER=true							#enables email verification for new users
ENABLE_USER_REGISTRATION=true					#enables user registration
ENABLE_SOCIAL_LOGIN=true						#enables social login
ENABLE_USER_LICENSE=false						#enables user licenses

#locking
LOCKIN_LOCK_VERSION_COLUMN=lock_version			#column name for the lock version
LOCKIN_LOCK_AT_COLUMN=locked_at					#column name for the lock date
LOCKIN_LOCK_BY_COLUMN=locked_user_id			#column name for the lock user id
LOCKIN_UNLOCK_ALLOWED=true						#enables unlock of locked objects
LOCKING_CAN_BE_UNLOCKED=						#comma separated list of user ids that can unlock locked objects
LOCKING_PREVENT_MODIFICATIONS_ON_LOCKED=false	#prevents modifications on locked objects
LOCKING_PREVENT_MODIFICATIONS_TO_LOCKED=false	#prevents modifications to locked objects

#entities
ENABLE_DYNAMIC_ENTITIES=false					#enables dynamic entities
ENABLE_DYNAMIC_GRIDUTILS=false					#enables dynamic gridutils
EXPOSE_CRUD_API=false							#enables CRUD API

#https
FORCE_HTTPS=false								#enables HTTPS

#logging
GRAYLOG_LEVEL=error								#log level for graylog
GRAYLOG_URL=									#graylog url
GRAYLOG_PORT=12201								#graylog port

#permission
PERMISSION_MODEL=Modules\Core\Models\Permission	#permission model
ROLE_MODEL=Modules\Core\Models\Role				#role model

#queues
HORIZON_DOMAIN=									#horizon domain
HORIZON_PATH=									#horizon path
HORIZON_PREFIX=									#horizon prefix

#ai
OPENAI_API_KEY=									#openai api key

#social login
FACEBOOK_CLIENT_ID=								#facebook client id
FACEBOOK_CLIENT_SECRET=							#facebook client secret
X_CLIENT_ID=										#x client id
X_CLIENT_SECRET=									#x client secret
LINKEDIN_OPENID_CLIENT_ID=						#linkedin openid client id
LINKEDIN_OPENID_CLIENT_SECRET=					#linkedin openid client secret
GOOGLE_CLIENT_ID=								#google client id
GOOGLE_CLIENT_SECRET=							#google client secret
GITHUB_CLIENT_ID=								#github client id
GITHUB_CLIENT_SECRET=							#github client secret
GITLAB_CLIENT_ID=								#gitlab client id
GITLAB_CLIENT_SECRET=							#gitlab client secret
BITBUCKET_CLIENT_ID=							#bitbucket client id
BITBUCKET_CLIENT_SECRET=						#bitbucket client secret
SLACK_CLIENT_ID=								#slack client id
SLACK_CLIENT_SECRET=							#slack client secret
SLACK_OPENID_CLIENT_ID=						#slack openid client id
SLACK_OPENID_CLIENT_SECRET=						#slack openid client secret
SOCIALITE_REDIRECT=							#socialite redirect
```

### Versioning configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasVersions;

class ... extends Model
{
    use HasVersions;

	protected $versionable = [ /** versionable fields */ ];
	protected $dontVersionable = [ /** fields not versionable */ ];
}
```

### Elasticsearch configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Cache\Searchable;

class ... extends Model
{
    use Searchable {
        prepareElasticDocument as prepareElasticDocumentTrait;
    }

	protected function prepareElasticDocument(): array
    {
		// get default model data if you like
		$document = $this->prepareElasticDocumentTrait();
        // add your customizations
		return $document + [
            'id' => 'keyword',
            'title' => 'text',
            'published' => 'boolean',
            'created_at' => 'date',
			// ...
        ];
    }
}
```

### Validations configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidations;

class ... extends Model
{
    use HasValidations;

	protected $rules = [
		'create' => [ /** rules for create */ ],
		'update' => [ /** rules for update */ ],
		'always' => [ /** rules for always */ ],
	];
}
```

### Validity configuration

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Core\Helpers\HasValidity;

class ... extends Model
{
    use HasValidity;

	protected static $valid_from_column = 'valid_from';
	protected static $valid_to_column = 'valid_to';
}
```

If you need to override the Core Module or dependencies configs you can publish them by running the following command:

## Features

### Requirements

-   PHP >= 8.3
-   Laravel 11
-   **PHP Extensions:**

    -   `ext-curl`: Provides support for URL requests.
    -   `ext-json`: Enables JSON serialization and deserialization.
    -   `ext-redis`: Allows interaction with Redis databases.
    -   `ext-pcntl`: Provides process control functions.
    -   `ext-posix`: Offers access to POSIX functions.
    -   `ext-intl`: Provides internationalization services.

### Installed Packages

The Core Module utilizes several packages to enhance its functionality. Below is a list of the key packages included in the `composer.json` file:

-   **Database and ORM:**

    -   [doctrine/dbal](https://github.com/doctrine/dbal): A database abstraction layer for PHP.
    -   [laravel/fortify](https://github.com/laravel/fortify): Provides authentication features for Laravel applications.
    -   [overtrue/laravel-versionable](https://github.com/overtrue/laravel-versionable): Adds versioning capabilities to Eloquent models.
    -   [spatie/laravel-permission](https://github.com/spatie/laravel-permission): Manages user roles and permissions.

-   **Logging and Monitoring:**

    -   [hedii/laravel-gelf-logger](https://github.com/hedii/laravel-gelf-logger): Sends logs to Graylog using the GELF format.
    -   [laravel/horizon](https://github.com/laravel/horizon): Provides a dashboard and code-driven configuration for Laravel queues.

-   **User Management:**

    -   [lab404/laravel-impersonate](https://github.com/404labfr/laravel-impersonate): Allows user impersonation for testing and support.
    -   [stephenlake/laravel-approval](https://github.com/stephenlake/laravel-approval): Implements an approval workflow for models.

-   **API and Documentation:**

    -   [wotz/laravel-swagger-ui](https://github.com/wotzebra/laravel-swagger-ui): Generates Swagger UI documentation for your API.
    -   [mtrajano/laravel-swagger](https://github.com/mtrajano/laravel-swagger): Provides Swagger annotations for Laravel.

-   **Search and Caching:**

    -   [elasticsearch/elasticsearch](https://github.com/elasticsearch/elasticsearch): Integrates Elasticsearch for advanced search capabilities.
    -   [theodo-group/llphant](https://github.com/theodo-group/llphant): Provides a library for handling Elasticsearch indexing.

-   **Development and Testing:**

    -   [pestphp/pest](https://github.com/pestphp/pest): A testing framework for PHP.
    -   [pestphp/pest-plugin-laravel](https://github.com/pestphp/pest-plugin-laravel): Adds Laravel-specific testing features to Pest.

### Additional Functionalities

The Core Module includes built-in features such as:

-   User management with multi-level roles and permissions.
-   Email verification for new users.
-   Command-line tools for user registration and model management.
-   Redis caching for improved performance.
-   Automatic indexing of entities with Elasticsearch and OpenAI support for tokenization.
-   Enhanced Swagger documentation generation.
-   Utilities for translations and model versioning.
-   Support for Laravel Octane and Horizon for improved performance and queue management.
-   Multi entities and connections crud endpoints with standardized requests parameters
-   Multi entities and connections exposed interactive grid endpoints
-   Strongly validated requests for Core routes
-   Default common Response Formatter
-   App settings and configurable Cron-Jobs on db tables
-   Automatic localization set on user request
-   Preview middleware for pending models approvals and multiuser approval system
-   Models versioning functionalities, with rollback functionalities
-   Dynamic entities for non mapped models
-   Dynamic gridutils for non mapped models
-   CRUD API for non mapped models
-   User licenses
-   Locking system for models
-   Graylog logging

### Other References

Core Module takes inspiration from, but does not directly require, libraries such as:

-   [sfolador/laravel-locked](https://github.com/sfolador/laravel-locked)
-   [reshadman/laravel-optimistic-locking](https://github.com/reshadman/laravel-optimistic-locking)
-   [vicgutt/laravel-inspect-db](https://github.com/VicGUTT/laravel-inspect-db)

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or correction.
3. Send a pull request.

## License

Core Module is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
