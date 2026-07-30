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
exec('php artisan test 2>&1', $output, $status);

if ($status === 0) {
    exit(0);
}

fwrite(STDERR, "php artisan test is failing — fix it before finishing:\n\n".implode("\n", $output)."\n");
exit(2);
