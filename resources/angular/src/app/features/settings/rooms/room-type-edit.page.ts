import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';

import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { Amenity, BedTypeOptionData, RoomType, RoomTypeInput } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { RecordStampsLine } from '../../../shared/ui/record-stamps';
import { RecordHistory } from '../../audit/record-history';
import { PageTitle } from '../../../shared/ui/page-title';
import { InventoryApi } from './inventory.api';
import { RoomTypeGallery } from './room-type-gallery';

/** Create (no id) or edit a room type: capacity, beds, amenities and images (SYS-04). */
@Component({
  selector: 'pms-room-type-edit-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError, RecordStampsLine, RecordHistory, RoomTypeGallery],
  templateUrl: './room-type-edit.page.html',
})
export class RoomTypeEditPage implements OnInit {
  readonly id = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly api = inject(InventoryApi);
  private readonly router = inject(Router);

  protected readonly isNew = computed(() => !this.id());
  protected readonly roomType = signal<RoomType | null>(null);
  protected readonly amenities = signal<Amenity[]>([]);
  protected readonly bedTypes = signal<BedTypeOptionData[]>([]);
  protected readonly selectedAmenities = signal<Set<string>>(new Set());
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    code: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9][A-Za-z0-9_-]*$/)]],
    description: [''],
    base_occupancy: [2, [Validators.required, Validators.min(1), Validators.max(20)]],
    max_adults: [2, [Validators.required, Validators.min(1), Validators.max(20)]],
    max_children: [0, [Validators.required, Validators.min(0), Validators.max(20)]],
    max_occupancy: [2, [Validators.required, Validators.min(1), Validators.max(30)]],
    bed_configuration: this.fb.array([this.bedGroup('queen', 1)]),
    sort_order: [0, [Validators.min(0)]],
    is_active: [true],
  });

  protected get beds() {
    return this.form.controls.bed_configuration;
  }

  ngOnInit(): void {
    const id = this.id();

    forkJoin({
      types: this.api.roomTypes({ perPage: 1 }),
      amenities: this.api.amenities(),
      roomType: id ? this.api.roomType(id) : of(null),
    }).subscribe({
      next: ({ types, amenities, roomType }) => {
        this.bedTypes.set(types.meta.bed_types);
        this.amenities.set(amenities);

        if (roomType) {
          this.roomType.set(roomType);
          this.beds.clear();
          roomType.bed_configuration.forEach((bed) => this.beds.push(this.bedGroup(bed.type, bed.count)));
          this.form.patchValue({ ...roomType, description: roomType.description ?? '' });
          this.selectedAmenities.set(new Set(roomType.amenities.map((amenity) => amenity.id)));
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

  protected addBed(): void {
    this.beds.push(this.bedGroup('single', 1));
  }

  protected removeBed(index: number): void {
    if (this.beds.length > 1) {
      this.beds.removeAt(index);
    }
  }

  protected toggleAmenity(id: string, checked: boolean): void {
    this.selectedAmenities.update((current) => {
      const next = new Set(current);
      if (checked) {
        next.add(id);
      } else {
        next.delete(id);
      }
      return next;
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
    const input: RoomTypeInput = {
      ...value,
      description: value.description || null,
      amenity_ids: [...this.selectedAmenities()],
    };

    const id = this.id();
    (id ? this.api.updateRoomType(id, input) : this.api.createRoomType(input)).subscribe({
      next: (roomType) => {
        this.saving.set(false);

        if (id) {
          this.roomType.set(roomType);
          this.saved.set(true);
        } else {
          void this.router.navigate(['/settings/room-types', roomType.id], { replaceUrl: true });
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }

  private bedGroup(type: string, count: number) {
    return this.fb.group({
      type: [type, [Validators.required]],
      count: [count, [Validators.required, Validators.min(1), Validators.max(10)]],
    });
  }
}
