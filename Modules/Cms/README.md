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

The Cms Module contains all the necessary functionalities to build a new Cms system.

## Installation

If you want to add this module to your project, you can use the `joshbrw/laravel-module-installer` package.

Laraplate-cms is dependent on laraplate-core. Add both the repositories to your `composer.json` file:

```json
"repositories": [
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-core.git"
    }
    {
        "type": "composer",
        "url": "https://github.com/swolley/laraplate-cms.git"
    }
]
```

```bash
composer require joshbrw/laravel-module-installer swolley/laraplate-core swolley/laraplate-cms
```

Then, you can install the module by running the following command:

```bash
php artisan module:install Core
php artisan module:install Cms
```

## Configuration

```env
CMS_SLUGGER='\Illuminate\Support\Str::slug'	#common slugger function for entities and content types

MEDIA_DISK='public'							#disk name for media files
MEDIA_QUEUE='sync'							#queue name for media files
QUEUE_CONVERSIONS_BY_DEFAULT=true			#queue conversions by default
QUEUE_CONVERSIONS_AFTER_DB_COMMIT=true		#queue conversions after database commit
MAX_FILE_SIZE=1024*1024*10					#max file size for media files
IMAGE_DRIVER=gd								#image driver for media files
FFMPEG_PATH='/usr/bin/ffmpeg'				#ffmpeg path for media files
FFPROBE_PATH='/usr/bin/ffprobe'				#ffprobe path for media files
MEDIA_DOWNLOADER_SSL=true					#enable SSL for media downloads
ENABLE_MEDIA_LIBRARY_VAPOR_UPLOADS=false	#enable vapor uploads for media files
FORCE_MEDIA_LIBRARY_LAZY_LOADING=true		#force lazy loading for media files
```

## Features

### Requirements

-   PHP >= 8.3
-   Laravel 11
-   **PHP Extensions:**

    -   `ext-gd`: Provides support for image processing.
    -   `ext-imagick` or `ext-gmagick`: Provides support for additional image processing.

### Installed Packages

The Cms Module utilizes several packages to enhance its functionality. Below is a list of the key packages included in the `composer.json` file:

-   **Parent-Child Relationships:**

    -   [tightenco/parental](https://github.com/tightenco/parental): Provides a way to manage parent-child relationships in models.

-   **Media Management:**

    -   [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary): Provides a way to manage media files in models.

### Additional Functionalities

The Cms Module includes built-in features such as:

-   Dynamic Content types management
-   Media library management
-   Models tagging
-   Content versioning
-   Authors and signatures management
-   Export Templates management in blade format
-   AI integration

### Other References

Cms Module takes inspiration from, but does not directly require, libraries such as:

-   [spatie/laravel-tags](https://github.com/spatie/laravel-tags): Provides a way to manage tags in models.

## Contributing

If you want to contribute to this project, follow these steps:

1. Fork the repository.
2. Create a new branch for your feature or correction.
3. Send a pull request.

## License

Core Module is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
