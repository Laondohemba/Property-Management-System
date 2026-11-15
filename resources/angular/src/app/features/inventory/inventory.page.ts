import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { Problem, applyProblemToForm, toProblem } from '../../core/bff/problem';
import { BlockType, BlockTypeOption, InventoryBlock, InventoryDay, InventoryGrid } from '../../core/bff/types';
import { ConfirmService } from '../../shared/ui/confirm-dialog';
import { FieldError } from '../../shared/ui/field-error';
import { PageTitle } from '../../shared/ui/page-title';
import { RecordStampsLine } from '../../shared/ui/record-stamps';
import { InventoryApi } from './inventory.api';

/** Adds days to a YYYY-MM-DD date without time-zone surprises. */
export function addDays(date: string, days: number): string {
  const value = new Date(`${date}T00:00:00Z`);
  value.setUTCDate(value.getUTCDate() + days);
  return value.toISOString().slice(0, 10);
}

/** Nights listed in a 409 inventory_unavailable problem. */
export function unavailableNights(problem: Problem): { date: string; available: number }[] {
  const nights = (problem as Problem & { nights?: unknown }).nights;
  return Array.isArray(nights) ? (nights as { date: string; available: number }[]) : [];
}

const WEEKDAYS = [
  { value: 1, label: 'Mon' },
  { value: 2, label: 'Tue' },
  { value: 3, label: 'Wed' },
  { value: 4, label: 'Thu' },
  { value: 5, label: 'Fri' },
  { value: 6, label: 'Sat' },
  { value: 7, label: 'Sun' },
];

/**
 * Master inventory (CHN-01): availability per room type and night, blocks (CAL-06) and
 * overbooking allowances. Every change is checked and applied by the server under row locks.
 */
@Component({
  selector: 'pms-inventory-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, PageTitle, FieldError, RecordStampsLine],
  templateUrl: './inventory.page.html',
})
export class InventoryPage {
  private readonly api = inject(InventoryApi);
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly confirm = inject(ConfirmService);

