import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';

import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { Room, RoomType } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { InventoryApi } from './inventory.api';

@Component({
  selector: 'pms-room-edit-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError, StatusBadge],
  template: `
    <pms-page-title
      [title]="isNew() ? 'New Room' : 'Room ' + (room()?.number ?? '')"
      [breadcrumbs]="[{ label: 'Settings' }, { label: 'Rooms', link: '/settings/rooms' }, { label: isNew() ? 'New' : 'Edit' }]" />

    @if (error(); as message) {
      <div class="pms-alert-danger mb-5" role="alert">{{ message }}</div>
    }
    @if (saved()) {
      <div class="pms-alert-success mb-5" role="status">Room saved.</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" novalidate class="max-w-3xl">
      <section class="pms-card">
        <h2 class="pms-card-title">Room details</h2>

        @if (activeTypes().length === 0 && !loading()) {
          <div class="pms-alert-danger mb-4" role="alert">
            Create an active room type first. <a routerLink="/settings/room-types/new" class="font-semibold underline">Add room type</a>
          </div>
        }

        <div class="grid gap-4 sm:grid-cols-3">
          <div>
            <label for="number" class="pms-label">Room number</label>
            <input id="number" type="text" formControlName="number" class="pms-input uppercase" />
            <pms-field-error [control]="form.controls.number" />
          </div>
          <div>
            <label for="floor" class="pms-label">Floor</label>
            <input id="floor" type="text" formControlName="floor" placeholder="e.g. 1, G" class="pms-input" />
            <pms-field-error [control]="form.controls.floor" />
          </div>
          <div>
            <label for="sort_order" class="pms-label">Sort order</label>
            <input id="sort_order" type="number" min="0" formControlName="sort_order" class="pms-input" />
          </div>
        </div>

        <div class="mt-4">
          <label for="room_type_id" class="pms-label">Room type</label>
          <select id="room_type_id" formControlName="room_type_id" class="pms-input">
            <option value="" disabled>Select a room type</option>
            @for (type of roomTypes(); track type.id) {
              <option [value]="type.id" [disabled]="!type.is_active && type.id !== room()?.room_type?.id">
                {{ type.name }} ({{ type.code }}){{ type.is_active ? '' : ', inactive' }}
              </option>
            }
          </select>
          <pms-field-error [control]="form.controls.room_type_id" />
        </div>

        <div class="mt-4">
          <label for="notes" class="pms-label">Notes</label>
          <textarea id="notes" rows="3" formControlName="notes" class="pms-input"></textarea>
        </div>

        <label for="is_active" class="mt-4 flex items-center gap-2">
          <input id="is_active" type="checkbox" formControlName="is_active" class="pms-checkbox" />
          <span class="text-sm">Active (part of the property's inventory)</span>
        </label>

        @if (room(); as current) {
          <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-muted">
            Current status:
            <pms-status-badge [value]="current.housekeeping_status.value" [label]="current.housekeeping_status.label" />
            <pms-status-badge [value]="current.occupancy_status.value" [label]="current.occupancy_status.label" />
            <span>Changed from the housekeeping board and front desk.</span>
          </p>
        }
      </section>

      <div class="flex gap-3">
        <button type="submit" class="pms-btn-primary" [disabled]="saving() || loading()">
          {{ saving() ? 'Saving…' : isNew() ? 'Create Room' : 'Save Changes' }}
        </button>
        <a routerLink="/settings/rooms" class="pms-btn-secondary">Back to list</a>
      </div>
    </form>
  `,
})
export class RoomEditPage implements OnInit {
  readonly id = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly api = inject(InventoryApi);
  private readonly router = inject(Router);

  protected readonly isNew = computed(() => !this.id());
  protected readonly room = signal<Room | null>(null);
  protected readonly roomTypes = signal<RoomType[]>([]);
  protected readonly activeTypes = computed(() => this.roomTypes().filter((type) => type.is_active));
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    number: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9][A-Za-z0-9-]*$/)]],
    floor: [''],
    room_type_id: ['', [Validators.required]],
    notes: [''],
    sort_order: [0, [Validators.min(0)]],
    is_active: [true],
  });

  ngOnInit(): void {
    const id = this.id();

    forkJoin({ types: this.api.roomTypes(), room: id ? this.api.room(id) : of(null) }).subscribe({
      next: ({ types, room }) => {
        this.roomTypes.set(types.items);

        if (room) {
          this.room.set(room);
          this.form.patchValue({ ...room, floor: room.floor ?? '', notes: room.notes ?? '', room_type_id: room.room_type.id });
        } else if (types.items.length) {
          this.form.patchValue({ room_type_id: types.items.find((type) => type.is_active)?.id ?? '' });
        }

        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);

    const value = this.form.getRawValue();
    const input = { ...value, floor: value.floor.trim() || null, notes: value.notes.trim() || null };
    const id = this.id();

    (id ? this.api.updateRoom(id, input) : this.api.createRoom(input)).subscribe({
      next: (room) => {
        this.saving.set(false);

        if (id) {
          this.room.set(room);
          this.saved.set(true);
        } else {
          void this.router.navigate(['/settings/rooms'], { replaceUrl: true });
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }
}
