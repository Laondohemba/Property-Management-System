import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged, startWith, switchMap, tap } from 'rxjs';

import { SessionStore } from '../../../core/auth/session.store';
import { PageMeta, Property } from '../../../core/bff/types';
import { toProblem } from '../../../core/bff/problem';
import { PageTitle } from '../../../shared/ui/page-title';
import { PropertiesApi } from './properties.api';

@Component({
  selector: 'pms-property-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle],
  template: `
    <pms-page-title title="Properties" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Properties' }]" />

    <section class="pms-card">
      <div class="flex flex-wrap items-center justify-between gap-3 pt-5 pb-4">
        <h2 class="font-display text-lg font-medium text-navy">
          All properties <span class="text-sm font-normal text-muted">| {{ meta()?.total ?? 0 }} total</span>
        </h2>
        <div class="flex flex-wrap items-center gap-3">
          <label class="sr-only" for="property-search">Search properties</label>
          <input id="property-search" type="search" placeholder="Search name or code" [formControl]="search"
                 class="pms-input w-56" />
          @if (session.isSuperAdmin()) {
            <a routerLink="/settings/properties/new" class="pms-btn-primary">Add property</a>
          }
        </div>
      </div>

      @if (error(); as message) {
        <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
      }

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b-2 border-line text-navy">
            <tr>
              <th scope="col" class="px-3 py-2 font-semibold">Name</th>
              <th scope="col" class="px-3 py-2 font-semibold">Code</th>
              <th scope="col" class="px-3 py-2 font-semibold">Timezone</th>
              <th scope="col" class="px-3 py-2 font-semibold">Currency</th>
              <th scope="col" class="px-3 py-2 font-semibold">Business date</th>
              <th scope="col" class="px-3 py-2 font-semibold">Status</th>
              <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (property of properties(); track property.id) {
              <tr class="border-b border-line last:border-0 hover:bg-primary-soft">
                <td class="px-3 py-3 font-semibold text-navy">{{ property.name }}</td>
                <td class="px-3 py-3"><code class="rounded bg-primary-soft px-1.5 py-0.5 text-navy">{{ property.code }}</code></td>
                <td class="px-3 py-3">{{ property.timezone }}</td>
                <td class="px-3 py-3">{{ property.currency }}</td>
                <td class="px-3 py-3">{{ property.business_date }}</td>
                <td class="px-3 py-3">
                  @if (property.is_active) {
                    <span class="rounded bg-[#d1e7dd] px-2 py-0.5 text-xs font-semibold text-[#0f5132]">Active</span>
                  } @else {
                    <span class="rounded bg-[#e2e3e5] px-2 py-0.5 text-xs font-semibold text-[#41464b]">Inactive</span>
                  }
                </td>
                <td class="px-3 py-3 text-right">
                  <a [routerLink]="['/settings/properties', property.id]" class="text-sm font-semibold hover:underline">Edit</a>
                </td>
              </tr>
            } @empty {
              <tr>
                <td colspan="7" class="px-3 py-8 text-center text-muted">
                  {{ loading() ? 'Loading…' : 'No properties found.' }}
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>

      @if (meta(); as page) {
        @if (page.last_page > 1) {
          <nav class="mt-4 flex items-center justify-end gap-2 text-sm" aria-label="Pagination">
            <button type="button" class="pms-btn-secondary px-3 py-1" [disabled]="page.page <= 1" (click)="go(page.page - 1)">Previous</button>
            <span class="text-muted">Page {{ page.page }} of {{ page.last_page }}</span>
            <button type="button" class="pms-btn-secondary px-3 py-1" [disabled]="page.page >= page.last_page" (click)="go(page.page + 1)">Next</button>
          </nav>
        }
      }
    </section>
  `,
})
export class PropertyListPage {
  protected readonly session = inject(SessionStore);
  private readonly api = inject(PropertiesApi);

  protected readonly search = new FormControl('', { nonNullable: true });
  protected readonly properties = signal<Property[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  private readonly page$ = new Subject<number>();
  private page = 1;

  constructor() {
    const search$ = this.search.valueChanges.pipe(debounceTime(300), distinctUntilChanged(), startWith(''));

    // switchMap cancels superseded requests (spec §11.4).
    search$
      .pipe(
        tap(() => (this.page = 1)),
        switchMap(() => this.page$.pipe(startWith(1))),
        tap(() => this.loading.set(true)),
        switchMap((page) => this.api.list({ page, search: this.search.value.trim() || undefined })),
        takeUntilDestroyed(),
      )
      .subscribe({
        next: ({ items, meta }) => {
          this.properties.set(items);
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

  protected go(page: number): void {
    this.page = page;
    this.page$.next(page);
  }
}
