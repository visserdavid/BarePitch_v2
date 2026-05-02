# Internationalization Foundation — v0.9.0

# Purpose
Establish the minimal i18n infrastructure required for MVP: a translation file structure, a `__()` helper function, locale selection, a fallback language, and replacement of all hardcoded visible UI strings in touched MVP screens with translation keys. This is NOT a full multi-language UI — it is the foundation that makes the UI translatable without future breakage.

---

# Required Context
See `01-shared-context.md`. All prior milestones must be complete. This prompt touches every view file that was modified or created during this milestone bundle. The statistics views from `02-basic-statistics.md` must already use `__()` before this prompt is marked done.

---

# Required Documentation
- `docs/BarePitch-v2-03-system-architecture-v1.0.md` — where helpers live; no business logic in views
- `docs/BarePitch-v2-06-mvp-scope-v1.0.md` — i18n scope for MVP (foundation only, not full multi-language)
- `docs/BarePitch-v2-09-ui-interaction-specifications-v1.0.md` — UI label inventory

---

# Scope

## 1. Translation File Structure

**Directory**: `resources/lang/` (create if it does not exist)

**Default language file**: `resources/lang/en.php`

This file returns a flat associative array of translation keys to English strings:
```php
<?php
// resources/lang/en.php
return [
    // General
    'app.name'                   => 'BarePitch',
    'nav.dashboard'              => 'Dashboard',
    'nav.players'                => 'Players',
    'nav.matches'                => 'Matches',
    'nav.statistics'             => 'Statistics',
    'nav.logout'                 => 'Log out',
    'nav.select_team'            => 'Select team',

    // Actions
    'action.save'                => 'Save',
    'action.cancel'              => 'Cancel',
    'action.delete'              => 'Delete',
    'action.edit'                => 'Edit',
    'action.create'              => 'Create',
    'action.confirm'             => 'Confirm',
    'action.back'                => 'Back',

    // Auth
    'auth.login'                 => 'Log in',
    'auth.login_heading'         => 'Sign in to BarePitch',
    'auth.email_label'           => 'Email address',
    'auth.email_placeholder'     => 'your@email.com',
    'auth.login_link_sent'       => 'Check your email for a login link.',
    'auth.link_expired'          => 'This login link is invalid or has expired.',
    'auth.no_access'             => 'You do not have access to any team.',
    'auth.select_team_heading'   => 'Select a team',

    // Players
    'players.heading'            => 'Players',
    'players.add'                => 'Add player',
    'players.name'               => 'Name',
    'players.number'             => 'Squad number',
    'players.position'           => 'Position',
    'players.status'             => 'Status',
    'players.active'             => 'Active',
    'players.inactive'           => 'Inactive',
    'players.no_players'         => 'No players found.',

    // Matches
    'matches.heading'            => 'Matches',
    'matches.create'             => 'New match',
    'matches.opponent'           => 'Opponent',
    'matches.date'               => 'Date',
    'matches.state'              => 'Status',
    'matches.state.planned'      => 'Planned',
    'matches.state.prepared'     => 'Prepared',
    'matches.state.active'       => 'Active',
    'matches.state.finished'     => 'Finished',
    'matches.no_matches'         => 'No matches found.',
    'matches.score'              => 'Score',
    'matches.home'               => 'Home',
    'matches.away'               => 'Away',

    // Match preparation
    'preparation.heading'        => 'Prepare match',
    'preparation.attendance'     => 'Attendance',
    'preparation.lineup'         => 'Lineup',
    'preparation.formation'      => 'Formation',
    'preparation.prepare_action' => 'Prepare match',
    'preparation.present'        => 'Present',
    'preparation.absent'         => 'Absent',
    'preparation.injured'        => 'Injured',

    // Live match
    'live.heading'               => 'Live match',
    'live.start'                 => 'Start match',
    'live.finish'                => 'Finish match',
    'live.add_event'             => 'Add event',
    'live.goal'                  => 'Goal',
    'live.assist'                => 'Assist',
    'live.yellow_card'           => 'Yellow card',
    'live.red_card'              => 'Red card',
    'live.substitution'          => 'Substitution',
    'live.minute'                => 'Minute',
    'live.player'                => 'Player',

    // Statistics
    'stats.players_heading'      => 'Player statistics',
    'stats.team_heading'         => 'Team statistics',
    'stats.matches_played'       => 'Matches played',
    'stats.goals'                => 'Goals',
    'stats.assists'              => 'Assists',
    'stats.yellow_cards'         => 'Yellow cards',
    'stats.red_cards'            => 'Red cards',
    'stats.playing_time'         => 'Playing time',
    'stats.attendance_pct'       => 'Attendance %',
    'stats.wins'                 => 'Wins',
    'stats.draws'                => 'Draws',
    'stats.losses'               => 'Losses',
    'stats.goals_for'            => 'Goals for',
    'stats.goals_against'        => 'Goals against',
    'stats.goal_difference'      => 'Goal difference',
    'stats.no_data'              => 'No statistics available yet.',
    'stats.filter_season'        => 'Season',
    'stats.filter_phase'         => 'Phase',
    'stats.filter_all'           => 'All',
    'stats.apply_filter'         => 'Apply',

    // Errors
    'error.403'                  => 'You do not have permission to do that.',
    'error.404'                  => 'Page not found.',
    'error.500'                  => 'Something went wrong. Please try again.',
    'error.csrf'                 => 'Your session may have expired. Please try again.',

    // Livestream
    'livestream.heading'         => 'Live match updates',
    'livestream.share'           => 'Share link',
    'livestream.stop'            => 'Stop livestream',
    'livestream.expired'         => 'This livestream has ended.',
    'livestream.no_events'       => 'No events yet.',

    // Admin
    'admin.heading'              => 'Administration',
    'admin.clubs'                => 'Clubs',
    'admin.seasons'              => 'Seasons',
    'admin.phases'               => 'Phases',
    'admin.teams'                => 'Teams',
    'admin.users'                => 'Users',
    'admin.roles'                => 'Roles',
];
```

