import { DatePipe, JsonPipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { Subject, debounceTime, map, merge, startWith, switchMap, tap } from 'rxjs';

import { SessionStore } from '../../core/auth/session.store';
import { toProblem } from '../../core/bff/problem';
import { AuditEntry, AuditLog, PageMeta } from '../../core/bff/types';
import { PageTitle } from '../../shared/ui/page-title';
import { Pagination } from '../../shared/ui/pagination';
import { StatusBadge } from '../../shared/ui/status-badge';
import { AuditApi } from './audit.api';

interface Change {
  field: string;
  before: unknown;
  after: unknown;
}

/**
 * Read-only audit trail (spec §5.12). The full viewer with export arrives in Phase 1 (AUD-03).
 */
@Component({
  selector: 'pms-audit-log-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, JsonPipe, ReactiveFormsModule, PageTitle, Pagination, StatusBadge],
  templateUrl: './audit-log.page.html',
})
export class AuditLogPage {
  protected readonly session = inject(SessionStore);
  private readonly api = inject(AuditApi);

  protected readonly filters = inject(NonNullableFormBuilder).group({
    log: ['' as AuditLog | ''],
    search: [''],
    from: [''],
    to: [''],
  });

  protected readonly entries = signal<AuditEntry[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly expanded = signal<string | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly page$ = new Subject<number>();

  constructor() {
    merge(this.filters.valueChanges.pipe(debounceTime(300), map(() => 1)), this.page$)
      .pipe(
        startWith(1),
        tap(() => this.loading.set(true)),
        switchMap((page) => {
          const { log, search, from, to } = this.filters.getRawValue();
          return this.api.list({ page, log, search: search.trim() || undefined, from: from || undefined, to: to || undefined });
        }),
        takeUntilDestroyed(),
      )
      .subscribe({
        next: ({ items, meta }) => {
          this.entries.set(items);
          this.meta.set(meta);
          this.loading.set(false);
        },
        error: (error: unknown) => {
          const problem = toProblem(error);
          this.error.set(problem.detail ?? problem.title);
          this.loading.set(false);
        },
      });
  }

  protected toggle(id: string): void {
    this.expanded.update((current) => (current === id ? null : id));
  }

  /** Before/after pairs for every field that changed. */
  protected changes(entry: AuditEntry): Change[] {
    const before = entry.old ?? {};
    const after = entry.new ?? {};
    const fields = [...new Set([...Object.keys(before), ...Object.keys(after)])];

    return fields.map((field) => ({ field, before: before[field], after: after[field] }));
  }

  protected hasDetails(entry: AuditEntry): boolean {
    return Object.keys(entry.details).length > 0;
  }

  protected format(value: unknown): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'object') return JSON.stringify(value);
    return String(value);
  }
}
