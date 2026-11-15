import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormArray, NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { forkJoin, of } from 'rxjs';

import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { MealPlan, PricePreview, RatePlan, RatePlanInput, RatePlanOptions, RoomTypeOption } from '../../core/bff/types';
import { FieldError } from '../../shared/ui/field-error';
import { PageTitle } from '../../shared/ui/page-title';
import { RecordStampsLine } from '../../shared/ui/record-stamps';
import { RecordHistory } from '../audit/record-history';
import { formatMoney, fromMinor, moneyStep, toMinor } from '../../shared/util/money';
import { RatesApi } from './rates.api';

/**
 * Create (no id) or edit a rate plan (RTE-01, RTE-07): details, policies, taxes, prices per
 * room type with occupancy supplements, and a live price preview.
 */
@Component({
  selector: 'pms-rate-plan-edit-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError, RecordStampsLine, RecordHistory],
  templateUrl: './rate-plan-edit.page.html',
})
export class RatePlanEditPage implements OnInit {
  readonly id = input<string>();

  private readonly api = inject(RatesApi);
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly router = inject(Router);

  protected readonly plan = signal<RatePlan | null>(null);
  protected readonly options = signal<RatePlanOptions | null>(null);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly selectedTaxes = signal<Set<string>>(new Set());
  protected readonly preview = signal<PricePreview | null>(null);
  protected readonly previewError = signal<string | null>(null);

  protected readonly isNew = computed(() => !this.id());
  protected readonly currency = computed(() => this.options()?.currency ?? 'NGN');
  protected readonly step = computed(() => moneyStep(this.currency()));

