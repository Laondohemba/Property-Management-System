import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { Option, Tax, TaxCalculation } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { RecordStampsLine } from '../../../shared/ui/record-stamps';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { formatMoney, fromMinor, moneyStep, toMinor } from '../../../shared/util/money';
import { PricingSetupApi } from './pricing-setup.api';

/**
 * Taxes and fees of the active property (spec §2.8): VAT, service charge, levies. No rate is
 * hard-coded; rate plans choose which apply.
 */
@Component({
  selector: 'pms-taxes-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, PageTitle, FieldError, RecordStampsLine, StatusBadge],
  template: `
    <pms-page-title title="Taxes & Fees" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Taxes & Fees' }]" />

    @if (error(); as message) {
      <div class="pms-alert-danger mb-5" role="alert">{{ message }}</div>
    }
    @if (notice(); as message) {
      <div class="pms-alert-success mb-5" role="status">{{ message }}</div>
    }

    <div class="grid gap-x-[30px] xl:grid-cols-[3fr_2fr]">
      <section class="pms-card" aria-labelledby="taxes-title">
        <h2 id="taxes-title" class="pms-card-title">Taxes and fees <span class="text-sm font-normal text-muted">| {{ currency() }}</span></h2>
        @if (taxes().length) {
          <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
              <thead class="border-b-2 border-line text-heading">
                <tr>
                  <th scope="col" class="px-3 py-2 font-semibold">Name</th>
                  <th scope="col" class="px-3 py-2 font-semibold">Charged as</th>
                  <th scope="col" class="px-3 py-2 font-semibold">Amount</th>
                  <th scope="col" class="px-3 py-2 font-semibold">Last updated</th>
                  <th scope="col" class="px-3 py-2 font-semibold">Status</th>
                  <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                @for (tax of taxes(); track tax.id) {
                  <tr class="border-b border-line last:border-0" [class.bg-primary-soft]="editing()?.id === tax.id">
                    <td class="px-3 py-3">
                      <span class="font-semibold text-heading">{{ tax.name }}</span>
                      <code class="ml-1 rounded bg-primary-soft px-1.5 py-0.5 text-xs text-heading">{{ tax.code }}</code>
                    </td>
                    <td class="px-3 py-3">{{ tax.calculation_label }}</td>
                    <td class="px-3 py-3 whitespace-nowrap">
                      {{ amount(tax) }}
                      @if (tax.is_inclusive) {
                        <span class="block text-xs text-muted">included in prices</span>
                      } @else {
                        <span class="block text-xs text-muted">added on top</span>
                      }
                    </td>
                    <td class="px-3 py-3"><pms-record-stamps [stamps]="tax.stamps" [compact]="true" /></td>
                    <td class="px-3 py-3"><pms-status-badge [value]="tax.is_active ? 'active' : 'inactive'" [label]="tax.is_active ? 'Active' : 'Inactive'" /></td>
                    <td class="px-3 py-3 text-right">
                      <button type="button" class="font-semibold hover:underline" (click)="edit(tax)">Edit</button>
                    </td>
                  </tr>
                }
              </tbody>
            </table>
          </div>
        } @else if (!loading()) {
          <p class="text-sm text-muted">No taxes yet. Add VAT, service charge or any levy your property collects.</p>
        }
      </section>

      <section class="pms-card" aria-labelledby="tax-form-title">
        <h2 id="tax-form-title" class="pms-card-title">{{ editing() ? 'Edit ' + editing()!.name : 'Add a tax or fee' }}</h2>
        <form [formGroup]="form" (ngSubmit)="submit()" novalidate class="space-y-4">
          <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
            <div>
              <label for="tax_name" class="pms-label">Name</label>
              <input id="tax_name" type="text" formControlName="name" class="pms-input" placeholder="e.g. VAT" />
              <pms-field-error [control]="form.controls.name" />
            </div>
            <div>
              <label for="tax_code" class="pms-label">Code</label>
              <input id="tax_code" type="text" formControlName="code" class="pms-input uppercase" />
              <pms-field-error [control]="form.controls.code" />
            </div>
          </div>

          <div>
            <label for="tax_calculation" class="pms-label">Charged as</label>
            <select id="tax_calculation" formControlName="calculation" class="pms-input">
              @for (option of calculations(); track option.value) {
                <option [value]="option.value">{{ option.label }}</option>
              }
            </select>
          </div>

          @if (isPercentage()) {
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label for="tax_rate" class="pms-label">Percentage</label>
                <input id="tax_rate" type="number" step="0.0001" min="0" max="100" formControlName="rate" class="pms-input" />
                <pms-field-error [control]="form.controls.rate" />
              </div>
              <label for="tax_inclusive" class="flex items-center gap-2 sm:mt-7">
                <input id="tax_inclusive" type="checkbox" formControlName="is_inclusive" class="pms-checkbox" />
                <span class="text-sm">Included in room prices</span>
              </label>
            </div>
          } @else {
            <div>
              <label for="tax_amount" class="pms-label">Amount ({{ currency() }})</label>
              <input id="tax_amount" type="number" [step]="step()" min="0" formControlName="amount" class="pms-input" />
              <pms-field-error [control]="form.controls.amount" />
            </div>
          }

          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label for="tax_sort" class="pms-label">Order</label>
              <input id="tax_sort" type="number" min="0" formControlName="sort_order" class="pms-input" />
            </div>
            <label for="tax_active" class="flex items-center gap-2 sm:mt-7">
              <input id="tax_active" type="checkbox" formControlName="is_active" class="pms-checkbox" />
              <span class="text-sm">Active</span>
            </label>
          </div>

          @if (formError(); as message) {
            <p class="text-sm text-danger" role="alert">{{ message }}</p>
          }

          <div class="flex gap-3">
            <button type="submit" class="pms-btn-primary" [disabled]="saving()">{{ saving() ? 'Saving…' : editing() ? 'Save changes' : 'Add' }}</button>
            @if (editing()) {
              <button type="button" class="pms-btn-secondary" (click)="reset()">Cancel</button>
            }
          </div>
        </form>
        <p class="mt-4 text-xs text-muted">
          Included percentages are already part of room prices; everything else is added on top. Fixed amounts are per room night,
          per stay or per guest per night.
        </p>
      </section>
    </div>
  `,
})
export class TaxesPage {
  private readonly api = inject(PricingSetupApi);
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly taxes = signal<Tax[]>([]);
  protected readonly calculations = signal<Option[]>([]);
  protected readonly currency = signal('NGN');
  protected readonly editing = signal<Tax | null>(null);
  protected readonly loading = signal(true);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);
  protected readonly formError = signal<string | null>(null);

  protected readonly form = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    code: ['', [Validators.required, Validators.maxLength(20), Validators.pattern(/^[A-Za-z0-9][A-Za-z0-9_-]*$/)]],
    calculation: ['percentage' as TaxCalculation, Validators.required],
    rate: ['' as string],
    amount: [null as number | null],
    is_inclusive: [false],
    sort_order: [0, Validators.min(0)],
    is_active: [true],
  });

  private readonly calculation = toSignal(this.form.controls.calculation.valueChanges, { initialValue: this.form.controls.calculation.value });
  protected readonly isPercentage = computed(() => this.calculation() === 'percentage');
  protected readonly step = computed(() => moneyStep(this.currency()));

  constructor() {
    this.load();
  }

  protected amount(tax: Tax): string {
    return tax.calculation === 'percentage' ? `${tax.rate}%` : formatMoney(tax.amount_minor, this.currency());
  }

  protected edit(tax: Tax): void {
    this.editing.set(tax);
    this.notice.set(null);
    this.formError.set(null);
    this.form.reset({
      name: tax.name,
      code: tax.code,
      calculation: tax.calculation,
      rate: tax.rate ?? '',
      amount: fromMinor(tax.amount_minor, this.currency()),
      is_inclusive: tax.is_inclusive,
      sort_order: tax.sort_order,
      is_active: tax.is_active,
    });
  }

  protected reset(): void {
    this.editing.set(null);
    this.formError.set(null);
    this.form.reset({ calculation: 'percentage', rate: '', amount: null, is_inclusive: false, sort_order: this.taxes().length + 1, is_active: true });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const value = this.form.getRawValue();
    const percentage = value.calculation === 'percentage';
    const editing = this.editing();
    this.saving.set(true);
    this.formError.set(null);

    this.api
      .saveTax(
        {
          name: value.name.trim(),
          code: value.code.trim().toUpperCase(),
          calculation: value.calculation,
          rate: percentage ? `${value.rate}` : null,
          amount_minor: percentage ? null : toMinor(value.amount, this.currency()),
          is_inclusive: percentage && value.is_inclusive,
          sort_order: value.sort_order,
          is_active: value.is_active,
        },
        editing?.id,
      )
      .subscribe({
        next: (tax) => {
          this.saving.set(false);
          this.notice.set(`${tax.name} saved.`);
          this.reset();
          this.load();
        },
        error: (error: unknown) => {
          this.saving.set(false);
          const problem = toProblem(error);
          // Server fields amount_minor map onto the "amount" control.
          problem.errors = problem.errors?.map((e) => (e.field === 'amount_minor' ? { ...e, field: 'amount' } : e));
          this.formError.set(applyProblemToForm(this.form, problem));
        },
      });
  }

  private load(): void {
    this.api.taxes().subscribe({
      next: ({ items, meta }) => {
        this.taxes.set(items);
        this.calculations.set(meta.calculations);
        this.currency.set(meta.currency);
        this.loading.set(false);
        if (!this.editing()) {
          this.form.controls.sort_order.setValue(items.length + 1);
        }
      },
      error: (error: unknown) => {
        this.error.set(toProblem(error).detail ?? 'Taxes could not be loaded.');
        this.loading.set(false);
      },
    });
  }
}
