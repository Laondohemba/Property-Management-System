import { BreakpointObserver } from '@angular/cdk/layout';
import { CdkMenu, CdkMenuGroup, CdkMenuItem, CdkMenuItemRadio, CdkMenuTrigger } from '@angular/cdk/menu';
import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { NavigationEnd, Router, RouterLink, RouterLinkActive, RouterOutlet } from '@angular/router';
import { filter, map } from 'rxjs';

import { AuthApi } from '../core/auth/auth.api';
import { SessionStore } from '../core/auth/session.store';
import { AppVersionService } from '../core/http/app-version.service';
import { ThemePreference, ThemeService } from '../core/theme/theme.service';
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
  imports: [RouterOutlet, RouterLink, RouterLinkActive, CdkMenu, CdkMenuGroup, CdkMenuItem, CdkMenuItemRadio, CdkMenuTrigger, Icon, Logo],
  templateUrl: './shell.html',
})
export class Shell {
  protected readonly session = inject(SessionStore);
  protected readonly appVersion = inject(AppVersionService);
  protected readonly theme = inject(ThemeService);
  protected readonly themeOptions: { value: ThemePreference; label: string }[] = [
    { value: 'system', label: 'System' },
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
  ];
  private readonly authApi = inject(AuthApi);
  private readonly router = inject(Router);

  /** Navigation filtered by what the user may do at the active property (server still enforces). */
  protected readonly nav = computed<NavSection[]>(() => {
    const settings: NavItem[] = [];

    if (this.session.isSuperAdmin() || this.session.permissions().has('settings.property')) {
      settings.push({ label: 'Properties', icon: 'building', link: '/settings/properties' });
    }

    if (this.session.permissions().has('settings.rooms')) {
      settings.push(
        { label: 'Room Types', icon: 'grid', link: '/settings/room-types' },
        { label: 'Rooms', icon: 'key', link: '/settings/rooms' },
        { label: 'Amenities', icon: 'shield', link: '/settings/amenities' },
      );
    }

    if (this.session.permissions().has('settings.taxes')) {
      settings.push({ label: 'Taxes & Fees', icon: 'receipt', link: '/settings/taxes' });
    }

    if (this.session.permissions().has('settings.policies')) {
      settings.push({ label: 'Policies', icon: 'document', link: '/settings/policies' });
    }

    if (this.session.canManageStaff()) {
      settings.push({ label: 'Users', icon: 'user', link: '/settings/users' });
    }

    if (this.session.isSuperAdmin()) {
      settings.push({ label: 'Roles & Permissions', icon: 'key', link: '/settings/roles' });
    }

    const main: NavItem[] = [{ label: 'Dashboard', icon: 'grid', link: '/dashboard' }];

    if (this.session.isSuperAdmin() || this.session.permissions().has('audit.view')) {
      main.push({ label: 'Audit Log', icon: 'clock', link: '/audit' });
    }

    const revenue: NavItem[] = [];

    if (this.session.permissions().has('rates.manage')) {
      revenue.push({ label: 'Rate Plans', icon: 'tag', link: '/rate-plans' });
    }

    if (this.session.permissions().has('inventory.manage')) {
      revenue.push({ label: 'Inventory', icon: 'calendar', link: '/inventory' });
    }

    return [
      { items: main },
      ...(revenue.length ? [{ heading: 'Revenue', items: revenue }] : []),
      ...(settings.length ? [{ heading: 'Settings', items: settings }] : []),
      { heading: 'Account', items: [{ label: 'Profile', icon: 'user', link: '/account/profile' }] },
    ];
  });

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

  protected async switchProperty(id: string): Promise<void> {
    if (id === this.session.activeProperty()?.id) {
      return;
    }

    await this.session.selectProperty(id);
    await this.router.navigateByUrl('/dashboard');
  }

  private leave(): void {
    this.session.clear();
    void this.router.navigate(['/sign-in']);
  }
}
