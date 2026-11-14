import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, effect, inject, input, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SessionStore } from '../../core/auth/session.store';
import { AuditEntry } from '../../core/bff/types';
import { AuditApi, AuditSubjectType } from './audit.api';

/**
 * Recent audit entries for one record, shown on edit screens to people who may see the audit log.
 */
@Component({
  selector: 'pms-record-history',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, RouterLink],
  template: `
    @if (canView()) {
      <section class="pms-card">
        <h2 class="pms-card-title">History <span class="text-sm font-normal text-muted">| latest changes</span></h2>
        <ol class="space-y-3">
          @for (entry of entries(); track entry.id) {
            <li class="border-l-2 border-line pl-3">
              <p class="text-sm">
                <span class="font-semibold text-navy">{{ entry.causer?.name ?? 'System' }}</span>
                <span class="text-muted"> · {{ entry.created_at | date: 'medium' }}</span>
              </p>
              <p class="text-sm">{{ entry.description }}</p>
              @for (change of changes(entry); track change.field) {
                <p class="text-xs text-muted">
                  <span class="font-mono text-navy">{{ change.field }}</span>:
                  <span class="text-[#842029]">{{ change.before }}</span> → <span class="text-[#0f5132]">{{ change.after }}</span>
                </p>
              }
            </li>
          } @empty {
            <li class="text-sm text-muted">{{ loading() ? 'Loading…' : 'No recorded changes yet.' }}</li>
          }
        </ol>
        <a routerLink="/audit" class="mt-4 inline-block text-sm hover:underline">Open audit log</a>
      </section>
    }
  `,
})
export class RecordHistory {
  readonly type = input.required<AuditSubjectType>();
  readonly id = input.required<string>();

  private readonly session = inject(SessionStore);
  private readonly api = inject(AuditApi);

  protected readonly canView = computed(() => this.session.isSuperAdmin() || this.session.can('audit.view'));
  protected readonly entries = signal<AuditEntry[]>([]);
  protected readonly loading = signal(true);

  constructor() {
    effect(() => {
      const [type, id] = [this.type(), this.id()];

      if (!this.canView() || !id) {
        return;
      }

      this.loading.set(true);
      this.api.list({ subjectType: type, subject: id, perPage: 10 }).subscribe({
        next: ({ items }) => {
          this.entries.set(items);
          this.loading.set(false);
        },
        error: () => this.loading.set(false),
      });
    });
  }

  protected changes(entry: AuditEntry): { field: string; before: string; after: string }[] {
    const before = entry.old ?? {};
    const after = entry.new ?? {};
    const show = (value: unknown) => (value === null || value === undefined || value === '' ? '—' : typeof value === 'object' ? JSON.stringify(value) : String(value));

    return entry.old
      ? Object.keys({ ...before, ...after }).map((field) => ({ field, before: show(before[field]), after: show(after[field]) }))
      : [];
  }
}