This list is a starting point. Add keys for any string found in a touched MVP view that is not listed here. Do not remove keys once added.

---

## 2. Translation Helper

**File**: `app/Helpers/TranslationHelper.php` (or `app/helpers.php` if a global helpers file already exists)

```php
<?php

/**
 * Translate a key to the current locale string.
 * Falls back to the default locale if the key is missing in the current locale.
 * Falls back to the key itself if missing in all locales.
 *
 * @param string $key      Translation key, e.g. 'nav.players'
 * @param array  $replace  Optional named replacements, e.g. ['name' => 'David']
 * @return string
 */
function __(string $key, array $replace = []): string
{
    static $translations = [];
    static $fallback = [];

    $locale = currentLocale();
    $defaultLocale = config('app.locale', 'en');

    if (!isset($translations[$locale])) {
        $path = BASE_PATH . '/resources/lang/' . $locale . '.php';
        $translations[$locale] = file_exists($path) ? require $path : [];
    }

    if (!isset($fallback[$defaultLocale])) {
        $path = BASE_PATH . '/resources/lang/' . $defaultLocale . '.php';
        $fallback[$defaultLocale] = file_exists($path) ? require $path : [];
    }

    $string = $translations[$locale][$key]
        ?? $fallback[$defaultLocale][$key]
        ?? $key;

    foreach ($replace as $search => $value) {
        $string = str_replace(':' . $search, htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'), $string);
    }

    return $string;
}
```

Notes:
- `BASE_PATH` must be defined in the application bootstrap (it should already exist from v0.1.0)
- `config()` must be a working config helper; if it does not exist, use `$_ENV['APP_LOCALE'] ?? 'en'` as the fallback
- The `$replace` parameter supports named placeholders in translation strings, e.g., `'welcome' => 'Welcome, :name'` → `__('welcome', ['name' => $user])`
- The function is intentionally minimal; do not add caching layers, pluralization, or locale negotiation from HTTP headers in this milestone

---

## 3. Locale Selection

**File**: `app/Helpers/LocaleHelper.php` (or add to the helpers file)

```php
<?php

/**
 * Return the current locale string.
 * Source priority: session > config > 'en'
 */
function currentLocale(): string
{
    if (!empty($_SESSION['locale'])) {
        return $_SESSION['locale'];
    }
    return config('app.locale', 'en');
}

/**
 * Set the locale in the session.
 * Only allow locales that have a corresponding file in resources/lang/.
 */
function setLocale(string $locale): void
{
    $path = BASE_PATH . '/resources/lang/' . $locale . '.php';
    if (file_exists($path)) {
        $_SESSION['locale'] = $locale;
    }
    // Silently ignore unknown locales — do not expose error
}
```

For this MVP milestone, the locale is always `'en'`. The `setLocale()` function is scaffolded but no locale-switching UI is built. A future milestone can add a locale switcher.

