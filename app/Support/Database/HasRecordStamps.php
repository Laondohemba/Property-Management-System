<?php

declare(strict_types=1);

namespace App\Support\Database;

use App\Modules\Identity\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * created_by / updated_by on business tables (spec §7.1), surfaced on list and edit screens.
 *
 * @mixin Model
 */
trait HasRecordStamps
{
    /**
     * Columns to eager load for the stamp line, so lists avoid N+1 queries.
     *
     * @return array<string, \Closure>
     */
    public static function stampRelations(): array
    {
        $columns = fn (BelongsTo $query) => $query->select(['id', 'public_id', 'name']);

        return ['createdBy' => $columns, 'updatedBy' => $columns];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
