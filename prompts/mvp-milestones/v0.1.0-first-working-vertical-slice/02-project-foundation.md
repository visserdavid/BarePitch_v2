# Project Foundation

# Purpose
Bootstrap the repository and project skeleton: directory structure, PHP autoloading, routing, error handling, environment configuration, and PDO database connection. This is the structural foundation every other prompt builds on.

# Required Context
See `01-shared-context.md`. This is the first prompt in the bundle — no prior state exists. Start from an empty repository (or verify the current state before proceeding).

# Required Documentation
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — directory structure and layer rules
- `docs/BarePitch-v2-12-ai-implementation-rules-v1.0.md` — implementation constraints

# Scope

## Directory Structure

Create the following directory tree (create empty `.gitkeep` files for empty directories):

```
public/
  index.php          ← front controller
  .htaccess          ← Apache URL rewriting
  css/
  js/
app/
  Http/
    Controllers/
    Requests/
    Middleware/
    Helpers/
  Policies/
  Services/
  Repositories/
  Domain/
    Enums/
    Exceptions/
  Views/
    layouts/
    errors/
config/
  app.php
database/
  migrations/
  seeds/
scripts/
tests/
  Unit/
  Integration/
.env.example
composer.json
```

## Environment Configuration

**`.env.example`** — template with all required variables:
```
APP_ENV=local
APP_URL=http://localhost:8000
APP_NAME=BarePitch

DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=barepitch
DB_USER=root
DB_PASS=
```

**`config/app.php`** — loads `.env` using a simple parser (or `vlucas/phpdotenv` if composer is already set up):
```php
// Return config array or set constants
define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('APP_URL', $_ENV['APP_URL'] ?? '');
// DB constants loaded here
```

## PHP Autoloader (`composer.json`)

```json
{
  "name": "barepitch/barepitch",
  "require": {
    "php": "^8.1"
  },
  "require-dev": {
    "phpunit/phpunit": "^10.0"
  },
  "autoload": {
    "psr-4": {
      "App\\": "app/"
    }
  }
}
```

Run `composer install` to generate the autoloader.

## PDO Database Connection (`app/Repositories/Database.php`)

```php
class Database {
    private static ?PDO $connection = null;

    public static function connection(): PDO {
        if (self::$connection === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$connection = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }
        return self::$connection;
    }
}
```

**Never** use raw string interpolation in SQL. Always use `prepare()` + `execute()`.

## Router (`app/Http/Router.php`)

A minimal router that maps `METHOD /path` to controller actions:

```php
class Router {
    private array $routes = [];

    public function get(string $path, callable|array $handler): void { ... }
    public function post(string $path, callable|array $handler): void { ... }
    public function dispatch(string $method, string $uri): void { ... }
}
```

Support simple path parameters: `/matches/{id}` → `['id' => '123']`.

## Front Controller (`public/index.php`)

```php
<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Load config and environment
require_once __DIR__ . '/../config/app.php';

// Error handler — no stack traces to end user
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    if (APP_ENV !== 'local') {
        http_response_code(500);
        require __DIR__ . '/../app/Views/errors/500.php';
        exit;
    }
    return false; // default handler in local
});

set_exception_handler(function(\Throwable $e) {
    error_log($e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (APP_ENV !== 'local') {
        http_response_code(500);
        require __DIR__ . '/../app/Views/errors/500.php';
        exit;
    }
    throw $e;
});

// Bootstrap and dispatch
$router = new \App\Http\Router();
require_once __DIR__ . '/../routes/web.php';
$router->dispatch($_SERVER['REQUEST_METHOD'], parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
```

## Apache `.htaccess`

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

## Error Views

Create minimal views:
- `app/Views/errors/500.php` — "An error occurred. Please try again."
- `app/Views/errors/403.php` — "Access denied."
- `app/Views/errors/404.php` — "Page not found."

No stack traces, no debug info visible to end users in production.

## Route file (`routes/web.php`)

Start with a single route proving the foundation works:
```php
$router->get('/', [\App\Http\Controllers\HomeController::class, 'index']);
```

`HomeController::index()` returns a simple HTML response: `<h1>BarePitch</h1>`.

# Out of Scope
- Authentication, session management, CSRF (v0.2.0)
- Database schema and migrations (prompt 03)
- Views, business logic, any feature (later prompts)

# Architectural Rules
- No business logic in `config/` or `public/index.php`
- No SQL in the front controller or router
- Error handler must prevent stack trace exposure in non-local environments
- PDO must be configured with `ERRMODE_EXCEPTION` and `EMULATE_PREPARES=false`

# Acceptance Criteria
- Root URL (`GET /`) renders an HTML page containing "BarePitch"
- `composer install` runs without errors
- PDO connection helper resolves against the configured database without errors
- Missing or invalid `.env` produces a clear startup error, not a cryptic crash
- PHP syntax check passes on all created PHP files
- Error handler is in place — exceptions in production render the error view, not a stack trace

# Verification
- PHP syntax check: `find . -name "*.php" -not -path "./vendor/*" -exec php -l {} \;`
- Start server: `php -S localhost:8000 -t public/`
- Visit `http://localhost:8000/` — confirm HTML response with "BarePitch"
- Confirm PDO connects: add a temporary DB ping in `HomeController` and remove it after

# Handoff Note
`03-database-foundation.md` installs the complete database schema using the PDO connection established here.
