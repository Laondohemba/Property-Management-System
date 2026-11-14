<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Audit\Support\AuditChain;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Recomputes every audit hash chain and reports the first entry that does not match (AUD-02).
 * Exit code 1 means the trail has been tampered with.
 */
#[Signature('audit:verify')]
#[Description('Verify that the audit trail hash chains are intact')]
class VerifyAuditChain extends Command
{
    public function handle(): int
    {
        $chains = AuditEntry::query()->select('organization_id')->distinct()->pluck('organization_id');
        $broken = 0;
        $checked = 0;

        foreach ($chains as $organizationId) {
            $previous = null;

            AuditEntry::query()
                ->when($organizationId === null, fn ($query) => $query->whereNull('organization_id'), fn ($query) => $query->where('organization_id', $organizationId))
                ->orderBy('id')
                ->chunkById(500, function ($entries) use (&$previous, &$broken, &$checked, $organizationId): bool {
                    foreach ($entries as $entry) {
                        $checked++;

                        if ($entry->prev_hash !== $previous || AuditChain::hash($entry) !== $entry->hash) {
                            $this->components->error(sprintf(
                                'Chain %s is broken at entry #%d (%s).',
                                $organizationId ?? 'global',
                                $entry->id,
                                $entry->description,
                            ));
                            $broken++;

                            return false;
                        }

                        $previous = $entry->hash;
                    }

                    return true;
                });
        }

        if ($broken > 0) {
            return self::FAILURE;
        }

        $this->components->info("Audit trail intact: {$checked} entries in {$chains->count()} chain(s).");

        return self::SUCCESS;
    }
}
