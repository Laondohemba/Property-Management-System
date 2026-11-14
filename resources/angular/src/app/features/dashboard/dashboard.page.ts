import { ChangeDetectionStrategy, Component, inject } from '@angular/core';

import { SessionStore } from '../../core/auth/session.store';
import { PageTitle } from '../../shared/ui/page-title';

/**
 * Placeholder until GET /bff/dashboard (KPI tiles, arrivals/departures, occupancy, alerts) is built in Phase 1.
 */
@Component({
  selector: 'pms-dashboard-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageTitle],
  template: `
    <pms-page-title title="Dashboard" [breadcrumbs]="[{ label: 'Dashboard' }]" />

    <section class="pms-card">
      <h2 class="pms-card-title">
        Welcome back, {{ session.user()?.name }}
        @if (session.organization(); as organization) {
          <span class="text-sm font-normal text-muted">| {{ organization.name }}</span>
        }
      </h2>
      <p class="text-sm">
        You are signed in. Property setup, reservations and the other modules will appear here as they are delivered.
      </p>
    </section>
  `,
})
export class DashboardPage {
  protected readonly session = inject(SessionStore);
}
