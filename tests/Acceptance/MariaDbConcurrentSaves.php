<?php

// Standalone acceptance harness: php tests/Acceptance/MariaDbConcurrentSaves.php
// Uses a new disposable local database, never PHPUnit's normal connection.

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use App\Services\AccountCredentialService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function acceptanceAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function acceptanceWait(Closure $ready, string $message, int $seconds = 20): void
{
    $until = microtime(true) + $seconds;
    do {
        clearstatcache();
        if ($ready()) {
            return;
        }
        usleep(50000);
    } while (microtime(true) < $until);
    throw new RuntimeException($message);
}

function acceptanceBoot(?array $connection = null, ?string $directory = null)
{
    putenv('APP_ENV=testing');
    $cachePrefix = ($directory ?? sys_get_temp_dir()).'/casetrack-acceptance-'.bin2hex(random_bytes(8));
    acceptanceAssert(! is_file($cachePrefix.'-config.php') && ! is_file($cachePrefix.'-routes.php'), 'Unexpected acceptance cache file.');
    putenv('APP_CONFIG_CACHE='.$cachePrefix.'-config.php');
    putenv('APP_ROUTES_CACHE='.$cachePrefix.'-routes.php');
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    if ($connection !== null) {
        $app->loadEnvironmentFrom('.env.acceptance-not-present');
    }
    $app->make(ConsoleKernel::class)->bootstrap();
    config([
        'app.env' => 'testing', 'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'session.driver' => 'array', 'cache.default' => 'array',
        'logging.default' => 'null', 'hashing.bcrypt.rounds' => 4,
    ]);
    if ($connection !== null) {
        acceptanceConnect($connection);
    }

    return $app;
}

function acceptanceConnect(array $connection): void
{
    acceptanceAssert((bool) preg_match('/^casetrack_acceptance_[a-f0-9]{16}$/D', $connection['database']), 'Unsafe fixture database name.');
    config(['database.default' => 'acceptance', 'database.connections' => ['acceptance' => $connection]]);
    acceptanceAssert(DB::selectOne('SELECT DATABASE() AS db')->db === $connection['database'], 'Fixture connection mismatch.');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 25');
}

function acceptanceWorker(): never
{
    $job = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
    $app = acceptanceBoot($job['connection'], $job['directory']);
    if (isset($job['app_key'])) {
        config(['app.key' => $job['app_key']]);
    }
    $connectionId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
    file_put_contents($job['directory'].'/'.$job['name'].'.connection', (string) $connectionId);
    DB::connection()->beforeExecuting(function ($sql) use ($job) {
        if (str_contains(strtolower($sql), 'for update') || str_contains(strtolower($sql), 'insert ignore into `submission_receipts`')) {
            file_put_contents($job['directory'].'/'.$job['name'].'.attempt', '1');
        }
    });
    if ($job['hold']) {
        $model = ($job['hold_model'] ?? 'case') === 'user' ? User::class : CaseModel::class;
        $model::updating(function ($record) use ($job) {
            if ($record->id === ($job['hold_target'] ?? $job['case_id'])) {
                acceptanceAssert(DB::transactionLevel() > 0, 'Expected a locked transaction.');
                file_put_contents($job['directory'].'/winner.locked', '1');
                acceptanceWait(fn () => is_file($job['directory'].'/release'), 'Winner release timed out.', 40);
            }
        });
    }
    if ($job['fail_audit']) {
        AuditLog::creating(function ($audit) use ($job) {
            if ($audit->action_performed === ($job['fail_audit_action'] ?? AuditLog::ACTION_TORTURE_EXTENSION_RECORDED)) {
                throw new RuntimeException('Synthetic extension-audit failure');
            }
        });
    }
    $cookies = [];
    if (isset($job['database_session_id'])) {
        config(['session.driver' => 'database']);
        $app['session']->forgetDrivers();
        $app->forgetInstance('session.store');
        $app['auth']->forgetGuards();
        $name = config('session.cookie');
        $cookies[$name] = $app['encrypter']->encrypt(
            CookieValuePrefix::create($name, $app['encrypter']->getKey()).$job['database_session_id'], false
        );
    } else {
        Auth::guard('web')->setUser(User::findOrFail($job['actor']));
    }
    $request = Request::create($job['path'], $job['method'] ?? 'PUT', $job['payload'] + ['_submission_id' => (string) Str::uuid()], cookies: $cookies, server: ['HTTP_ACCEPT' => 'application/json']);
    $kernel = $app->make(HttpKernel::class);
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true);
    $replayed = $request->session()->get('status') === 'This submission was already completed.';
    $kernel->terminate($request, $response);
    echo json_encode([
        'status' => $response->getStatusCode(),
        'error_keys' => array_keys($body['errors'] ?? []),
        'connection_id' => $connectionId,
        'replayed' => $replayed,
        'session_authenticated' => $request->session()->has(Auth::guard('web')->getName()),
        'exception' => ($response->exception ?? null)?->getMessage(),
    ], JSON_THROW_ON_ERROR);
    exit(0);
}

