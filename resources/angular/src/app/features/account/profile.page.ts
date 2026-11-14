import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';

import { SessionStore } from '../../core/auth/session.store';
import { initials } from '../../shared/initials';
import { Icon } from '../../shared/ui/icon';
import { PageTitle } from '../../shared/ui/page-title';
import { ChangePassword } from './change-password';
import { TwoFactorSettings } from './two-factor-settings';

@Component({
  selector: 'pms-profile-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, PageTitle, Icon, ChangePassword, TwoFactorSettings],
  template: `
    <pms-page-title title="Profile" [breadcrumbs]="[{ label: 'Account' }, { label: 'Profile' }]" />

    <div class="grid gap-x-[30px] xl:grid-cols-[1fr_2fr]">
      <div>
        <section class="pms-card">
          <div class="flex flex-col items-center pt-6 pb-2 text-center">
            <span class="flex size-[120px] items-center justify-center rounded-full bg-primary-soft font-heading text-4xl font-bold text-primary">
              {{ initials() }}
            </span>
            <h2 class="mt-3 font-heading text-2xl font-bold text-[#2c384e]">{{ session.user()?.name }}</h2>
            @if (session.primaryRole(); as role) {
              <p class="mt-1 text-lg text-[#444]">{{ role.label }}</p>
            }
            <dl class="mt-3 space-y-1.5 text-sm text-muted">
              @if (session.organization(); as organization) {
                <div class="flex items-center justify-center gap-1.5">
                  <dt class="sr-only">Organisation</dt>
                  <pms-icon name="building" size="size-4" />
                  <dd>{{ organization.name }}</dd>
                </div>
              }
              @if (session.user()?.last_login_at; as lastLogin) {
                <div class="flex items-center justify-center gap-1.5">
                  <dt class="sr-only">Last sign-in</dt>
                  <pms-icon name="clock" size="size-4" />
                  <dd>Last sign-in {{ lastLogin | date: 'medium' }}</dd>
                </div>
              }
            </dl>
          </div>
        </section>
      </div>

      <div>
        <pms-change-password />
        <pms-two-factor-settings />
      </div>
    </div>
  `,
})
export class ProfilePage {
  protected readonly session = inject(SessionStore);
  protected readonly initials = computed(() => initials(this.session.user()?.name));
}
