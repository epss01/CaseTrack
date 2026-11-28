<?php

namespace App\Services;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class SubmissionService
{
    public function run(Request $request, Closure $authorize, Closure $next): Response
    {
        $authorize(true, false);
        $id = strtolower(Validator::make($request->all(), [
            '_submission_id' => ['required', 'uuid'],
        ])->validate()['_submission_id']);
        $actor = $request->user() ? 'user:'.$request->user()->id : 'guest:'.$request->session()->getId();
        $actorHash = $this->digest($actor);
        $action = $request->route()->getName() ?? $request->route()->getActionName();
        $target = $request->route('case')?->id ?? $request->route('user')?->id ?? $request->route('investigator')?->id;
        $input = $this->sorted($request->except(['_token', '_submission_id']));
        $requestHash = $this->digest(json_encode([$request->method(), $action, $target, $input], JSON_THROW_ON_ERROR));
        $level = DB::transactionLevel();

        DB::beginTransaction();
        try {
            // InnoDB's unique insert waits for an uncommitted claimant, including rollback.
            DB::table('submission_receipts')->insertOrIgnore([
                'id' => $id, 'actor_hash' => $actorHash, 'action' => $action,
                'target_id' => $target, 'request_hash' => $requestHash,
            ]);
            $receipt = DB::table('submission_receipts')->where('id', $id)->lockForUpdate()->first();
            abort_if($receipt === null, 500);
            abort_unless(hash_equals($receipt->actor_hash, $actorHash), 403);
            $replaying = $receipt->completed_at !== null;
            $matches = $receipt->action === $action && $receipt->target_id == $target
                && hash_equals($receipt->request_hash, $requestHash);

            // Recheck after waiting: reassignment or account changes may have revoked access.
            $authorize($replaying, true, $matches ? $receipt : null);
            abort_unless($matches, 409,
                __('This submission identifier was already used with different input. Reload the form before submitting a new action.'));

            if ($replaying) {
                DB::commit();

                return redirect($receipt->redirect_path)
                    ->with('status', __('This submission was already completed.'));
            }

            $response = $next($request);
            // Laravel's routing pipeline may render an exception instead of throwing it.
            if (($response->exception ?? null) !== null) {
                DB::rollBack($level);
                // Only denials need rendering outside rollback; other exceptions were already reported.
                if ($response->exception instanceof AuthorizationException
                    || ($response->exception instanceof HttpExceptionInterface && $response->exception->getStatusCode() === 403)) {
                    throw $response->exception;
                }

                return $response;
            }
            if (! $response->isRedirect() || in_array('errors', $request->session()->get('_flash.new', []), true)) {
                DB::rollBack($level);

                return $response;
            }
            $location = $response->headers->get('Location');
            $origin = $request->getSchemeAndHttpHost();
            abort_unless(str_starts_with($location, $origin.'/'), 500);
            $path = substr($location, strlen($origin));
            $resultCaseId = $action === 'cases.store' && preg_match('~^/cases/(\d+)(?:\?|$)~', $path, $match)
                ? (int) $match[1] : null;
            DB::table('submission_receipts')->where('id', $id)->update([
                'redirect_path' => $path,
                'result_case_id' => $resultCaseId,
                'requires_supervisor' => $action === 'cases.import.store' && $request->user()->isSupervisor(),
                'completed_at' => now(),
            ]);
            DB::commit();

            return $response;
        } catch (Throwable $exception) {
            if (DB::transactionLevel() > $level) {
                DB::rollBack($level);
            }
            throw $exception;
        }
    }

    private function digest(string $value): string
    {
        return hash_hmac('sha256', $value, config('app.key'));
    }

    private function sorted(array $input): array
    {
        ksort($input);
        foreach ($input as &$value) {
            if (is_array($value)) {
                $value = $this->sorted($value);
            }
        }

        return $input;
    }
}
