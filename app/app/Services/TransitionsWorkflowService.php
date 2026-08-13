<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
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

        $transition = $this->transitionFor($model, $to, $actor);

        if ($transition === null) {
            throw new AuthorizationException('This status transition is not allowed.');
        }

        $preconditionError = ($transition['preconditions'] ?? null) !== null
            ? ($transition['preconditions'])($model)
            : null;

        if ($preconditionError !== null) {
            throw ValidationException::withMessages([
                'status' => [$preconditionError],
            ]);
        }

        $from = $model->getRawOriginal('status');

        DB::transaction(function () use ($model, $transition): void {
            $locked = $model->newQuery()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->first();

            if ($locked !== null) {
                $model = $locked;
            }

            $model->setAttribute('status', $transition['to']);
            $model->forceFill($transition['on_apply'] ?? []);
            $model->save();
        });

        app(AuditLogService::class)->record(
            $actor->id,
            "{$model->getTable()}.status_changed",
            $model->getTable(),
            (string) $model->getKey(),
            before: ['status' => $from],
            after: ['status' => $transition['to']],
        );

        return $model;
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
}
