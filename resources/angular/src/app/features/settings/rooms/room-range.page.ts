import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { RoomType } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { InventoryApi } from './inventory.api';

const MAX_ROOMS = 200;

/** Create a run of numbered rooms at once, e.g. 101–120 on floor 1. */
@Component({
  selector: 'pms-room-range-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError],
  template: `
    <pms-page-title
      title="Add Rooms in Bulk"
      [breadcrumbs]="[{ label: 'Settings' }, { label: 'Rooms', link: '/settings/rooms' }, { label: 'Add range' }]" />

    @if (error(); as message) {
      <div class="pms-alert-danger mb-5" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" novalidate class="max-w-3xl">
      <section class="pms-card">
        <h2 class="pms-card-title">Room range</h2>
        <p class="text-sm text-muted">All rooms in the range get the same room type and floor. Nothing is created if any number already exists.</p>

        <div class="mt-4">
          <label for="room_type_id" class="pms-label">Room type</label>
          <select id="room_type_id" formControlName="room_type_id" class="pms-input">
            <option value="" disabled>Select a room type</option>
            @for (type of roomTypes(); track type.id) {
              <option [value]="type.id">{{ type.name }} ({{ type.code }})</option>
            }
          </select>
          <pms-field-error [control]="form.controls.room_type_id" />
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-4">
          <div>
            <label for="prefix" class="pms-label">Prefix</label>
            <input id="prefix" type="text" maxlength="5" formControlName="prefix" placeholder="Optional" class="pms-input uppercase placeholder:normal-case" />
            <pms-field-error [control]="form.controls.prefix" />
          </div>
          <div>
            <label for="from" class="pms-label">From</label>
            <input id="from" type="number" min="0" formControlName="from" class="pms-input" />
            <pms-field-error [control]="form.controls.from" />
          </div>
          <div>
            <label for="to" class="pms-label">To</label>
            <input id="to" type="number" min="0" formControlName="to" class="pms-input" />
            <pms-field-error [control]="form.controls.to" />
          </div>
          <div>
            <label for="floor" class="pms-label">Floor</label>
            <input id="floor" type="text" formControlName="floor" placeholder="e.g. 1" class="pms-input" />
          </div>
        </div>

        <p class="mt-4 text-sm" [class.text-danger]="count() > maxRooms || count() < 1">
          @if (count() >= 1) {
            Creates <strong>{{ count() }}</strong> rooms: {{ preview() }}
          } @else {
            "To" must be greater than or equal to "From".
          }
          @if (count() > maxRooms) {
            (at most {{ maxRooms }} at a time)
          }
        </p>
      </section>

      <div class="flex gap-3">
        <button type="submit" class="pms-btn-primary" [disabled]="saving() || count() < 1 || count() > maxRooms">
          {{ saving() ? 'Creating…' : 'Create Rooms' }}
        </button>
        <a routerLink="/settings/rooms" class="pms-btn-secondary">Cancel</a>
      </div>
    </form>
  `,
})
export class RoomRangePage {
  private readonly api = inject(InventoryApi);
  private readonly router = inject(Router);

  protected readonly maxRooms = MAX_ROOMS;
  protected readonly roomTypes = signal<RoomType[]>([]);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    room_type_id: ['', [Validators.required]],
    prefix: ['', [Validators.pattern(/^[A-Za-z0-9-]*$/)]],
    from: [101, [Validators.required, Validators.min(0)]],
    to: [110, [Validators.required, Validators.min(0)]],
    floor: ['1'],
  });

  private readonly value = toSignal(this.form.valueChanges, { initialValue: this.form.getRawValue() });

  protected readonly count = computed(() => (Number(this.value().to) || 0) - (Number(this.value().from) || 0) + 1);
  protected readonly preview = computed(() => {
    const { prefix = '', from = 0, to = 0 } = this.value();
    const label = (n: number) => `${(prefix ?? '').toUpperCase()}${n}`;

    return this.count() > 3 ? `${label(from)}, ${label(from + 1)} … ${label(to)}` : Array.from({ length: this.count() }, (_, i) => label(from + i)).join(', ');
  });

  constructor() {
    this.api.roomTypes({ active: true }).subscribe(({ items }) => {
      this.roomTypes.set(items);
      if (items.length) {
        this.form.patchValue({ room_type_id: items[0]!.id });
      }
    });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.error.set(null);

    const { room_type_id, prefix, from, to, floor } = this.form.getRawValue();

    this.api.createRoomRange({ room_type_id, prefix: prefix || null, from, to, floor: floor.trim() || null }).subscribe({
      next: () => void this.router.navigate(['/settings/rooms']),
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }
}
