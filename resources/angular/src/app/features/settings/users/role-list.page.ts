import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { RouterLink } from '@angular/router';

import { toProblem } from '../../../core/bff/problem';
import { RoleDetail } from '../../../core/bff/types';
import { PageTitle } from '../../../shared/ui/page-title';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { StaffApi } from './staff.api';

@Component({
  selector: 'pms-role-list-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink, PageTitle, StatusBadge],
  template: `
    <pms-page-title title="Roles & Permissions" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Roles' }]" />

    <section class="pms-card">
      <div class="flex flex-wrap items-center justify-between gap-3 pt-5 pb-4">
        <h2 class="font-display text-lg font-medium text-heading">Roles <span class="text-sm font-normal text-muted">| {{ roles().length }}</span></h2>
        <a routerLink="/settings/roles/new" class="pms-btn-primary">New custom role</a>
      </div>
      <p class="mb-4 text-sm text-muted">
        Built-in roles follow the standard access for each job and can't be edited. Duplicate one to create a custom role with different permissions.
      </p>

      @if (error(); as message) {
        <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
      }

      <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
          <thead class="border-b-2 border-line text-heading">
            <tr>
              <th scope="col" class="px-3 py-2 font-semibold">Role</th>
              <th scope="col" class="px-3 py-2 font-semibold">Type</th>
              <th scope="col" class="px-3 py-2 font-semibold">Permissions</th>
              <th scope="col" class="px-3 py-2 font-semibold">Staff</th>
              <th scope="col" class="px-3 py-2 font-semibold">2FA</th>
              <th scope="col" class="px-3 py-2"><span class="sr-only">Actions</span></th>
            </tr>
          </thead>
          <tbody>
            @for (role of roles(); track role.name) {
              <tr class="border-b border-line last:border-0 hover:bg-primary-soft">
                <td class="px-3 py-3">
                  <span class="font-semibold text-heading">{{ role.label }}</span>
                  @if (role.description) {
                    <span class="block text-xs text-muted">{{ role.description }}</span>
                  }
                </td>
                <td class="px-3 py-3">{{ role.is_system ? 'Built-in' : 'Custom' }}</td>
                <td class="px-3 py-3">{{ role.is_super_admin ? 'Everything' : role.permissions.length }}</td>
                <td class="px-3 py-3">{{ role.users_count }}</td>
                <td class="px-3 py-3">
                  <pms-status-badge [value]="role.requires_two_factor ? 'active' : 'inactive'" [label]="role.requires_two_factor ? 'Required' : 'Optional'" />
                </td>
                <td class="px-3 py-3 text-right">
                  <a [routerLink]="['/settings/roles', role.name]" class="font-semibold hover:underline">{{ role.is_system ? 'View' : 'Edit' }}</a>
                </td>
              </tr>
            } @empty {
              <tr><td colspan="6" class="px-3 py-8 text-center text-muted">{{ loading() ? 'Loading…' : 'No roles.' }}</td></tr>
            }
          </tbody>
        </table>
      </div>
    </section>
  `,
})
export class RoleListPage {
  private readonly api = inject(StaffApi);

  protected readonly roles = signal<RoleDetail[]>([]);
  protected readonly loading = signal(true);
  protected readonly error = signal<string | null>(null);

  constructor() {
    this.api.roles().subscribe({
      next: ({ roles }) => {
        this.roles.set(roles);
        this.loading.set(false);
      },
      error: (error: unknown) => {
        const problem = toProblem(error);
        this.error.set(problem.detail ?? problem.title);
        this.loading.set(false);
      },
    });
  }
}
