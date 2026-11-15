import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { toProblem } from '../../core/bff/problem';
import { RatePlan } from '../../core/bff/types';
import { PageTitle } from '../../shared/ui/page-title';
import { RecordStampsLine } from '../../shared/ui/record-stamps';
import { StatusBadge } from '../../shared/ui/status-badge';
import { formatMoney } from '../../shared/util/money';
import { RatesApi } from './rates.api';

/** Rate plans of the active property (RTE-01). */
@Component({
  selector: 'pms-rate-plan-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, PageTitle, RecordStampsLine, StatusBadge],
  template: `
    <pms-page-title title="Rate Plans" [breadcrumbs]="[{ label: 'Revenue' }, { label: 'Rate Plans' }]" />

    @if (error(); as message) {
      <div class="pms-alert-danger mb-5" role="alert">{{ message }}</div>
    }

    <section class="pms-card">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h2 class="pms-card-title mb-0!">Rate plans <span class="text-sm font-normal text-muted">| {{ plans().length }}</span></h2>
        <a routerLink="/rate-plans/new" class="pms-btn-primary">Add rate plan</a>
      </div>

      @if (plans().length) {
        <div class="overflow-x-auto">
          <table class="w-full text-left text-sm">
            <thead class="border-b-2 border-line text-heading">
              <tr>
                <th scope="col" class="px-3 py-2 font-semibold">Name</th>
                <th scope="col" class="px-3 py-2 font-semibold">Meals</th>
                <th scope="col" class="px-3 py-2 font-semibold">Policies</th>
                <th scope="col" class="px-3 py-2 font-semibold">From</th>
                <th scope="col" class="px-3 py-2 font-semibold">Taxes</th>
                <th scope="col" class="px-3 py-2 font-semibold">Last updated</th>
                <th scope="col" class="px-3 py-2 font-semibold">Status</th>
                <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
              </tr>
            </thead>
            <tbody>
              @for (plan of plans(); track plan.id) {
                <tr class="border-b border-line last:border-0 hover:bg-primary-soft">
                  <td class="px-3 py-3">
                    <span class="font-semibold text-heading">{{ plan.name }}</span>
                    <code class="ml-1 rounded bg-primary-soft px-1.5 py-0.5 text-xs text-heading">{{ plan.code }}</code>
                    <span class="block text-xs text-muted">{{ plan.prices.length }} {{ plan.prices.length === 1 ? 'room type' : 'room types' }}</span>
                  </td>
                  <td class="px-3 py-3">{{ plan.meal_plan_label }}</td>
                  <td class="px-3 py-3">
                    {{ plan.cancellation_policy?.name ?? 'No cancellation policy' }}
                    <span class="block text-xs text-muted">{{ plan.deposit_policy?.name ?? 'No deposit policy' }}</span>
                  </td>
                  <td class="px-3 py-3 whitespace-nowrap">{{ lowest(plan) }}</td>
                  <td class="px-3 py-3">{{ taxCodes(plan) }}</td>
                  <td class="px-3 py-3"><pms-record-stamps [stamps]="plan.stamps" [compact]="true" /></td>
                  <td class="px-3 py-3"><pms-status-badge [value]="plan.is_active ? 'active' : 'inactive'" [label]="plan.is_active ? 'Active' : 'Inactive'" /></td>
                  <td class="px-3 py-3 text-right"><a [routerLink]="['/rate-plans', plan.id]" class="font-semibold hover:underline">Edit</a></td>
                </tr>
              }
            </tbody>
          </table>
        </div>
      } @else if (!loading()) {
        <p class="text-sm text-muted">No rate plans yet. Set up taxes and policies under Settings first, then add a plan such as “Best Available Rate”.</p>
      }
    </section>
  `,
})
export class RatePlanListPage {
  private readonly api = inject(RatesApi);

  protected readonly plans = signal<RatePlan[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    this.api.ratePlans().subscribe({
      next: ({ items }) => {
        this.plans.set(items);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        this.error.set(toProblem(error).detail ?? 'Rate plans could not be loaded.');
        this.loading.set(false);
      },
    });
  }

  protected lowest(plan: RatePlan): string {
    if (!plan.prices.length) {
      return '—';
    }
    return formatMoney(Math.min(...plan.prices.map((price) => price.base_price_minor)), plan.currency);
  }

  protected taxCodes(plan: RatePlan): string {
    return plan.taxes.length ? plan.taxes.map((tax) => tax.code).join(', ') : 'None';
  }
}
