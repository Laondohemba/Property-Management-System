import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Subject, debounceTime, map, merge, startWith, switchMap, tap } from 'rxjs';

import { SessionStore } from '../../../core/auth/session.store';
import { toProblem } from '../../../core/bff/problem';
import { PageMeta, Room, RoomType } from '../../../core/bff/types';
import { PageTitle } from '../../../shared/ui/page-title';
import { Pagination } from '../../../shared/ui/pagination';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { InventoryApi } from './inventory.api';

@Component({
  selector: 'pms-room-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, Pagination, StatusBadge],
  templateUrl: './room-list.page.html',
})
export class RoomListPage {
  protected readonly session = inject(SessionStore);
  private readonly api = inject(InventoryApi);

  protected readonly filters = inject(NonNullableFormBuilder).group({
    search: [''],
    roomType: [''],
    floor: [''],
    status: ['all' as 'all' | 'active' | 'inactive'],
  });

  protected readonly rooms = signal<Room[]>([]);
  protected readonly roomTypes = signal<RoomType[]>([]);
  protected readonly floors = signal<string[]>([]);
  protected readonly meta = signal<PageMeta | null>(null);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  private readonly page$ = new Subject<number>();

  constructor() {
    this.api.roomTypes().subscribe({ next: ({ items }) => this.roomTypes.set(items) });

    const filterChanges$ = this.filters.valueChanges.pipe(debounceTime(250), map(() => 1));

    merge(filterChanges$, this.page$)
      .pipe(
        startWith(1),
        tap(() => this.loading.set(true)),
        // switchMap cancels superseded requests (spec §11.4).
        switchMap((page) => {
          const { search, roomType, floor, status } = this.filters.getRawValue();

          return this.api.rooms({
            page,
            search: search.trim() || undefined,
            roomType: roomType || undefined,
            floor: floor || undefined,
            active: status === 'all' ? undefined : status === 'active',
          });
        }),
        takeUntilDestroyed(),
      )
      .subscribe({
        next: ({ items, meta }) => {
          this.rooms.set(items);
          this.meta.set(meta);
          this.floors.set(meta.floors);
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
    this.page$.next(page);
  }
}