  protected readonly grid = signal<InventoryGrid | null>(null);
  protected readonly blockTypes = signal<BlockTypeOption[]>([]);
  protected readonly blocks = signal<InventoryBlock[]>([]);
  protected readonly blockStatus = signal<'active' | 'all'>('active');
  protected readonly from = signal<string | null>(null);
  protected readonly nights = signal(14);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);
  protected readonly blockError = signal<string | null>(null);
  protected readonly blockNights = signal<{ date: string; available: number }[]>([]);
  protected readonly overbookingError = signal<string | null>(null);
  protected readonly saving = signal<'block' | 'overbooking' | null>(null);

  protected readonly weekdays = WEEKDAYS;
  protected readonly windowSizes = [7, 14, 30];
  protected readonly roomTypes = computed(() => this.grid()?.rows.map((row) => row.room_type) ?? []);

  protected readonly blockForm = this.fb.group({
    room_type_id: ['', Validators.required],
    type: ['out_of_order' as BlockType, Validators.required],
    quantity: [1, [Validators.required, Validators.min(1), Validators.max(500)]],
    from_date: ['', Validators.required],
    to_date: ['', Validators.required],
    reason: ['', [Validators.required, Validators.maxLength(500)]],
    expires_at: [''],
  });

  protected readonly overbookingForm = this.fb.group({
    room_type_id: ['', Validators.required],
    from: ['', Validators.required],
    to: ['', Validators.required],
    allowance: [0, [Validators.required, Validators.min(0), Validators.max(50)]],
  });
  protected readonly overbookingDays = signal<Set<number>>(new Set(WEEKDAYS.map((day) => day.value)));

  constructor() {
    this.load();
    this.loadBlocks();
  }

  protected load(from = this.from()): void {
    this.loading.set(true);
    this.error.set(null);

    // First visit: the server's business date is not known yet, so ask for today in the browser
    // and move to the business date once it arrives.
    const start = from ?? new Date().toISOString().slice(0, 10);

    this.api.grid(start, addDays(start, this.nights() - 1)).subscribe({
      next: ({ grid, meta }) => {
        if (from === null && grid.from !== grid.first_date) {
          this.from.set(grid.first_date);
          this.load(grid.first_date);
          return;
        }

        this.from.set(grid.from);
        this.grid.set(grid);
        this.blockTypes.set(meta.block_types);
        this.prefillForms(grid);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }

  protected loadBlocks(): void {
    this.api.blocks(this.blockStatus()).subscribe({
      next: ({ items }) => this.blocks.set(items),
      error: (error: unknown) => this.error.set(toProblem(error).detail ?? 'Blocks could not be loaded.'),
    });
  }

  protected shift(direction: -1 | 1): void {
    const from = this.from();
    if (from) {
      this.load(addDays(from, direction * this.nights()));
    }
  }

  protected goToToday(): void {
    const grid = this.grid();
    if (grid) {
      this.load(grid.first_date);
    }
  }

  protected jumpTo(date: string): void {
    if (/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      this.load(date);
    }
  }

  protected setWindow(nights: number): void {
    this.nights.set(nights);
    this.load();
  }

  protected showBlocks(status: 'active' | 'all'): void {
    this.blockStatus.set(status);
    this.loadBlocks();
  }

  protected toggleDay(day: number, checked: boolean): void {
    this.overbookingDays.update((current) => {
      const next = new Set(current);
      if (checked) {
        next.add(day);
      } else {
        next.delete(day);
      }
      return next;
    });
  }

  protected submitBlock(): void {
    if (this.blockForm.invalid) {
      this.blockForm.markAllAsTouched();
      return;
    }

    const value = this.blockForm.getRawValue();
    this.saving.set('block');
    this.blockError.set(null);
    this.blockNights.set([]);

    this.api
      .createBlock({
        ...value,
        reason: value.reason.trim(),
        expires_at: value.expires_at ? new Date(value.expires_at).toISOString() : null,
      })
      .subscribe({
        next: (block) => {
          this.saving.set(null);
          this.notice.set(`${block.quantity} × ${block.room_type.name} blocked from ${block.from_date} to ${block.to_date}.`);
          // reset() (not patchValue) so the cleared fields are pristine again and show no errors.
          this.blockForm.controls.reason.reset('');
          this.blockForm.controls.expires_at.reset('');
          this.blockForm.controls.quantity.reset(1);
          this.load();
          this.loadBlocks();
        },
        error: (error: unknown) => {
          this.saving.set(null);
          const problem = toProblem(error);
          this.blockNights.set(unavailableNights(problem));
          this.blockError.set(applyProblemToForm(this.blockForm, problem));
        },
      });
  }

  protected submitOverbooking(): void {
    const days = [...this.overbookingDays()].sort();
    if (this.overbookingForm.invalid || days.length === 0) {
      this.overbookingForm.markAllAsTouched();
      if (days.length === 0) {
        this.overbookingError.set('Choose at least one weekday.');
      }
      return;
    }

    this.saving.set('overbooking');
    this.overbookingError.set(null);

    this.api
      .setOverbooking({ ...this.overbookingForm.getRawValue(), weekdays: days.length === 7 ? null : days })
      .subscribe({
        next: ({ nights_changed }) => {
          this.saving.set(null);
          this.notice.set(
            nights_changed === 0
              ? 'Overbooking was already set that way; nothing changed.'
              : `Overbooking updated on ${nights_changed} ${nights_changed === 1 ? 'night' : 'nights'}.`,
          );
          this.load();
        },
        error: (error: unknown) => {
          this.saving.set(null);
          const problem = toProblem(error);
          const nights = unavailableNights(problem).map((night) => night.date);
          const message = applyProblemToForm(this.overbookingForm, problem);
          this.overbookingError.set(message && nights.length ? `${message} (${nights.join(', ')})` : message);
        },
      });
  }

  protected async release(block: InventoryBlock): Promise<void> {
    const confirmed = await this.confirm.ask({
      title: 'Release block',
      message: `${block.quantity} × ${block.room_type.name} (${block.from_date} to ${block.to_date}) will go back on sale.`,
      confirmLabel: 'Release',
      tone: 'danger',
    });
    if (!confirmed) {
      return;
    }

    this.api.releaseBlock(block.id).subscribe({
      next: () => {
        this.notice.set(`Block released: ${block.room_type.name} is back on sale for those nights.`);
        this.load();
        this.loadBlocks();
      },
      error: (error: unknown) => this.error.set(toProblem(error).detail ?? 'The block could not be released.'),
    });
  }

  /** Styling of one cell: full, nearly full or open. */
  protected cellTone(day: InventoryDay): string {
    if (day.total === 0) {
      return 'text-subtle';
    }
    if (day.available <= 0) {
      return 'bg-danger-soft text-danger-fg font-semibold';
    }
    if (day.available / day.total <= 0.2) {
      return 'bg-warning-soft text-warning-fg';
    }
    return 'text-heading';
  }

  protected cellLabel(roomType: string, day: InventoryDay): string {
    const parts = [`${roomType}, ${this.longDate(day.date)}: ${day.available} of ${day.total} available`];
    if (day.blocked) parts.push(`${day.blocked} blocked`);
    if (day.sold) parts.push(`${day.sold} sold`);
    if (day.overbook_allowance) parts.push(`overbooking allowance ${day.overbook_allowance}`);
    return parts.join(', ');
  }

  protected weekday(date: string): string {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, { weekday: 'short', timeZone: 'UTC' });
  }

  protected dayOfMonth(date: string): string {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, { day: 'numeric', month: 'short', timeZone: 'UTC' });
  }

  protected longDate(date: string): string {
    return new Date(`${date}T00:00:00Z`).toLocaleDateString(undefined, {
      weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC',
    });
  }

  protected isWeekend(date: string): boolean {
    const day = new Date(`${date}T00:00:00Z`).getUTCDay();
    return day === 0 || day === 6;
  }

  protected expiry(value: string | null): string {
    return value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : 'No expiry';
  }

  private prefillForms(grid: InventoryGrid): void {
    const firstType = grid.rows[0]?.room_type.id ?? '';
    if (!this.blockForm.controls.room_type_id.value) {
      this.blockForm.patchValue({ room_type_id: firstType, from_date: grid.first_date, to_date: grid.first_date });
    }
    if (!this.overbookingForm.controls.room_type_id.value) {
      this.overbookingForm.patchValue({ room_type_id: firstType, from: grid.first_date, to: addDays(grid.first_date, 6) });
    }
  }
}
