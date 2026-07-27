<?php

/**
 * PostToolUse hook (Write|Edit): flags conventions from CLAUDE.md that are
 * easy to break by accident.
 *
 *   1. Schema changes made outside database/migrations/.
 *   2. A model $fillable column that no migration ever creates.
 *   3. Date math on case_timelines fields written outside CaseDeadlineService
 *      (only once that class exists — it does not yet).
 *
 * Runs after the write, so it reads the file from disk rather than parsing
 * the tool payload, and reports instead of blocking. Exit 2 hands the findings
 * back to Claude to fix.
 */

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$path = $input['tool_input']['file_path'] ?? '';
$root = rtrim(str_replace('\\', '/', getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');

if (! str_ends_with($path, '.php') || ! is_file($path)) {
    exit(0);
}

// Tell the Stop hook there is PHP worth re-testing.
touch(sys_get_temp_dir().'/casetrack-tests-pending-'.md5($root));

$relative = ltrim(str_replace('\\', '/', $path), '/');
$relative = str_starts_with($relative, ltrim($root, '/'))
    ? ltrim(substr($relative, strlen(ltrim($root, '/'))), '/')
    : $relative;

$source = file_get_contents($path);
$findings = [];

// 1. Schema changes belong in a migration, never anywhere else.
if (! str_starts_with($relative, 'database/migrations/')
    && preg_match('/Schema::(create|table|drop|dropIfExists|rename)\b/', $source, $m)) {
    $findings[] = "{$relative} calls Schema::{$m[1]}() outside database/migrations/. "
        .'Schema changes go in a migration — see CLAUDE.md, Conventions.';
}

// 2. A $fillable entry with no migration behind it means the column was added
//    to the model but never to the database.
if (str_contains($relative, 'app/Models/')
    && preg_match('/protected\s+\$fillable\s*=\s*\[(.*?)\];/s', $source, $m)) {
    $migrations = implode(' ', array_map('file_get_contents', glob("{$root}/database/migrations/*.php")));

    preg_match_all("/'([a-z0-9_]+)'/i", $m[1], $columns);

    foreach (array_unique($columns[1]) as $column) {
        if (! str_contains($migrations, "'{$column}'")) {
            $findings[] = "{$relative} lists '{$column}' in \$fillable, but no migration creates that column. "
                .'Write a migration for it, or drop it from $fillable.';
        }
    }
}

// 3. Deadline math belongs in one testable place. Dormant until it exists.
$service = 'app/Services/CaseDeadlineService.php';

if (file_exists("{$root}/{$service}")
    && $relative !== $service
    && str_starts_with($relative, 'app/')
    && preg_match('/\b(date_of_docket|date_submission_rop|extension_30_days|submission_60th_day|submission_120th_day|target_date_fir|date_fir_submitted)\b/', $source)
    && preg_match('/\b(addDays?|subDays?|addMonths?|subMonths?|diffIn\w+|Carbon::|strtotime)\b/', $source, $m)) {
    $findings[] = "{$relative} does date math ({$m[1]}) on a case_timelines field outside CaseDeadlineService. "
        .'Move it into App\Services\CaseDeadlineService so it stays unit-testable — see CLAUDE.md, Conventions.';
}

if ($findings === []) {
    exit(0);
}

fwrite(STDERR, "CaseTrack convention check:\n\n- ".implode("\n- ", $findings)."\n");
exit(2);
