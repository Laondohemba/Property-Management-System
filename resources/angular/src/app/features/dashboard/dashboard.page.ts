import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, computed, effect, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { SessionStore } from '../../core/auth/session.store';
import { toProblem } from '../../core/bff/problem';
import { BffEnvelope, DashboardData } from '../../core/bff/types';
import { Icon } from '../../shared/ui/icon';
import { PageTitle } from '../../shared/ui/page-title';

/**
 * Dashboard for the active property, from GET /bff/dashboard (spec §8.2).
 * KPI tiles, arrivals/departures, occupancy trend and alerts arrive in Phase 1.
 */
@Component({
  selector: 'pms-dashboard-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [PageTitle, RouterLink, Icon],
  templateUrl: './dashboard.page.html',
})
export class DashboardPage {
  protected readonly session = inject(SessionStore);
  private readonly http = inject(HttpClient);

  protected readonly data = signal<DashboardData | null>(null);
  protected readonly error = signal<string | null>(null);
  protected readonly canView = computed(() => this.session.permissions().has('dashboard.view'));

  constructor() {
    // Reload whenever the active property changes.
    effect(() => {
      const propertyId = this.session.activeProperty()?.id;

      this.data.set(null);
      this.error.set(null);

      if (propertyId && this.canView()) {
        this.http.get<BffEnvelope<DashboardData>>('/bff/dashboard').subscribe({
          next: (response) => this.data.set(response.data),
          error: (error: unknown) => {
            const problem = toProblem(error);
            this.error.set(problem.detail ?? problem.title);
          },
        });
      }
    });
  }
}
