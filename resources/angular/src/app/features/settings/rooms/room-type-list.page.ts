import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SessionStore } from '../../../core/auth/session.store';
import { toProblem } from '../../../core/bff/problem';
import { RoomType } from '../../../core/bff/types';
import { PageTitle } from '../../../shared/ui/page-title';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { InventoryApi } from './inventory.api';

@Component({
  selector: 'pms-room-type-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, PageTitle, StatusBadge],
  template: `
    <pms-page-title title="Room Types" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Room Types' }]" />

    <section class="pms-card">
      <div class="flex flex-wrap items-center justify-between gap-3 pt-5 pb-4">
        <h2 class="font-display text-lg font-medium text-navy">
          {{ session.activeProperty()?.name }} <span class="text-sm font-normal text-muted">| {{ roomTypes().length }} room types</span>
        </h2>
        <a routerLink="/settings/room-types/new" class="pms-btn-primary">Add room type</a>
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
              <th scope="col" class="px-3 py-2 font-semibold">Occupancy</th>
              <th scope="col" class="px-3 py-2 font-semibold">Beds</th>
              <th scope="col" class="px-3 py-2 font-semibold">Rooms</th>
              <th scope="col" class="px-3 py-2 font-semibold">Status</th>
              <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (type of roomTypes(); track type.id) {
              <tr class="border-b border-line last:border-0 hover:bg-primary-soft">
                <td class="px-3 py-3">
                  <span class="font-semibold text-navy">{{ type.name }}</span>
                  @if (type.amenities.length) {
                    <span class="block text-xs text-muted">{{ amenityNames(type) }}</span>
                  }
                </td>
                <td class="px-3 py-3"><code class="rounded bg-primary-soft px-1.5 py-0.5 text-navy">{{ type.code }}</code></td>
                <td class="px-3 py-3 whitespace-nowrap">
                  {{ type.base_occupancy }} base · {{ type.max_occupancy }} max
                  <span class="block text-xs text-muted">{{ type.max_adults }} adults, {{ type.max_children }} children</span>
                </td>
                <td class="px-3 py-3">{{ beds(type) }}</td>
                <td class="px-3 py-3">{{ type.rooms_count ?? 0 }}</td>
                <td class="px-3 py-3">
                  <pms-status-badge [value]="type.is_active ? 'active' : 'inactive'" [label]="type.is_active ? 'Active' : 'Inactive'" />
                </td>
                <td class="px-3 py-3 text-right">
                  <a [routerLink]="['/settings/room-types', type.id]" class="font-semibold hover:underline">Edit</a>
                </td>
              </tr>
            } @empty {
              <tr>
                <td colspan="7" class="px-3 py-8 text-center text-muted">
                  {{ loading() ? 'Loading…' : 'No room types yet. Add one to start setting up rooms.' }}
                </td>
              </tr>
            }
          </tbody>
        </table>
      </div>
    </section>
  `,
})
export class RoomTypeListPage {
  protected readonly session = inject(SessionStore);
  private readonly api = inject(InventoryApi);

  protected readonly roomTypes = signal<RoomType[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    this.api.roomTypes().subscribe({
      next: ({ items }) => {
        this.roomTypes.set(items);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }

  protected beds(type: RoomType): string {
    return type.bed_configuration.map((bed) => `${bed.count} × ${bed.label ?? bed.type}`).join(', ');
  }

  protected amenityNames(type: RoomType): string {
    return type.amenities.map((amenity) => amenity.name).join(' · ');
  }
}
