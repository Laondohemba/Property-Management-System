import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, map, merge, startWith, switchMap, tap } from 'rxjs';

import { toProblem } from '../../../core/bff/problem';
import { PageMeta, StaffOptions, StaffStatus, StaffUser } from '../../../core/bff/types';
import { PageTitle } from '../../../shared/ui/page-title';
import { Pagination } from '../../../shared/ui/pagination';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { StaffApi } from './staff.api';

@Component({
  selector: 'pms-user-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, ReactiveFormsModule, RouterLink, PageTitle, Pagination, StatusBadge],
  template: `
    <pms-page-title title="Users" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Users' }]" />

    <section class="pms-card">
      <div class="flex flex-wrap items-center justify-between gap-3 pt-5 pb-4">
        <h2 class="font-display text-lg font-medium text-navy">Staff <span class="text-sm font-normal text-muted">| {{ meta()?.total ?? 0 }} people</span></h2>
        <a routerLink="/settings/users/invite" class="pms-btn-primary">Invite staff</a>
      </div>

      <form [formGroup]="filters" class="mb-4 grid gap-3 sm:grid-cols-3" role="search" aria-label="Filter users">
        <div>
          <label for="user_search" class="sr-only">Name or email</label>
          <input id="user_search" type="search" formControlName="search" placeholder="Search name or email" class="pms-input" />
        </div>
        <div>
          <label for="user_property" class="sr-only">Property</label>
          <select id="user_property" formControlName="property" class="pms-input">
            <option value="">All properties</option>
            @for (property of options()?.properties ?? []; track property.id) {
              <option [value]="property.id">{{ property.name }}</option>
            }
          </select>
        </div>
        <div>
          <label for="user_status" class="sr-only">Status</label>
          <select id="user_status" formControlName="status" class="pms-input">
            <option value="">Any status</option>
            <option value="active">Active</option>
            <option value="invited">Invited</option>
            <option value="inactive">Inactive</option>
          </select>
        </div>
      </form>

      @if (error(); as message) {
        <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
      }

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b-2 border-line text-navy">
            <tr>
              <th scope="col" class="px-3 py-2 font-semibold">Name</th>
              <th scope="col" class="px-3 py-2 font-semibold">Access</th>
              <th scope="col" class="px-3 py-2 font-semibold">2FA</th>
              <th scope="col" class="px-3 py-2 font-semibold">Last sign-in</th>
              <th scope="col" class="px-3 py-2 font-semibold">Status</th>
              <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (user of users(); track user.id) {
              <tr class="border-b border-line last:border-0 hover:bg-primary-soft">
                <td class="px-3 py-3">
                  <span class="font-semibold text-navy">{{ user.name }}</span>
                  <span class="block text-xs text-muted">{{ user.email }}</span>
                </td>
                <td class="px-3 py-3">
                  @if (user.is_super_admin) {
                    <span class="block"><span class="font-semibold text-navy">Super Admin</span><span class="ml-1 text-xs text-muted">· all properties</span></span>
                  }
                  @for (assignment of user.assignments; track assignment.property.id) {
                    <span class="block">{{ assignment.property.name }}<span class="ml-1 text-xs text-muted">· {{ assignment.role?.label }}</span></span>
                  }
                </td>
                <td class="px-3 py-3">
                  <pms-status-badge [value]="user.two_factor_enabled ? 'active' : 'inactive'" [label]="user.two_factor_enabled ? 'On' : 'Off'" />
                </td>
                <td class="px-3 py-3 whitespace-nowrap">{{ user.last_login_at ? (user.last_login_at | date: 'medium') : '—' }}</td>
                <td class="px-3 py-3"><pms-status-badge [value]="statusTone(user.status)" [label]="statusLabel(user.status)" /></td>
                <td class="px-3 py-3 text-right">
                  <a [routerLink]="['/settings/users', user.id]" class="font-semibold hover:underline">Manage</a>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="px-3 py-8 text-center text-muted">{{ loading() ? 'Loading…' : 'No users match.' }}</td></tr>
            }
          </tbody>
        </table>
      </div>

      <pms-pagination [meta]="meta()" (pageChange)="page$.next($event)" />
    </section>
  `,
})
export class UserListPage {
  private readonly api = inject(StaffApi);

  protected readonly filters = inject(NonNullableFormBuilder).group({
    search: [''],
    property: [''],
    status: ['' as StaffStatus | ''],
  });

  protected readonly users = signal<StaffUser[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly options = signal<StaffOptions | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly page$ = new Subject<number>();

  constructor() {
    merge(this.filters.valueChanges.pipe(debounceTime(250), map(() => 1)), this.page$)
      .pipe(
        startWith(1),
        tap(() => this.loading.set(true)),
        switchMap((page) => {
          const { search, property, status } = this.filters.getRawValue();
          return this.api.list({ page, search: search.trim() || undefined, property, status });
        }),
        takeUntilDestroyed(),
      )
      .subscribe({
        next: ({ items, meta }) => {
          this.users.set(items);
          this.meta.set(meta);
          this.options.set(meta);
          this.loading.set(false);
        },
        error: (error: unknown) => {
          const problem = toProblem(error);
          this.error.set(problem.detail ?? problem.title);
          this.loading.set(false);
        },
      });
  }

  protected statusLabel(status: StaffStatus): string {
    return { active: 'Active', invited: 'Invited', inactive: 'Inactive' }[status];
  }

  protected statusTone(status: StaffStatus): string {
    return { active: 'active', invited: 'cleaning', inactive: 'inactive' }[status];
  }
}
