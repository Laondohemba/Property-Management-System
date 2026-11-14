import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, input } from '@angular/core';

import { RecordStamps } from '../../core/bff/types';

/**
 * "Created by … · Last updated by …" line (spec §7.1). `compact` renders the list-cell variant.
 */
@Component({
  selector: 'pms-record-stamps',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe],
  template: `
    @if (compact()) {
      <span class="whitespace-nowrap">{{ (stamps().updated_at | date: 'mediumDate') ?? '—' }}</span>
      <span class="block text-xs text-muted">{{ stamps().updated_by?.name ?? 'System' }}</span>
    } @else {
      <p class="text-xs text-muted">
        Created
        @if (stamps().created_at) {
          {{ stamps().created_at | date: 'medium' }}
        }
        by <span class="font-semibold text-heading">{{ stamps().created_by?.name ?? 'System' }}</span>
        <span class="mx-1">·</span>
        Last updated
        @if (stamps().updated_at) {
          {{ stamps().updated_at | date: 'medium' }}
        }
        by <span class="font-semibold text-heading">{{ stamps().updated_by?.name ?? 'System' }}</span>
      </p>
    }
  `,
})
export class RecordStampsLine {
  readonly stamps = input.required<RecordStamps>();
  readonly compact = input(false);
}