  protected readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    code: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9][A-Za-z0-9_-]*$/)]],
    description: [''],
    meal_plan: ['room_only' as MealPlan, Validators.required],
    cancellation_policy_id: [''],
    deposit_policy_id: [''],
    min_price: [null as number | null, Validators.min(0)],
    max_price: [null as number | null, Validators.min(0)],
    sort_order: [0, Validators.min(0)],
    is_active: [true],
    change_reason: ['', Validators.maxLength(255)],
    prices: this.fb.array<ReturnType<RatePlanEditPage['priceGroup']>>([]),
  });

  protected readonly previewForm = this.fb.group({
    room_type_id: [''],
    adults: [2, [Validators.required, Validators.min(1), Validators.max(20)]],
    children: [0, [Validators.min(0), Validators.max(20)]],
    nights: [1, [Validators.required, Validators.min(1), Validators.max(365)]],
  });

  protected get prices(): FormArray<ReturnType<RatePlanEditPage['priceGroup']>> {
    return this.form.controls.prices;
  }

  ngOnInit(): void {
    const id = this.id();

    forkJoin({ options: this.api.options(), plan: id ? this.api.ratePlan(id) : of(null) }).subscribe({
      next: ({ options, plan }) => {
        this.options.set(options);
        this.buildPrices(options.room_types, plan);

        if (plan) {
          this.fill(plan);
        } else {
          this.selectedTaxes.set(new Set(options.taxes.map((tax) => tax.id)));
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

  protected toggleTax(id: string, checked: boolean): void {
    this.selectedTaxes.update((current) => {
      const next = new Set(current);
      if (checked) {
        next.add(id);
      } else {
        next.delete(id);
      }
      return next;
    });
  }

  protected policySummary(kind: 'cancellation' | 'deposit'): string | null {
    const options = this.options();
    const id = kind === 'cancellation' ? this.form.controls.cancellation_policy_id.value : this.form.controls.deposit_policy_id.value;
    const list = kind === 'cancellation' ? options?.cancellation_policies : options?.deposit_policies;
    return list?.find((policy) => policy.id === id)?.summary ?? null;
  }

  protected soldRoomTypes(): RoomTypeOption[] {
    return this.prices.controls.filter((group) => group.controls.sold.value).map((group) => group.controls.room_type.value);
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const value = this.form.getRawValue();
    const currency = this.currency();
    const input: RatePlanInput = {
      name: value.name.trim(),
      code: value.code.trim().toUpperCase(),
      description: value.description.trim() || null,
      meal_plan: value.meal_plan,
      cancellation_policy_id: value.cancellation_policy_id || null,
      deposit_policy_id: value.deposit_policy_id || null,
      min_price_minor: toMinor(value.min_price, currency),
      max_price_minor: toMinor(value.max_price, currency),
      sort_order: value.sort_order,
      is_active: value.is_active,
      tax_ids: [...this.selectedTaxes()],
      prices: value.prices
        .filter((row) => row.sold)
        .map((row) => ({
          room_type_id: row.room_type.id,
          base_price_minor: toMinor(row.base, currency) ?? 0,
          extra_adult_minor: toMinor(row.extra_adult, currency) ?? 0,
          extra_child_minor: toMinor(row.extra_child, currency) ?? 0,
        })),
      change_reason: value.change_reason.trim() || null,
    };

    this.saving.set(true);
    this.saved.set(false);
    this.error.set(null);

    const id = this.id();
    this.api.save(input, id).subscribe({
      next: (plan) => {
        this.saving.set(false);
        if (id) {
          this.fill(plan);
          this.saved.set(true);
          this.runPreview();
        } else {
          void this.router.navigate(['/rate-plans', plan.id], { replaceUrl: true });
        }
      },
      error: (error: unknown) => {
        this.saving.set(false);
        const problem = toProblem(error);
        this.error.set(applyProblemToForm(this.form, {
          ...problem,
          errors: problem.errors?.map((e) => ({ ...e, field: e.field.replace(/_price_minor$/, '_price') })),
        }) ?? problem.errors?.[0]?.message ?? null);
      },
    });
  }

  protected runPreview(): void {
    const id = this.id();
    const value = this.previewForm.getRawValue();
    if (!id || !value.room_type_id || this.previewForm.invalid) {
      return;
    }

    this.previewError.set(null);
    this.api.preview(id, value.room_type_id, value.adults, value.children, value.nights).subscribe({
      next: (preview) => this.preview.set(preview),
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.preview.set(null);
        this.previewError.set(problem.errors?.[0]?.message ?? problem.detail ?? 'The price could not be worked out.');
      },
    });
  }

  protected money(minor: number | null | undefined): string {
    return formatMoney(minor, this.currency());
  }

  private priceGroup(roomType: RoomTypeOption, sold: boolean, base: number | null, extraAdult: number | null, extraChild: number | null) {
    return this.fb.group({
      room_type: [roomType],
      sold: [sold],
      base: [base, [Validators.min(0)]],
      extra_adult: [extraAdult ?? 0, [Validators.min(0)]],
      extra_child: [extraChild ?? 0, [Validators.min(0)]],
    });
  }

  private buildPrices(roomTypes: RoomTypeOption[], plan: RatePlan | null): void {
    const currency = this.currency();
    this.prices.clear();
    for (const roomType of roomTypes) {
      const price = plan?.prices.find((p) => p.room_type.id === roomType.id);
      this.prices.push(this.priceGroup(
        roomType,
        !!price || (!plan && roomType.is_active),
        fromMinor(price?.base_price_minor, currency),
        fromMinor(price?.extra_adult_minor, currency),
        fromMinor(price?.extra_child_minor, currency),
      ));
    }
  }

  private fill(plan: RatePlan): void {
    const currency = this.currency();
    this.plan.set(plan);
    this.form.patchValue({
      name: plan.name,
      code: plan.code,
      description: plan.description ?? '',
      meal_plan: plan.meal_plan,
      cancellation_policy_id: plan.cancellation_policy?.id ?? '',
      deposit_policy_id: plan.deposit_policy?.id ?? '',
      min_price: fromMinor(plan.min_price_minor, currency),
      max_price: fromMinor(plan.max_price_minor, currency),
      sort_order: plan.sort_order,
      is_active: plan.is_active,
      change_reason: '',
    });
    this.selectedTaxes.set(new Set(plan.taxes.map((tax) => tax.id)));

    const options = this.options();
    if (options) {
      this.buildPrices(options.room_types, plan);
    }

    if (!this.previewForm.controls.room_type_id.value && plan.prices.length) {
      this.previewForm.patchValue({ room_type_id: plan.prices[0].room_type.id, adults: plan.prices[0].room_type.base_occupancy });
      this.runPreview();
    }
  }
}
