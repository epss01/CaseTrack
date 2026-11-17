<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;
use PDOException;
use RuntimeException;
use Tests\TestCase;

class DatabaseErrorLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function captureLogs(): TestHandler
    {
        $handler = new TestHandler;
        $handler->setFormatter(new LineFormatter(includeStacktraces: true));
        Log::swap(new Logger(new MonologLogger('synthetic', [$handler]), $this->app['events']));

        return $handler;
    }

    private function queryFailure(string $privateArgument): QueryException
    {
        $driver = new PDOException('CONFIDENTIAL_DRIVER_MESSAGE');
        $driver->errorInfo = ['23000', 1062, 'CONFIDENTIAL_DRIVER_DETAIL'];

        return new QueryException('CONFIDENTIAL_CONNECTION',
            "insert into cases (case_title, incident_details) values (?, 'CONFIDENTIAL_SQL_LITERAL')",
            [$privateArgument], $driver);
    }

    public function test_query_reporting_excludes_sensitive_messages_context_and_trace_arguments(): void
    {
        $handler = $this->captureLogs();
        $previous = ini_set('zend.exception_ignore_args', '0');
        try {
            $error = $this->queryFailure('CONFIDENTIAL_BINDING_AND_ARGUMENT');
        } finally {
            ini_set('zend.exception_ignore_args', $previous);
        }
        $this->assertStringContainsString('CONFIDENTIAL_', $error->getMessage());
        $this->assertContains('CONFIDENTIAL_BINDING_AND_ARGUMENT', array_merge(...array_column($error->getTrace(), 'args')));

        report($error);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $record = $records[0];
        $this->assertSame('Database query failed.', $record->message);
        $this->assertSame('23000', $record->context['sql_state']);
        $this->assertSame(1062, $record->context['driver_code']);
        $this->assertSame(QueryException::class, $record->context['exception_type']);
        $this->assertSame($error->getFile(), $record->context['file']);
        $this->assertSame($error->getLine(), $record->context['line']);
        $this->assertNotEmpty($record->context['trace']);
        $this->assertStringNotContainsString('CONFIDENTIAL_', json_encode($record->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('CONFIDENTIAL_', $record->formatted);
        $this->assertArrayNotHasKey('exception', $record->context);
        foreach ($record->context['trace'] as $frame) {
            $this->assertSame([], array_diff(array_keys($frame), ['file', 'line', 'class', 'function']));
        }
        $this->assertStringContainsString('CONFIDENTIAL_', $error->getMessage());
    }

    public function test_wrapped_query_errors_cannot_leak_outer_messages_or_malformed_driver_metadata(): void
    {
        $handler = $this->captureLogs();
        $query = $this->queryFailure('CONFIDENTIAL_BINDING');
        $query->errorInfo = ['CONFIDENTIAL_STATE', 'CONFIDENTIAL_CODE', 'CONFIDENTIAL_DETAIL'];
        report(new RuntimeException('CONFIDENTIAL_OUTER_MESSAGE', previous: new RuntimeException('CONFIDENTIAL_MIDDLE_MESSAGE', previous: $query)));

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame(RuntimeException::class, $records[0]->context['exception_type']);
        $this->assertSame(QueryException::class, $records[0]->context['database_exception_type']);
        $this->assertNull($records[0]->context['sql_state']);
        $this->assertNull($records[0]->context['driver_code']);
        $this->assertStringNotContainsString('CONFIDENTIAL_', json_encode($records[0]->context, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('CONFIDENTIAL_', $records[0]->formatted);
    }

    public function test_unrelated_exceptions_keep_normal_reporting(): void
    {
        $handler = $this->captureLogs();
        $error = new RuntimeException('Synthetic non-database failure');

        report($error);

        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame($error->getMessage(), $records[0]->message);
        $this->assertSame($error, $records[0]->context['exception']);
    }

    public function test_a_real_failed_audit_query_still_returns_500_and_rolls_back_case_intake(): void
    {
        config(['app.debug' => false]);
        $actor = User::factory()->investigator()->create();
        $handler = $this->captureLogs();
        AuditLog::creating(function (AuditLog $log) {
            if ($log->action_performed === AuditLog::ACTION_CREATE) {
                DB::insert('insert into nonexistent_synthetic_audit (note) values (?)', ['CONFIDENTIAL_AUDIT_BINDING']);
            }
        });

        try {
            $this->actingAs($actor)->post(route('cases.store'), [
                'docket_no' => 'SYNTHETIC-LOG-1',
                'case_title' => 'CONFIDENTIAL_CASE_TITLE',
                'incident_details' => 'CONFIDENTIAL_INCIDENT',
                'complexity_weight' => 3,
                'is_torture_case' => '0',
                'date_of_docket' => '2026-01-01',
                'victims' => [['name' => 'Synthetic victim', 'status' => 'Test', 'sector' => 'Test']],
                'respondents' => [['name' => 'Synthetic respondent']],
            ])->assertStatus(500)->assertDontSee('CONFIDENTIAL_');
        } finally {
            AuditLog::flushEventListeners();
        }

        foreach (['cases', 'victims', 'respondents', 'case_timelines', 'audit_logs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $records = $handler->getRecords();
        $this->assertCount(1, $records);
        $this->assertSame('Database query failed.', $records[0]->message);
        $this->assertStringNotContainsString('CONFIDENTIAL_', $records[0]->formatted);
    }
}
