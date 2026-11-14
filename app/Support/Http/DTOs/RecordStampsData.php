<?php

declare(strict_types=1);

namespace App\Support\Http\DTOs;

use Illuminate\Database\Eloquent\Model;
use Spatie\LaravelData\Data;

/**
 * Who created and last updated a record, and when (spec §7.1). The full history is in the audit trail.
 */
class RecordStampsData extends Data
{
    public function __construct(
        public ?string $created_at,
        public ?ActorRefData $created_by,
        public ?string $updated_at,
        public ?ActorRefData $updated_by,
    ) {}

    /**
     * Uses the createdBy / updatedBy relations (HasRecordStamps); loads them when missing.
     */
    public static function fromModel(Model $model): self
    {
        $model->loadMissing(['createdBy', 'updatedBy']);

        return new self(
            $model->getAttribute('created_at')?->toIso8601ZuluString(),
            ActorRefData::fromUser($model->getRelation('createdBy')),
            $model->getAttribute('updated_at')?->toIso8601ZuluString(),
            ActorRefData::fromUser($model->getRelation('updatedBy')),
        );
    }
}