---

## 4. Config Key

Add to the application configuration (`.env.example` and the config reader):
```
APP_LOCALE=en
```

Default value: `en`. The config reader must return `'en'` if `APP_LOCALE` is not set.

---

## 5. View Replacement — Touched MVP Screens

Every view file that is created or modified in this milestone bundle must use `__()` for all visible user-facing text. This includes:

### Screens to audit and update

Go through each of the following view files and replace every hardcoded visible string with a `__()` call:

- `app/Views/auth/login.php`
- `app/Views/auth/no_access.php`
- `app/Views/auth/select_team.php`
- `app/Views/players/index.php`
- `app/Views/players/create.php`
- `app/Views/players/edit.php`
- `app/Views/matches/index.php`
- `app/Views/matches/create.php`
- `app/Views/matches/prepare.php`
- `app/Views/matches/live.php`
- `app/Views/matches/summary.php`
- `app/Views/stats/players.php` (created in prompt 02)
- `app/Views/stats/team.php` (created in prompt 02)
- `app/Views/livestream/public.php`
- `app/Views/errors/403.php`
- `app/Views/errors/404.php`
- `app/Views/errors/500.php`
- Any shared layout or partial files (navigation, header, footer)

### Replacement rule

Before:
```php
<h1>Players</h1>
<a href="/players/create">Add player</a>
```

After:
```php
<h1><?= __('players.heading') ?></h1>
<a href="/players/create"><?= __('players.add') ?></a>
```

Do NOT replace:
- Content that comes from the database (player names, opponent names, etc.) — these are data, not labels
- Developer-only debug output
- HTML structure strings (tags, attributes, CSS classes)
- JavaScript strings inside `<script>` blocks (defer to a future milestone)

---

## 6. Autoloading the Helper

Ensure `__()` and `currentLocale()` are available globally. Options:
- If a global helpers file is already required in the bootstrap (`require_once BASE_PATH . '/app/helpers.php'`), add the functions there
- Otherwise, create `app/Helpers/TranslationHelper.php` and require it in the bootstrap entry point (`public/index.php` or `app/bootstrap.php`)

The helper must be loaded before any view is rendered.

---

# Out of Scope

- Locale switching UI (language selector in nav)
- Multiple translation files beyond `en.php` (the structure supports them; the content does not need to exist yet)
- ICU message format, pluralization rules, or complex interpolation
- Database-driven translations
- JavaScript i18n for client-side strings
- Right-to-left layout support
- Automatic locale detection from `Accept-Language` header

---

# Architectural Rules

- `__()` is a global helper function, not a class method — it must be callable from view files without instantiating anything
- Views call `__()` for labels only; they never contain translation logic
- The translation file is a plain PHP array; no YAML, JSON, or gettext
- Locale is stored in the session only; it is never trusted from query strings or POST body without validation
- The fallback chain is: current locale → default locale (`en`) → raw key

---

# Acceptance Criteria

- `resources/lang/en.php` exists and returns a PHP array with all keys listed in this prompt (plus any additional keys found in touched views)
- `__()` function is globally available and callable from any view
- `__('nav.players')` returns `'Players'` in the default locale
- `__('nonexistent.key')` returns `'nonexistent.key'` (the key itself, not blank, not an error)
- Every visible label in the touched MVP view files listed above uses `__()` instead of a hardcoded string
- No PHP errors when the locale file does not exist for an unknown locale (graceful fallback)
- Setting an unknown locale via `setLocale()` is silently ignored (no file is created, no error thrown)
- `APP_LOCALE=en` in `.env.example`

---

# Verification

1. PHP syntax check all new/modified files:
   ```bash
   find app/ resources/ -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"
   ```
2. Grep for hardcoded English strings in touched view files to confirm none remain:
   ```bash
   grep -rn "Players\|Matches\|Log in\|Save\|Cancel\|Delete\|Goals\|Wins\|Dashboard" app/Views/
   ```
   Expected: zero results for hardcoded labels (data values from DB are acceptable).
3. Manually load the player list view and confirm all labels render correctly in English.
4. Temporarily rename `resources/lang/en.php` and verify the app falls back to returning the raw key without a PHP fatal error (then restore the file).

---

# Handoff Note

After this prompt, all touched MVP screens use the `__()` helper. The i18n foundation is in place. Future milestones can add a second language file (`resources/lang/nl.php`, etc.) and a locale switcher without touching existing view logic. Prompt `04-security-hardening-review.md` follows.
