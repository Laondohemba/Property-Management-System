import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { toSignal } from '@angular/core/rxjs-interop';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { Problem, applyProblemToForm, toProblem } from '../../../core/bff/problem';
import {
  BalanceDue,
  CancellationPolicy,
  CancellationPolicyInput,
  DepositDue,
  DepositPolicy,
  DepositPolicyInput,
  DepositType,
  Option,
  PenaltyType,
} from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { fromMinor, moneyStep, toMinor } from '../../../shared/util/money';
import { PricingSetupApi } from './pricing-setup.api';

/** Server field names for amounts end in _minor; the form controls hold major units without it. */
function mapAmountFields(problem: Problem): Problem {
  return { ...problem, errors: problem.errors?.map((e) => ({ ...e, field: e.field.replace(/_minor$/, '') })) };
}

/**
 * Cancellation and deposit policies (RTE-07). Rate plans pick one of each; reservations keep a
 * snapshot taken at booking, so editing a policy never changes existing bookings.
 */
@Component({
  selector: 'pms-policies-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, PageTitle, FieldError, StatusBadge],
  templateUrl: './policies.page.html',
})
export class PoliciesPage {
  private readonly api = inject(PricingSetupApi);
  private readonly fb = inject(NonNullableFormBuilder);

  protected readonly cancellation = signal<CancellationPolicy[]>([]);
  protected readonly deposit = signal<DepositPolicy[]>([]);
  protected readonly penaltyTypes = signal<Option[]>([]);
  protected readonly depositTypes = signal<Option[]>([]);
  protected readonly depositDues = signal<Option[]>([]);
  protected readonly balanceDues = signal<Option[]>([]);
  protected readonly currency = signal('NGN');
  protected readonly step = signal('0.01');
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);
  protected readonly notice = signal<string | null>(null);

  protected readonly editingCancellation = signal<CancellationPolicy | null>(null);
  protected readonly editingDeposit = signal<DepositPolicy | null>(null);
  protected readonly cancellationError = signal<string | null>(null);
  protected readonly depositError = signal<string | null>(null);
  protected readonly saving = signal<'cancellation' | 'deposit' | null>(null);

  protected readonly cancellationForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    non_refundable: [false],
    free_cancellation_hours: [48 as number | null, [Validators.min(0), Validators.max(8760)]],
    penalty_type: ['nights' as PenaltyType],
    penalty_nights: [1 as number | null],
    penalty_percent: ['' as string],
    penalty_amount: [null as number | null],
    no_show_type: ['nights' as PenaltyType],
    no_show_nights: [1 as number | null],
    no_show_percent: ['' as string],
    no_show_amount: [null as number | null],
    guest_text: [''],
    is_active: [true],
  });

  protected readonly depositForm = this.fb.group({
    name: ['', [Validators.required, Validators.maxLength(100)]],
    deposit_type: ['none' as DepositType],
    deposit_percent: ['' as string],
    deposit_nights: [1 as number | null],
    deposit_amount: [null as number | null],
    deposit_due: ['at_booking' as DepositDue],
    due_days_before_arrival: [7 as number | null],
    requires_card_guarantee: [false],
    balance_due: ['at_check_out' as BalanceDue],
    guest_text: [''],
    is_active: [true],
  });

  protected readonly nonRefundable = toSignal(this.cancellationForm.controls.non_refundable.valueChanges, { initialValue: false });
  protected readonly penaltyType = toSignal(this.cancellationForm.controls.penalty_type.valueChanges, { initialValue: 'nights' as PenaltyType });
  protected readonly noShowType = toSignal(this.cancellationForm.controls.no_show_type.valueChanges, { initialValue: 'nights' as PenaltyType });
  protected readonly depositType = toSignal(this.depositForm.controls.deposit_type.valueChanges, { initialValue: 'none' as DepositType });
  protected readonly depositDue = toSignal(this.depositForm.controls.deposit_due.valueChanges, { initialValue: 'at_booking' as DepositDue });

  constructor() {
    this.load();
  }

  protected editCancellation(policy: CancellationPolicy): void {
    const currency = this.currency();
    this.editingCancellation.set(policy);
    this.cancellationError.set(null);
    this.cancellationForm.reset({
      name: policy.name,
      non_refundable: policy.free_cancellation_hours === null,
      free_cancellation_hours: policy.free_cancellation_hours ?? 48,
      penalty_type: policy.penalty_type,
      penalty_nights: policy.penalty_nights ?? 1,
      penalty_percent: policy.penalty_percent ?? '',
      penalty_amount: fromMinor(policy.penalty_amount_minor, currency),
      no_show_type: policy.no_show_type,
      no_show_nights: policy.no_show_nights ?? 1,
      no_show_percent: policy.no_show_percent ?? '',
      no_show_amount: fromMinor(policy.no_show_amount_minor, currency),
      guest_text: policy.guest_text ?? '',
      is_active: policy.is_active,
    });
  }

  protected editDeposit(policy: DepositPolicy): void {
    this.editingDeposit.set(policy);
    this.depositError.set(null);
    this.depositForm.reset({
      name: policy.name,
      deposit_type: policy.deposit_type,
      deposit_percent: policy.deposit_percent ?? '',
      deposit_nights: policy.deposit_nights ?? 1,
      deposit_amount: fromMinor(policy.deposit_amount_minor, this.currency()),
      deposit_due: policy.deposit_due,
      due_days_before_arrival: policy.due_days_before_arrival ?? 7,
      requires_card_guarantee: policy.requires_card_guarantee,
      balance_due: policy.balance_due,
      guest_text: policy.guest_text ?? '',
      is_active: policy.is_active,
    });
  }

  protected resetCancellation(): void {
    this.editingCancellation.set(null);
    this.cancellationError.set(null);
    this.cancellationForm.reset();
  }

  protected resetDeposit(): void {
    this.editingDeposit.set(null);
    this.depositError.set(null);
    this.depositForm.reset();
  }

  protected submitCancellation(): void {
    if (this.cancellationForm.invalid) {
      this.cancellationForm.markAllAsTouched();
      return;
    }

    const v = this.cancellationForm.getRawValue();
    const currency = this.currency();
    const input: CancellationPolicyInput = {
      name: v.name.trim(),
      free_cancellation_hours: v.non_refundable ? null : v.free_cancellation_hours ?? 0,
      penalty_type: v.penalty_type,
      penalty_nights: v.penalty_type === 'nights' ? v.penalty_nights : null,
      penalty_percent: v.penalty_type === 'percent' ? `${v.penalty_percent}` : null,
      penalty_amount_minor: v.penalty_type === 'fixed' ? toMinor(v.penalty_amount, currency) : null,
      no_show_type: v.no_show_type,
      no_show_nights: v.no_show_type === 'nights' ? v.no_show_nights : null,
      no_show_percent: v.no_show_type === 'percent' ? `${v.no_show_percent}` : null,
      no_show_amount_minor: v.no_show_type === 'fixed' ? toMinor(v.no_show_amount, currency) : null,
      guest_text: v.guest_text.trim() || null,
      is_active: v.is_active,
    };

    this.saving.set('cancellation');
    this.cancellationError.set(null);
    this.api.saveCancellation(input, this.editingCancellation()?.id).subscribe({
      next: (policy) => {
        this.saving.set(null);
        this.notice.set(`Cancellation policy “${policy.name}” saved.`);
        this.resetCancellation();
        this.load();
      },
      error: (error: unknown) => {
        this.saving.set(null);
        this.cancellationError.set(applyProblemToForm(this.cancellationForm, mapAmountFields(toProblem(error))));
      },
    });
  }

  protected submitDeposit(): void {
    if (this.depositForm.invalid) {
      this.depositForm.markAllAsTouched();
      return;
    }

    const v = this.depositForm.getRawValue();
    const input: DepositPolicyInput = {
      name: v.name.trim(),
      deposit_type: v.deposit_type,
      deposit_percent: v.deposit_type === 'percent' ? `${v.deposit_percent}` : null,
      deposit_nights: v.deposit_type === 'nights' ? v.deposit_nights : null,
      deposit_amount_minor: v.deposit_type === 'fixed' ? toMinor(v.deposit_amount, this.currency()) : null,
      deposit_due: v.deposit_due,
      due_days_before_arrival: v.deposit_type !== 'none' && v.deposit_due === 'before_arrival' ? v.due_days_before_arrival : null,
      requires_card_guarantee: v.requires_card_guarantee,
      balance_due: v.balance_due,
      guest_text: v.guest_text.trim() || null,
      is_active: v.is_active,
    };

    this.saving.set('deposit');
    this.depositError.set(null);
    this.api.saveDeposit(input, this.editingDeposit()?.id).subscribe({
      next: (policy) => {
        this.saving.set(null);
        this.notice.set(`Deposit policy “${policy.name}” saved.`);
        this.resetDeposit();
        this.load();
      },
      error: (error: unknown) => {
        this.saving.set(null);
        this.depositError.set(applyProblemToForm(this.depositForm, mapAmountFields(toProblem(error))));
      },
    });
  }

  private load(): void {
    this.api.policies().subscribe({
      next: ({ policies, meta }) => {
        this.cancellation.set(policies.cancellation);
        this.deposit.set(policies.deposit);
        this.penaltyTypes.set(meta.penalty_types);
        this.depositTypes.set(meta.deposit_types);
        this.depositDues.set(meta.deposit_dues);
        this.balanceDues.set(meta.balance_dues);
        this.currency.set(meta.currency);
        this.step.set(moneyStep(meta.currency));
        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.error.set(toProblem(error).detail ?? 'Policies could not be loaded.');
        this.loading.set(false);
      },
    });
  }
}
