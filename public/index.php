<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use BarePitch\Core\App;
use BarePitch\Core\Database;
use BarePitch\Core\Env;
use BarePitch\Core\Request;
use BarePitch\Core\Response;
use BarePitch\Core\Router;
use BarePitch\Core\View;
use BarePitch\Core\Exceptions\AuthorizationException;
use BarePitch\Core\Exceptions\CsrfException;
use BarePitch\Core\Exceptions\NotFoundException;
use BarePitch\Core\Exceptions\ValidationException;

// ── Repositories ───────────────────────────────────────────────────────────
use BarePitch\Repositories\AuditRepository;
use BarePitch\Repositories\EventRepository;
use BarePitch\Repositories\LineupRepository;
use BarePitch\Repositories\LockRepository;
use BarePitch\Repositories\MatchRepository;
use BarePitch\Repositories\PhaseRepository;
use BarePitch\Repositories\PlayerRepository;
use BarePitch\Repositories\SelectionRepository;
use BarePitch\Repositories\TeamRepository;
use BarePitch\Repositories\UserRepository;

// ── Services ───────────────────────────────────────────────────────────────
use BarePitch\Services\AuditService;
use BarePitch\Services\AuthService;
use BarePitch\Services\LiveMatchService;
use BarePitch\Services\MatchPreparationService;
use BarePitch\Services\MatchService;
use BarePitch\Services\PlayerService;
use BarePitch\Services\TeamContextService;

// ── Controllers ────────────────────────────────────────────────────────────
use BarePitch\Http\Controllers\AuthController;
use BarePitch\Http\Controllers\ContextController;
use BarePitch\Http\Controllers\HomeController;
use BarePitch\Http\Controllers\LiveMatchController;
use BarePitch\Http\Controllers\MatchController;
use BarePitch\Http\Controllers\MatchPreparationController;
use BarePitch\Http\Controllers\PlayerController;

// ── Bootstrap ──────────────────────────────────────────────────────────────
Env::load(BASE_PATH . '/.env');
App::boot();

// ── Wire up repositories ───────────────────────────────────────────────────
$pdo = Database::connection();

$userRepo      = new UserRepository($pdo);
$teamRepo      = new TeamRepository($pdo);
$playerRepo    = new PlayerRepository($pdo);
$phaseRepo     = new PhaseRepository($pdo);
$matchRepo     = new MatchRepository($pdo);
$selectionRepo = new SelectionRepository($pdo);
$lineupRepo    = new LineupRepository($pdo);
$eventRepo     = new EventRepository($pdo);
$auditRepo     = new AuditRepository($pdo);
$lockRepo      = new LockRepository($pdo);

// ── Wire up services ───────────────────────────────────────────────────────
$auditService   = new AuditService($auditRepo);
$authService    = new AuthService($userRepo);
$teamCtxService = new TeamContextService($teamRepo);
$playerService  = new PlayerService($playerRepo, $auditService);
$matchService   = new MatchService($matchRepo, $auditService);
$prepService    = new MatchPreparationService(
    $matchRepo,
    $selectionRepo,
    $lineupRepo,
    $teamRepo,
    $auditService
);
$liveService    = new LiveMatchService(
    $matchRepo,
    $selectionRepo,
    $eventRepo,
    $lockRepo,
    $auditService
);

// ── Wire up controllers ────────────────────────────────────────────────────
$homeCtrl  = new HomeController($authService, $teamCtxService, $matchRepo, $teamRepo, $userRepo);
$authCtrl  = new AuthController($authService, $userRepo);
$ctxCtrl   = new ContextController($authService, $teamCtxService);
$playerCtrl = new PlayerController($authService, $teamCtxService, $playerRepo, $playerService);
$matchCtrl  = new MatchController(
    $authService,
    $teamCtxService,
    $matchRepo,
    $matchService,
    $selectionRepo,
    $lineupRepo,
    $eventRepo,
    $phaseRepo
);
$prepCtrl   = new MatchPreparationController(
    $authService,
    $teamCtxService,
    $matchRepo,
    $playerRepo,
    $selectionRepo,
    $lineupRepo,
    $teamRepo,
    $prepService
);
$liveCtrl   = new LiveMatchController(
    $authService,
    $teamCtxService,
    $matchRepo,
    $selectionRepo,
    $eventRepo,
    $lineupRepo,
    $liveService
);

