<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Central status engine (docs/Workflows.md). The ONLY way to change a
 * workflow status: direct column writes are banned.
 *
 * @template TModel of Model
 */
final class TransitionsWorkflowService
{
    /**
     * @param  class-string<TModel>  $modelClass
     * @param  array<string, list<array{to: string, actor: string, preconditions?: Closure(TModel): ?string, on_apply?: array<string, mixed>}>>  $transitions
     */
    public function __construct(
        private readonly string $modelClass,
        private readonly array $transitions,
    ) {}

    /**
     * @param  TModel  $model
     */
    public function canTransition(Model $model, string $to, User $actor): bool
    {
        return $this->transitionFor($model, $to, $actor) !== null;
    }

    /**
     * Apply a transition inside a transaction, writing the audit log.
     *
     * @param  TModel  $model
     * @param  array<string, mixed>  $extra
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function transition(Model $model, string $to, User $actor, array $extra = []): Model
    {
        if (! $model instanceof $this->modelClass) {
            throw new \InvalidArgumentException('Model does not match the workflow class.');
        }

        $from = null;

        $updated = DB::transaction(function () use ($model, $to, $actor, &$from): Model {
            $locked = $this->lockModel($model);

            // Re-evaluate both authorization and preconditions against the
            // locked row so concurrent transitions cannot use stale state.
            $transition = $this->transitionFor($locked, $to, $actor);

            if ($transition === null) {
                throw new AuthorizationException('This status transition is not allowed.');
            }

            $preconditionError = ($transition['preconditions'] ?? null) !== null
                ? ($transition['preconditions'])($locked)
                : null;

            if ($preconditionError !== null) {
                throw ValidationException::withMessages([
                    'status' => [$preconditionError],
                ]);
            }

            $from = $locked->getRawOriginal('status');
            $locked->setAttribute('status', $transition['to']);
            $locked->forceFill($transition['on_apply'] ?? []);
            $locked->save();

            return $locked;
        });

        app(AuditLogService::class)->record(
            $actor->id,
            "{$updated->getTable()}.status_changed",
            $updated->getTable(),
            (string) $updated->getKey(),
            before: ['status' => $from],
            after: ['status' => $to],
        );

        return $updated;
    }

    /**
     * @param  TModel  $model
     * @return array{to: string, actor: string, preconditions?: Closure(TModel): ?string, on_apply?: array<string, mixed>}|null
     */
    private function transitionFor(Model $model, string $to, User $actor): ?array
    {
        $from = $model->getRawOriginal('status');

        $candidates = $this->transitions[$from] ?? [];

        foreach ($candidates as $transition) {
            if ($transition['to'] !== $to) {
                continue;
            }

            if (! $this->actorMatches($transition['actor'], $actor)) {
                continue;
            }

            return $transition;
        }

        return null;
    }

    private function actorMatches(string $pattern, User $actor): bool
    {
        if ($pattern === '*') {
            return true;
        }

        return in_array($actor->role->value, explode('|', $pattern), true);
    }

    /**
     * @param  TModel  $model
     * @return TModel
     */
    private function lockModel(Model $model): Model
    {
        $locked = $model->newQuery()
            ->whereKey($model->getKey())
            ->lockForUpdate()
            ->first();

        if ($locked === null) {
            throw (new ModelNotFoundException)->setModel($this->modelClass, [$model->getKey()]);
        }

        if (! $locked instanceof $this->modelClass) {
            throw new \InvalidArgumentException('Locked model does not match the workflow class.');
        }

        return $locked;
    }
}
