import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterOutlet } from '@angular/router';

import { Logo } from '../shared/ui/logo';

/** Centered card used by sign-in and password screens (NiceAdmin pages-login layout). */
@Component({
  selector: 'pms-auth-layout',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, Logo],
  template: `
    <main class="flex min-h-screen flex-col items-center justify-center px-4 py-6">
      <div class="w-full max-w-[420px]">
        <div class="flex justify-center py-6">
          <span class="flex items-center gap-2">
            <pms-logo />
            <span class="font-heading text-[26px] font-bold text-navy">PMS</span>
          </span>
        </div>

        <div class="mb-3 rounded-[5px] bg-white px-5 pb-6 shadow-card">
          <router-outlet />
        </div>

        <p class="text-center text-[13px] text-navy">&copy; {{ year }} PMS</p>
      </div>
    </main>
  `,
})
export class AuthLayout {
  protected readonly year = new Date().getFullYear();
}
