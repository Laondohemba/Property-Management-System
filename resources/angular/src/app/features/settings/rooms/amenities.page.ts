import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { FormsModule, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { SessionStore } from '../../../core/auth/session.store';
import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { Amenity } from '../../../core/bff/types';
import { ConfirmService } from '../../../shared/ui/confirm-dialog';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { InventoryApi } from './inventory.api';

/** The property's amenity catalogue: add, rename inline and remove. */
@Component({
  selector: 'pms-amenities-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, FormsModule, PageTitle, FieldError],
  template: `
    <pms-page-title title="Amenities" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Amenities' }]" />

    <section class="pms-card max-w-3xl">
      <h2 class="pms-card-title">
        {{ session.activeProperty()?.name }} <span class="text-sm font-normal text-muted">| {{ amenities().length }} amenities</span>
      </h2>

      @if (error(); as message) {
        <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
      }

      <form [formGroup]="form" (ngSubmit)="add()" class="mb-5 flex items-start gap-3" novalidate>
        <div class="flex-1">
          <label for="new_amenity" class="sr-only">New amenity</label>
          <input id="new_amenity" type="text" formControlName="name" placeholder="e.g. Ocean view" class="pms-input" />
          <pms-field-error [control]="form.controls.name" />
        </div>
        <button type="submit" class="pms-btn-primary" [disabled]="busy()">Add</button>
      </form>

      <ul class="divide-y divide-line">
        @for (amenity of amenities(); track amenity.id) {
          <li class="flex items-center gap-3 py-2.5">
            @if (editingId() === amenity.id) {
              <label [for]="'rename_' + amenity.id" class="sr-only">Amenity name</label>
              <input [id]="'rename_' + amenity.id" type="text" [(ngModel)]="editName" class="pms-input flex-1"
                     (keydown.enter)="$event.preventDefault(); saveRename(amenity)" (keydown.escape)="editingId.set(null)" />
              <button type="button" class="pms-btn-primary px-3 py-1" (click)="saveRename(amenity)" [disabled]="busy()">Save</button>
              <button type="button" class="pms-btn-secondary px-3 py-1" (click)="editingId.set(null)">Cancel</button>
            } @else {
              <span class="flex-1 text-sm font-semibold text-heading">{{ amenity.name }}</span>
              <span class="text-xs text-muted">{{ amenity.room_types_count ?? 0 }} {{ (amenity.room_types_count ?? 0) === 1 ? 'room type' : 'room types' }}</span>
              <button type="button" class="cursor-pointer text-sm text-primary hover:underline" (click)="startRename(amenity)">Rename</button>
              <button type="button" class="cursor-pointer text-sm text-danger hover:underline" (click)="remove(amenity)" [disabled]="busy()">Remove</button>
            }
          </li>
        } @empty {
          <li class="py-6 text-center text-sm text-muted">{{ loading() ? 'Loading…' : 'No amenities yet.' }}</li>
        }
      </ul>
    </section>
  `,
})
export class AmenitiesPage {
  protected readonly session = inject(SessionStore);
  private readonly api = inject(InventoryApi);

  protected readonly amenities = signal<Amenity[]>([]);
  protected readonly loading = signal(true);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  private readonly confirm = inject(ConfirmService);
  protected readonly editingId = signal<string | null>(null);
  protected editName = '';

  protected readonly form = inject(NonNullableFormBuilder).group({ name: ['', [Validators.required, Validators.maxLength(100)]] });

  constructor() {
    this.reload();
  }

  protected add(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.busy.set(true);
    this.api.createAmenity(this.form.getRawValue().name).subscribe({
      next: () => {
        this.form.reset();
        this.busy.set(false);
        this.reload();
      },
      error: (error: unknown) => {
        this.busy.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }

  protected startRename(amenity: Amenity): void {
    this.editName = amenity.name;
    this.editingId.set(amenity.id);
  }

  protected saveRename(amenity: Amenity): void {
    this.busy.set(true);
    this.api.renameAmenity(amenity.id, this.editName).subscribe({
      next: () => {
        this.busy.set(false);
        this.editingId.set(null);
        this.reload();
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  protected async remove(amenity: Amenity): Promise<void> {
    const count = amenity.room_types_count ?? 0;
    const confirmed = await this.confirm.ask({
      title: 'Remove amenity',
      message: count
        ? `"${amenity.name}" will be removed from ${count} room type${count === 1 ? '' : 's'}.`
        : `"${amenity.name}" will be removed from this property's amenity list.`,
      confirmLabel: 'Remove',
      tone: 'danger',
    });

    if (!confirmed) {
      return;
    }

    this.busy.set(true);
    this.api.deleteAmenity(amenity.id).subscribe({
      next: () => {
        this.busy.set(false);
        this.reload();
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  private reload(): void {
    this.api.amenities().subscribe({
      next: (amenities) => {
        this.amenities.set(amenities);
        this.loading.set(false);
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  private fail(error: unknown): void {
    const problem = toProblem(error);
    this.busy.set(false);
    this.loading.set(false);
    this.error.set(problem.errors?.[0]?.message ?? problem.detail ?? problem.title);
  }
}
