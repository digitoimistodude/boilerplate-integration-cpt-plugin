# Mysaas integration
![project_created 4_Feb_2019](https://img.shields.io/badge/project_created-4_Feb_2019-blue.svg?style=flat-square) ![Tested_up_to WordPress_5.0.3](https://img.shields.io/badge/Tested_up_to-WordPress_5.0.3-blue.svg?style=flat-square) ![Compatible_with PHP_7.2](https://img.shields.io/badge/Compatible_with-PHP_7.2-green.svg?style=flat-square)

Integration to update items from SaaS service to site.

- [Features](#features)
- [Before starting to use](#before-starting-to-use)
- [Write to log](#write-to-log)
- [Hooks](#hooks)
	- [Disable debug log write](#disable-debug-log-write)
	- [Allow Simple History write detailed CPT log](#allow-simple-history-write-detailed-cpt-log)
- [Data structure](#data-structure)
- [Contributing](#contributing)
- [Contributors](#contributors)

## Features
- Page for doing manual sync
- Write detailed log file if `WP_DEBUG` is `true` to uploads folder
- Write detailed log to [Query Monitor](https://github.com/johnbillion/query-monitor) if `WP_DEBUG` is `true`
- Write informative messages to [Simple History](https://github.com/bonny/WordPress-Simple-History/)
- Prevent CPT updated/deleted messages from being recodred in Simple History, we have informative sync messages instead
- Use [Query Monitor profiling](https://github.com/johnbillion/query-monitor#profiling)
- Remove items from databse which no longer exist in REST API

## Before starting to use

Before you start using this plugin, do folowing earch & replaces:

- `Mysaas` => `Servicename`
- `MYSAAS` => `SERVICENAME`
- `mysaas` => `servicename`

and change following variables in plugin base file:

- `$cpt`
- `$api_base`
- `$api_key_name`
- `$item_uniq_id_key`

Also enable API key check in `classes/class-helper.php` if needed.

## Write to log

Writing to log is really simple, just use `Logging::log()` function. First parameter is the message, second is PSR-3 compatible level and third one is possible `WP_Error` instance for Query Monitor to use.

Simpliest way to log info level message is
```php
Logging::log( 'My message' );
```

Log and change the level
```php
Logging::log( 'My message', 'debug' );
```

Log and pass `WP_Error` instance
```php
Logging::log( 'My message', 'error', $insert );
```

## Hooks

### Disable debug log write

To prevent debug log from wring even when `WP_DEBUG` is `true`, use
`add_filter( 'mysaas_debug_log_write', '__return_false' );`

### Allow Simple History write detailed CPT log

By default this plugin prevents Simple History to record updated/deleted messages because we have informative sync messages instead. Allow it with
`add_filter( 'mysaas_disable_simple_history_cpt', '__return_false' );`

## Data structure

Plugin uses [REQ|RES](https://reqres.in/) service to get fake data before REST API is changed to more suitable one.

Document the data structure here.

## Contributing

If you have ideas about the plugin or spot an issue, please let us know. Thank you very much!

## Contributors

Plugin boilerplate is developed by Digitoimisto Dude Oy, a Finnish boutique digital agency in the center of Jyväskylä.