if (($argv[1] ?? null) === '--worker') {
    acceptanceWorker();
}

$root = dirname(__DIR__, 2);
$envHash = hash_file('sha256', $root.'/.env');
$app = acceptanceBoot();
$original = config('database.connections.'.config('database.default'));
acceptanceAssert(in_array($original['host'], ['127.0.0.1', 'localhost', '::1'], true), 'Only a local MariaDB server is permitted.');
acceptanceAssert(empty($original['url']) && empty($original['unix_socket']), 'Explicit TCP connection required; DB_URL/socket is not supported by this harness.');
$database = 'casetrack_acceptance_'.bin2hex(random_bytes(8));
acceptanceAssert($database !== $original['database'], 'Refusing the configured database.');
$directory = $root.'/storage/app/'.$database;
acceptanceAssert(mkdir($directory, 0700), 'Unable to create barrier directory.');
$server = new PDO('mysql:host='.$original['host'].';port='.$original['port'].';charset=utf8mb4', $original['username'], $original['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$version = $server->query('SELECT VERSION()')->fetchColumn();
acceptanceAssert(str_contains($version, 'MariaDB'), 'This acceptance check requires MariaDB.');
$connection = array_replace($original, ['driver' => 'mysql', 'url' => null, 'database' => $database]);
$created = false;
$processes = [];
$results = [];
$failure = null;
try {
    $server->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $created = true;
    acceptanceConnect($connection);
    acceptanceAssert(Artisan::call('migrate', ['--force' => true, '--no-interaction' => true]) === 0, 'Fixture migrations failed.');
    $engine = DB::selectOne("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'cases'", [$database])->ENGINE;
    acceptanceAssert($engine === 'InnoDB', 'Expected InnoDB case locks.');
    $actor = User::factory()->investigator()->create(['username' => 'SYNTHETIC-acceptance-investigator']);
    $next = User::factory()->investigator()->create(['username' => 'SYNTHETIC-acceptance-next']);
    $supervisor = User::factory()->supervisor()->create(['username' => 'SYNTHETIC-acceptance-supervisor']);
    $scenarios = [
        ['timeline_vs_timeline', 'grant', 'timeline', 302, 422],
        ['timeline_vs_case', 'grant', 'case', 302, 422],
        ['case_vs_timeline', 'case', 'grant', 302, 422],
        ['reassignment_vs_grant', 'reassign', 'grant', 302, 403],
        ['grant_vs_reassignment', 'grant', 'reassign', 302, 302],
        ['supervisor_vs_supervisor', 'supervisor_grant', 'supervisor_grant', 302, 422],
        ['audit_failure_vs_grant', 'failed_grant', 'grant', 500, 302],
    ];
    foreach ($scenarios as [$label, $first, $second, $firstStatus, $secondStatus]) {
        $scenarioDirectory = $directory.'/'.$label;
        acceptanceAssert(mkdir($scenarioDirectory), 'Unable to create scenario directory.');
        $case = CaseModel::factory()->assignedTo($actor)->create([
            'docket_no' => 'SYNTHETIC-'.$label, 'case_title' => 'Synthetic acceptance case',
            'status' => CaseModel::STATUS_DOCKETED, 'complexity_weight' => 2, 'is_torture_case' => true,
        ]);
        $case->timeline()->create(['date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10']);
        $jobs = [];
        foreach (['winner' => $first, 'contender' => $second] as $name => $operation) {
            $path = '/cases/'.$case->id.'/timeline';
            $payload = ['lock_version' => 0, 'date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10'];
            $user = $actor;
            if (in_array($operation, ['grant', 'supervisor_grant', 'failed_grant'], true)) {
                $payload += ['torture_extension_granted' => 1, 'extension_note' => 'SYNTHETIC-'.$name];
            }
            if ($operation === 'supervisor_grant') {
                $user = $supervisor;
                $payload['reason'] = 'Synthetic coverage decision recording';
            } elseif ($operation === 'failed_grant') {
                $payload['date_of_docket'] = '2026-01-02';
            } elseif ($operation === 'timeline') {
                $payload['date_submission_rop'] = '2026-01-11';
            } elseif ($operation === 'case') {
                $path = '/cases/'.$case->id;
                $payload = $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']);
                $payload['case_title'] = 'Synthetic corrected title';
            } elseif ($operation === 'reassign') {
                $user = $supervisor;
                $path = '/cases/'.$case->id.'/reassign';
                $payload = ['investigator_id' => $next->id, 'docket_no' => $case->docket_no];
            }
            $jobs[$name] = [
                'connection' => $connection, 'directory' => $scenarioDirectory,
                'name' => $name, 'case_id' => $case->id, 'actor' => $user->id,
                'path' => $path, 'payload' => $payload, 'hold' => $name === 'winner',
                'fail_audit' => $operation === 'failed_grant',
            ];
        }
        $launch = function ($job) use ($root, &$processes) {
            $process = new Process([PHP_BINARY, __FILE__, '--worker'], $root);
            $process->setTimeout(60);
            $process->setInput(json_encode($job, JSON_THROW_ON_ERROR));
            $process->start();
            $processes[] = $process;

            return $process;
        };
        $winner = $launch($jobs['winner']);
        acceptanceWait(fn () => is_file($scenarioDirectory.'/winner.locked') || ! $winner->isRunning(), 'Winner did not reach locked save.');
        acceptanceAssert(is_file($scenarioDirectory.'/winner.locked'), 'Winner failed before lock: '.$winner->getErrorOutput().$winner->getOutput());
        $contender = $launch($jobs['contender']);
        acceptanceWait(fn () => is_file($scenarioDirectory.'/contender.attempt') || ! $contender->isRunning(), 'Contender did not attempt locked query.');
        acceptanceAssert(is_file($scenarioDirectory.'/contender.attempt'), 'Contender failed before lock: '.$contender->getErrorOutput().$contender->getOutput());
        $winnerId = (int) file_get_contents($scenarioDirectory.'/winner.connection');
        $contenderId = (int) file_get_contents($scenarioDirectory.'/contender.connection');
        $lockWait = $server->prepare('SELECT COUNT(*) FROM information_schema.INNODB_LOCK_WAITS w JOIN information_schema.INNODB_TRX r ON r.trx_id = w.requesting_trx_id JOIN information_schema.INNODB_TRX b ON b.trx_id = w.blocking_trx_id WHERE r.trx_mysql_thread_id = ? AND b.trx_mysql_thread_id = ?');
        acceptanceWait(function () use ($lockWait, $winnerId, $contenderId) {
            $lockWait->execute([$contenderId, $winnerId]);

            return (int) $lockWait->fetchColumn() > 0;
        }, $label.': no actual InnoDB lock wait observed. Winner: '.$winner->getOutput().' Contender: '.$contender->getOutput());
        file_put_contents($scenarioDirectory.'/release', '1');
        acceptanceAssert($winner->wait() === 0 && $contender->wait() === 0, 'Worker failed.');
        $one = json_decode($winner->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $two = json_decode($contender->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        acceptanceAssert($one['status'] === $firstStatus && $two['status'] === $secondStatus, $label.' response mismatch: '.json_encode([$one, $two]));
        if ($secondStatus === 422) {
            acceptanceAssert($two['error_keys'] === ['lock_version'], 'Expected a stale-version validation error.');
        }
        $case->refresh();
        $timeline = $case->timeline()->sole();
        $bothSucceed = $label === 'grant_vs_reassignment';
        acceptanceAssert($case->lock_version === ($bothSucceed ? 2 : 1), 'Unexpected version increment.');
        $reassigned = in_array($label, ['reassignment_vs_grant', 'grant_vs_reassignment'], true);
        acceptanceAssert($case->investigator_id === ($reassigned ? $next->id : $actor->id), 'Unexpected assignment.');
        $hasGrant = ! in_array($label, ['case_vs_timeline', 'reassignment_vs_grant'], true);
        acceptanceAssert($timeline->extension_60th_days?->format('Y-m-d') === ($hasGrant ? '2026-04-01' : null), 'Unexpected extension date.');
        acceptanceAssert($timeline->date_of_docket->format('Y-m-d') === '2026-01-01', 'Rolled-back docket correction survived.');
        acceptanceAssert($timeline->date_submission_rop->format('Y-m-d') === '2026-01-10', 'Rejected timeline correction survived.');
        acceptanceAssert($case->case_title === ($label === 'case_vs_timeline' ? 'Synthetic corrected title' : 'Synthetic acceptance case'), 'Rejected case correction survived.');
        $auditCounts = AuditLog::where('case_id', $case->id)->get()->countBy('action_performed')->sortKeys()->all();
        $expected = $hasGrant ? [AuditLog::ACTION_TIMELINE_UPDATE => 1, AuditLog::ACTION_TORTURE_EXTENSION_RECORDED => 1] : [];
        if ($label === 'case_vs_timeline') {
            $expected[AuditLog::ACTION_EDIT] = 1;
        }
        if ($reassigned) {
            $expected[AuditLog::ACTION_CASE_REASSIGNED] = 1;
        }
        if ($secondStatus === 403) {
            $expected[AuditLog::ACTION_ACCESS_DENIED] = 1;
        }
        if ($label === 'supervisor_vs_supervisor') {
            $expected[AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF] = 1;
            $onBehalf = AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_TIMELINE_EDIT_ON_BEHALF)->sole();
            acceptanceAssert($onBehalf->user_id === $supervisor->id && $onBehalf->notes === $jobs['winner']['payload']['reason'], 'Supervisor accountability reason was lost.');
        }
        ksort($expected);
        acceptanceAssert($auditCounts === $expected, 'Audit atomicity mismatch: '.json_encode($auditCounts));
        if ($hasGrant) {
            $notes = json_decode(AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_TORTURE_EXTENSION_RECORDED)->sole()->notes, true, flags: JSON_THROW_ON_ERROR);
            acceptanceAssert($notes === ['previous_deadline' => null, 'deadline' => '2026-04-01', 'note' => 'SYNTHETIC-'.($label === 'audit_failure_vs_grant' ? 'contender' : 'winner')], 'Rejected grant audit survived or successful reference was lost.');
        }
        $results[] = ['scenario' => $label, 'statuses' => [$one['status'], $two['status']], 'actual_innodb_lock_wait' => true, 'version' => $case->lock_version, 'audit_counts' => $auditCounts];
        echo $label.': PASS (observed InnoDB wait; '.implode('/', [$one['status'], $two['status']]).')'.PHP_EOL;
    }
    foreach (['same_key_edit', 'same_key_changed_input', 'same_key_rollback', 'same_key_rating'] as $label) {
        $scenarioDirectory = $directory.'/'.$label;
        acceptanceAssert(mkdir($scenarioDirectory), 'Unable to create receipt scenario directory.');
        $case = CaseModel::factory()->assignedTo($actor)->create(['docket_no' => 'SYNTHETIC-'.$label, 'status' => CaseModel::STATUS_DOCKETED]);
        $rating = $label === 'same_key_rating';
        $target = $rating ? User::factory()->investigator()->create() : null;
        $id = (string) Str::uuid();
        $payload = $rating ? ['performance_rating' => '0.5'] : array_replace(
            $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']),
            ['case_title' => 'Synthetic single intended edit']
        );
        $payload['_submission_id'] = $id;
        $baseJob = [
            'connection' => $connection, 'directory' => $scenarioDirectory,
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'case_id' => $case->id, 'actor' => $rating ? $supervisor->id : $actor->id,
            'path' => $rating ? '/workload/'.$target->id : '/cases/'.$case->id,
            'payload' => $payload, 'hold_model' => $rating ? 'user' : 'case',
            'hold_target' => $rating ? $target->id : $case->id,
            'fail_audit_action' => AuditLog::ACTION_EDIT,
        ];
        $winnerJob = $baseJob + ['name' => 'winner', 'hold' => true, 'fail_audit' => $label === 'same_key_rollback'];
        $contenderJob = $baseJob + ['name' => 'contender', 'hold' => false, 'fail_audit' => false];
        if ($label === 'same_key_changed_input') {
            $contenderJob['payload']['case_title'] = 'Different intended edit';
        }
        $winner = $launch($winnerJob);
        acceptanceWait(fn () => is_file($scenarioDirectory.'/winner.locked') || ! $winner->isRunning(), 'Receipt winner did not lock.');
        acceptanceAssert(is_file($scenarioDirectory.'/winner.locked'), 'Receipt winner failed: '.$winner->getErrorOutput().$winner->getOutput());
        $contender = $launch($contenderJob);
        acceptanceWait(fn () => is_file($scenarioDirectory.'/contender.attempt') || ! $contender->isRunning(), 'Receipt contender did not claim.');
        acceptanceAssert(is_file($scenarioDirectory.'/contender.attempt'), 'Receipt contender failed: '.$contender->getErrorOutput().$contender->getOutput());
        $winnerId = (int) file_get_contents($scenarioDirectory.'/winner.connection');
        $contenderId = (int) file_get_contents($scenarioDirectory.'/contender.connection');
        $lockWait->closeCursor();
        acceptanceWait(function () use ($lockWait, $winnerId, $contenderId) {
            $lockWait->execute([$contenderId, $winnerId]);

            return (int) $lockWait->fetchColumn() > 0;
        }, 'No actual receipt InnoDB lock wait observed.');
        file_put_contents($scenarioDirectory.'/release', '1');
        acceptanceAssert($winner->wait() === 0 && $contender->wait() === 0, 'Receipt worker failed.');
        $one = json_decode($winner->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $two = json_decode($contender->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $expectedFirst = $label === 'same_key_rollback' ? 500 : 302;
        $expectedSecond = match ($label) {
            'same_key_changed_input' => 409,
            default => 302,
        };
        acceptanceAssert([$one['status'], $two['status']] === [$expectedFirst, $expectedSecond], $label.' receipt response mismatch: '.json_encode([$one, $two]));
        acceptanceAssert(DB::table('submission_receipts')->where('id', $id)->whereNotNull('completed_at')->count() === 1, 'Expected one durable completed receipt.');
        if ($rating) {
            acceptanceAssert((float) $target->fresh()->performance_rating === 0.5, 'Rating was not saved.');
            acceptanceAssert(AuditLog::where('target_user_id', $target->id)->where('action_performed', AuditLog::ACTION_PERFORMANCE_RATING_UPDATED)->count() === 1, 'Rating audit repeated.');
        } else {
            acceptanceAssert($case->fresh()->lock_version === 1, 'Same intended edit changed the version twice.');
            acceptanceAssert($case->fresh()->case_title === 'Synthetic single intended edit', 'Changed retry replaced the intended edit.');
            acceptanceAssert(AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_EDIT)->count() === 1, 'Case audit repeated.');
        }
        $retry = $launch(array_replace($baseJob, ['name' => 'uncertain_response_retry', 'hold' => false, 'fail_audit' => false]));
        acceptanceAssert($retry->wait() === 0, 'Uncertain-response worker failed.');
        $three = json_decode($retry->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        acceptanceAssert($three['status'] === 302 && $three['replayed'], 'A later unchanged retry did not replay the committed outcome.');
        acceptanceAssert(DB::table('submission_receipts')->where('id', $id)->count() === 1, 'Retry claimed a second receipt.');
        $results[] = ['scenario' => $label, 'statuses' => [$one['status'], $two['status'], $three['status']], 'actual_innodb_lock_wait' => true, 'single_mutation_and_audit' => true, 'uncertain_response_replayed' => true];
        echo $label.': PASS (observed InnoDB receipt wait; single mutation/audit; later retry replayed)'.PHP_EOL;
    }

    $supervisorRole = $supervisor->role_id;
    $admin = User::factory()->admin()->create();
    foreach (['case_wait_role_revoked', 'receipt_wait_password_reset', 'receipt_wait_session_revoked'] as $label) {
        $scenarioDirectory = $directory.'/'.$label;
        acceptanceAssert(mkdir($scenarioDirectory), 'Unable to create authorization scenario directory.');
        $case = CaseModel::factory()->assignedTo($actor)->create(['docket_no' => 'SYNTHETIC-'.$label, 'status' => CaseModel::STATUS_DOCKETED]);
        $id = (string) Str::uuid();
        $baseJob = [
            'connection' => $connection, 'directory' => $scenarioDirectory,
            'app_key' => 'base64:'.base64_encode(random_bytes(32)),
            'case_id' => $case->id, 'actor' => $supervisor->id, 'path' => '/cases/'.$case->id,
            'payload' => $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version'])
                + ['reason' => 'Synthetic authorization acceptance', '_submission_id' => $id],
            'hold' => false, 'fail_audit' => false,
        ];
        $initial = $launch($baseJob + ['name' => 'initial']);
        acceptanceAssert($initial->wait() === 0, 'Initial authorization fixture failed.');
        acceptanceAssert(json_decode($initial->getOutput(), true, flags: JSON_THROW_ON_ERROR)['status'] === 302, 'Initial edit did not succeed.');
        $sessionId = null;
        if ($label !== 'case_wait_role_revoked') {
            $sessionId = bin2hex(random_bytes(20));
            DB::table('sessions')->insert([
                'id' => $sessionId, 'user_id' => $supervisor->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Synthetic acceptance',
                'payload' => base64_encode(serialize([Auth::guard('web')->getName() => $supervisor->id, '_token' => Str::random(40)])),
                'last_activity' => time(),
            ]);
            $baseJob['database_session_id'] = $sessionId;
        }
        $blocker = new PDO('mysql:host='.$original['host'].';port='.$original['port'].';dbname='.$database.';charset=utf8mb4', $original['username'], $original['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        try {
            $blocker->beginTransaction();
            $blockerId = (int) $blocker->query('SELECT CONNECTION_ID()')->fetchColumn();
            if ($label === 'case_wait_role_revoked') {
                $statement = $blocker->prepare('SELECT id FROM cases WHERE id = ? FOR UPDATE');
                $statement->execute([$case->id]);
            } else {
                $statement = $blocker->prepare('SELECT id FROM submission_receipts WHERE id = ? FOR UPDATE');
                $statement->execute([$id]);
            }
            $statement->fetchAll();
            $retry = $launch($baseJob + ['name' => 'revoked_retry']);
            acceptanceWait(fn () => is_file($scenarioDirectory.'/revoked_retry.attempt') || ! $retry->isRunning(), 'Authorization retry did not attempt its lock.');
            acceptanceAssert(is_file($scenarioDirectory.'/revoked_retry.attempt'), 'Authorization retry failed before lock: '.$retry->getErrorOutput().$retry->getOutput());
            $retryId = (int) file_get_contents($scenarioDirectory.'/revoked_retry.connection');
            acceptanceWait(function () use ($lockWait, $retryId, $blockerId) {
                $lockWait->execute([$retryId, $blockerId]);

                return (int) $lockWait->fetchColumn() > 0;
            }, 'No actual authorization lock wait observed.');
            if ($label === 'case_wait_role_revoked') {
                $supervisor->update(['role_id' => $actor->role_id]);
            } elseif ($label === 'receipt_wait_password_reset') {
                app(AccountCredentialService::class)->resetPassword($admin, $supervisor->fresh(), 'Synthetic acceptance reset');
            } else {
                DB::table('sessions')->where('id', $sessionId)->delete();
            }
            $blocker->commit();
            acceptanceAssert($retry->wait() === 0, 'Authorization worker failed.');
            $outcome = json_decode($retry->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            acceptanceAssert($outcome['status'] === 403 && ! $outcome['replayed'], $label.' replayed a revoked authorization: '.json_encode($outcome));
            acceptanceAssert($case->fresh()->lock_version === 1, 'Revoked replay changed the case.');
            acceptanceAssert(AuditLog::where('case_id', $case->id)->where('action_performed', AuditLog::ACTION_EDIT_ON_BEHALF)->count() === 1, 'Revoked replay repeated the mutation audit.');
            if ($sessionId !== null) {
                acceptanceAssert(! $outcome['session_authenticated'] && DB::table('sessions')->where('id', $sessionId)->count() === 0, 'Revoked session was resurrected by request completion.');
            }
            $results[] = ['scenario' => $label, 'actual_innodb_lock_wait' => true, 'revoked_replay_denied' => true, 'database_session_checked' => $sessionId !== null, 'single_mutation_and_audit' => true];
            echo $label.': PASS (observed InnoDB wait; revoked replay denied; no repeated mutation/audit)'.PHP_EOL;
        } finally {
            if ($blocker->inTransaction()) {
                $blocker->rollBack();
            }
            $supervisor->update(['role_id' => $supervisorRole]);
        }
    }
    $label = 'receipt_wait_import_promotion';
    $scenarioDirectory = $directory.'/'.$label;
    acceptanceAssert(mkdir($scenarioDirectory), 'Unable to create import promotion scenario directory.');
    $importId = (string) Str::uuid();
    $id = (string) Str::uuid();
    $path = 'imports/user-'.$actor->id.'-'.$importId.'.csv';
    $csv = "Docket No.,Title,Date of Docket,Incident Details,Victim Name,Victim Status,Victim Sector,Respondent Name,Investigator\n";
    $csv .= "SYNTHETIC-promotion-own,Synthetic,2026-01-01,Synthetic only,Synthetic,Synthetic,Synthetic,Synthetic,{$actor->username}\n";
    $csv .= "SYNTHETIC-promotion-other,Synthetic,2026-01-01,Synthetic only,Synthetic,Synthetic,Synthetic,Synthetic,{$next->username}\n";
    acceptanceAssert(Storage::put($path, $csv), 'Unable to stage synthetic import.');
    $sessionId = bin2hex(random_bytes(20));
    DB::table('sessions')->insert([
        'id' => $sessionId, 'user_id' => $actor->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Synthetic acceptance',
        'payload' => base64_encode(serialize([
            Auth::guard('web')->getName() => $actor->id, '_token' => Str::random(40),
            'case_import' => ['path' => $path, 'id' => $importId, 'user_id' => $actor->id, 'investigator_id' => null],
        ])), 'last_activity' => time(),
    ]);
    $importJob = [
        'connection' => $connection, 'directory' => $scenarioDirectory,
        'app_key' => 'base64:'.base64_encode(random_bytes(32)), 'database_session_id' => $sessionId,
        'actor' => $actor->id, 'path' => '/cases/import', 'method' => 'POST',
        'payload' => ['_submission_id' => $id, '_import_id' => $importId], 'hold' => false, 'fail_audit' => false,
    ];
    $blocker = new PDO('mysql:host='.$original['host'].';port='.$original['port'].';dbname='.$database.';charset=utf8mb4', $original['username'], $original['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    try {
        $blocker->beginTransaction();
        $blockerId = (int) $blocker->query('SELECT CONNECTION_ID()')->fetchColumn();
        $statement = $blocker->prepare('INSERT INTO submission_receipts (id, actor_hash, action, request_hash) VALUES (?, ?, ?, ?)');
        $statement->execute([$id, str_repeat('0', 64), 'synthetic-rollback', str_repeat('0', 64)]);
        $import = $launch($importJob + ['name' => 'promoted_import']);
        acceptanceWait(fn () => is_file($scenarioDirectory.'/promoted_import.attempt') || ! $import->isRunning(), 'Import did not attempt receipt claim.');
        acceptanceAssert(is_file($scenarioDirectory.'/promoted_import.attempt'), 'Import failed before claim: '.$import->getOutput().$import->getErrorOutput());
        $importConnection = (int) file_get_contents($scenarioDirectory.'/promoted_import.connection');
        acceptanceWait(function () use ($lockWait, $importConnection, $blockerId) {
            $lockWait->execute([$importConnection, $blockerId]);

            return (int) $lockWait->fetchColumn() > 0;
        }, 'No actual import claim wait observed.');
        $actor->update(['role_id' => $supervisorRole]);
        $blocker->rollBack();
        acceptanceAssert($import->wait() === 0, 'Promoted import worker failed.');
        $outcome = json_decode($import->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        acceptanceAssert($outcome['status'] === 302, 'Promoted import failed: '.json_encode($outcome));
        $imported = CaseModel::where('docket_no', 'SYNTHETIC-promotion-other')->sole();
        acceptanceAssert($imported->investigator_id === $next->id, 'Supervisor assignment was not performed.');
        acceptanceAssert((bool) DB::table('submission_receipts')->where('id', $id)->value('requires_supervisor'), 'Receipt retained the pre-wait role.');
        $actor->update(['role_id' => $next->role_id]);
        $retry = $launch($importJob + ['name' => 'demoted_import_retry']);
        acceptanceAssert($retry->wait() === 0, 'Demoted import retry worker failed.');
        $outcome = json_decode($retry->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        acceptanceAssert($outcome['status'] === 403 && ! $outcome['replayed'], 'Demoted import receipt replay was allowed.');
        acceptanceAssert(AuditLog::where('case_id', $imported->id)->where('action_performed', AuditLog::ACTION_CREATE)->count() === 1, 'Import create audit repeated.');
        acceptanceAssert(AuditLog::where('user_id', $actor->id)->where('action_performed', AuditLog::ACTION_IMPORTED)->count() === 1, 'Batch import audit repeated.');
        $results[] = ['scenario' => $label, 'actual_innodb_lock_wait' => true, 'claim_rolled_back' => true, 'completion_role_recorded' => true, 'demoted_replay_denied' => true, 'single_mutation_and_audit' => true];
        echo $label.': PASS (observed claim wait; promotion then rollback; demoted replay denied)'.PHP_EOL;
    } finally {
        if ($blocker->inTransaction()) {
            $blocker->rollBack();
        }
        Storage::delete($path);
    }
} catch (Throwable $exception) {
    $failure = $exception;
    foreach ($processes as $process) {
        if ($process->getOutput() !== '' || $process->getErrorOutput() !== '') {
            fwrite(STDERR, $process->getOutput().$process->getErrorOutput().PHP_EOL);
        }
    }
} finally {
    foreach ($processes as $process) {
        if ($process->isRunning()) {
            $process->stop(1);
        }
    }
    if ($created) {
        DB::disconnect('acceptance');
        acceptanceAssert((bool) preg_match('/^casetrack_acceptance_[a-f0-9]{16}$/D', $database) && $database !== $original['database'], 'Unsafe cleanup target.');
        $server->exec('DROP DATABASE `'.$database.'`');
    }
    acceptanceAssert(hash_file('sha256', $root.'/.env') === $envHash, '.env changed during acceptance.');
}
if ($failure !== null) {
    fwrite(STDERR, $failure->getMessage().PHP_EOL);
    exit(1);
}
echo json_encode(['server' => $version, 'engine' => $engine, 'scenarios' => $results, 'disposable_database_dropped' => true, 'env_unchanged' => true, 'normal_database_selected' => false], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
