<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

/**
 * Row-level tenancy: limits queries to the signed-in user's organisation (spec §2.7).
 * Derived from the authenticated user on every query; never cached in process memory.
 *
 * @implements Scope<Model>
 */
class OrganizationScope implements Scope
{
    /**
     * @param  Builder<covariant Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $organizationId = self::currentOrganizationId();

        if ($organizationId !== null) {
            $builder->where($model->qualifyColumn('organization_id'), $organizationId);
        }
    }

    public static function currentOrganizationId(): ?int
    {
        $user = Auth::user();

        return $user instanceof Model ? $user->getAttribute('organization_id') : null;
    }
}
