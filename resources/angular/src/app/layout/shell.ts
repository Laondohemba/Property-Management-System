import { BreakpointObserver } from '@angular/cdk/layout';
import { CdkMenu, CdkMenuItem, CdkMenuTrigger } from '@angular/cdk/menu';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';

import { AuthApi } from '../core/auth/auth.api';
import { SessionStore } from '../core/auth/session.store';
import { AppVersionService } from '../core/http/app-version.service';
import { initials } from '../shared/initials';
import { Icon, IconName } from '../shared/ui/icon';
import { Logo } from '../shared/ui/logo';

interface NavItem {
  label: string;
  icon: IconName;
  link: string;
}

interface NavSection {
  heading?: string;
  items: NavItem[];
}

/** Authenticated application shell: fixed header, collapsible sidebar (NiceAdmin layout). */
@Component({
  selector: 'pms-shell',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterOutlet, RouterLink, RouterLinkActive, CdkMenu, CdkMenuItem, CdkMenuTrigger, Icon, Logo],
  templateUrl: './shell.html',
})
export class Shell {
  protected readonly session = inject(SessionStore);
  protected readonly appVersion = inject(AppVersionService);
  private readonly authApi = inject(AuthApi);
  private readonly router = inject(Router);

  protected readonly nav: NavSection[] = [
    { items: [{ label: 'Dashboard', icon: 'grid', link: '/dashboard' }] },
    { heading: 'Account', items: [{ label: 'Profile', icon: 'user', link: '/account/profile' }] },
  ];

  protected readonly desktop = toSignal(
    inject(BreakpointObserver)
      .observe('(min-width: 1280px)')
      .pipe(map((state) => state.matches)),
    { initialValue: true },
  );
  protected readonly collapsed = signal(false);
  protected readonly mobileOpen = signal(false);
  protected readonly sidebarVisible = computed(() => (this.desktop() ? !this.collapsed() : this.mobileOpen()));
  protected readonly contentOffset = computed(() => (this.desktop() && !this.collapsed() ? 'xl:ml-sidebar' : ''));
  protected readonly initials = computed(() => initials(this.session.user()?.name));
  protected readonly year = new Date().getFullYear();

  constructor() {
    this.router.events
      .pipe(
        filter((event) => event instanceof NavigationEnd),
        takeUntilDestroyed(),
      )
      .subscribe(() => this.mobileOpen.set(false));
  }

  protected toggleSidebar(): void {
    if (this.desktop()) {
      this.collapsed.update((value) => !value);
    } else {
      this.mobileOpen.update((value) => !value);
    }
  }

  protected signOut(): void {
    this.authApi.logout().subscribe({
      complete: () => this.leave(),
      error: () => this.leave(),
    });
  }

  protected reload(): void {
    window.location.reload();
  }

  private leave(): void {
    this.session.clear();
    void this.router.navigate(['/sign-in']);
  }
}
