<?php

/**
 * Stop hook: a turn that touched PHP must not end with a failing suite.
 *
 * Runs once per turn rather than once per edit — a mid-task edit is expected
 * to fail, and the full suite is ~11s. Exit 2 sends the failures back to
 * Claude to fix before it can finish.
 */

$input = json_decode(stream_get_contents(STDIN), true) ?: [];
$root = rtrim(str_replace('\\', '/', getenv('CLAUDE_PROJECT_DIR') ?: getcwd()), '/');
$pending = sys_get_temp_dir().'/casetrack-tests-pending-'.md5($root);

// stop_hook_active means Claude is already responding to a previous block;
// blocking again on a suite that will not go green loops forever.
if (! empty($input['stop_hook_active']) || ! file_exists($pending)) {
    exit(0);
}

unlink($pending);
chdir($root);

// Bare "php" resolves via PATH, which on this project is not the php this
// suite is meant to run under (CLAUDE.md, Stack: XAMPP's bundled PHP "cannot
// run artisan at all" / "never fall back to the XAMPP one"). The working
// binary's path is machine-specific, so it isn't hardcoded here — it's read
// from .claude/launch.json (gitignored, per-teammate, already required setup
// per the same doc) the same way the Browser pane dev server resolves it.
// Bare "php" is only the fallback for a machine that hasn't set that file up.
$php = 'php';
$launch = "{$root}/.claude/launch.json";

if (is_file($launch)) {
    $config = json_decode(file_get_contents($launch), true) ?: [];

    foreach ($config['configurations'] ?? [] as $configuration) {
        if (($configuration['name'] ?? null) === 'casetrack' && ! empty($configuration['runtimeExecutable'])) {
            $php = $configuration['runtimeExecutable'];
            break;
        }
    }
}

exec(escapeshellarg($php).' artisan test 2>&1', $output, $status);

if ($status === 0) {
    exit(0);
}

fwrite(STDERR, "php artisan test is failing — fix it before finishing:\n\n".implode("\n", $output)."\n");
exit(2);