// ── Register routes ────────────────────────────────────────────────────────

// Home
Router::get('/', fn(Request $req, array $p) => $homeCtrl->index($req, $p));

// Auth
Router::get('/auth/dev-login',  fn(Request $req, array $p) => $authCtrl->devLoginForm($req, $p));
Router::post('/auth/dev-login', fn(Request $req, array $p) => $authCtrl->devLogin($req, $p));
Router::post('/logout',         fn(Request $req, array $p) => $authCtrl->logout($req, $p));

// Team context
Router::post('/context/team', fn(Request $req, array $p) => $ctxCtrl->switchTeam($req, $p));

// Matches (specific static paths before parameterised paths)
Router::get('/matches',                                    fn(Request $req, array $p) => $matchCtrl->index($req, $p));
Router::get('/matches/create',                             fn(Request $req, array $p) => $matchCtrl->create($req, $p));
Router::post('/matches',                                   fn(Request $req, array $p) => $matchCtrl->store($req, $p));
Router::get('/matches/{match_id}',                         fn(Request $req, array $p) => $matchCtrl->show($req, $p));
Router::get('/matches/{match_id}/summary',                 fn(Request $req, array $p) => $matchCtrl->summary($req, $p));
Router::get('/matches/{match_id}/edit',                    fn(Request $req, array $p) => $matchCtrl->edit($req, $p));
Router::post('/matches/{match_id}/update',                 fn(Request $req, array $p) => $matchCtrl->update($req, $p));

// Match preparation
Router::get('/matches/{match_id}/prepare',                 fn(Request $req, array $p) => $prepCtrl->show($req, $p));
Router::post('/matches/{match_id}/attendance',             fn(Request $req, array $p) => $prepCtrl->saveAttendance($req, $p));
Router::post('/matches/{match_id}/formation',              fn(Request $req, array $p) => $prepCtrl->setFormation($req, $p));
Router::post('/matches/{match_id}/lineup',                 fn(Request $req, array $p) => $prepCtrl->saveLineup($req, $p));
Router::post('/matches/{match_id}/prepare/confirm',        fn(Request $req, array $p) => $prepCtrl->confirmPreparation($req, $p));

// Live match
Router::get('/matches/{match_id}/live',                            fn(Request $req, array $p) => $liveCtrl->show($req, $p));
Router::post('/matches/{match_id}/start',                          fn(Request $req, array $p) => $liveCtrl->start($req, $p));
Router::post('/matches/{match_id}/finish',                         fn(Request $req, array $p) => $liveCtrl->finish($req, $p));
Router::post('/matches/{match_id}/periods/{period_id}/end',        fn(Request $req, array $p) => $liveCtrl->endPeriod($req, $p));
Router::post('/matches/{match_id}/periods/start-second-half',      fn(Request $req, array $p) => $liveCtrl->startSecondHalf($req, $p));
Router::post('/matches/{match_id}/events/goal',                    fn(Request $req, array $p) => $liveCtrl->registerGoal($req, $p));

// Players
Router::get('/players',          fn(Request $req, array $p) => $playerCtrl->index($req, $p));
Router::get('/players/create',   fn(Request $req, array $p) => $playerCtrl->create($req, $p));
Router::post('/players',         fn(Request $req, array $p) => $playerCtrl->store($req, $p));
Router::get('/players/{player_id}', fn(Request $req, array $p) => $playerCtrl->show($req, $p));

// ── Dispatch ───────────────────────────────────────────────────────────────
try {
    Router::dispatch();
} catch (NotFoundException $e) {
    http_response_code(404);
    echo View::render('errors/404', ['message' => $e->getMessage()]);
} catch (AuthorizationException $e) {
    // If unauthenticated (no session), redirect to login; otherwise 403.
    if ($e->getMessage() === 'You must be logged in to perform this action.') {
        Response::redirect('/auth/dev-login');
    }
    http_response_code(403);
    echo View::render('errors/403', ['message' => $e->getMessage()]);
} catch (CsrfException $e) {
    http_response_code(403);
    echo View::render('errors/403', ['message' => 'Invalid security token. Please try again.']);
} catch (\Throwable $e) {
    http_response_code(500);
    $message = getenv('APP_DEBUG') === 'true'
        ? $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine()
        : 'An unexpected error occurred.';
    echo View::render('errors/500', ['message' => $message]);
}
