import { DatePipe } from '@angular/common';
import { ChangeDetectionStrategy, Component, OnInit, computed, inject, input, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { Observable } from 'rxjs';

import { toProblem } from '../../../core/bff/problem';
import { StaffOptions, StaffUser } from '../../../core/bff/types';
import { PageTitle } from '../../../shared/ui/page-title';
import { StatusBadge } from '../../../shared/ui/status-badge';
import { StaffApi, StaffResult } from './staff.api';

/** One staff member: account status (Super Admins) and access per property. */
@Component({
  selector: 'pms-user-detail-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [DatePipe, FormsModule, RouterLink, PageTitle, StatusBadge],
  templateUrl: './user-detail.page.html',
})
export class UserDetailPage implements OnInit {
  readonly id = input.required<string>();
  /** invited | added, after the invite screen. */
  readonly notice = input<string>();

  private readonly api = inject(StaffApi);

  protected readonly user = signal<StaffUser | null>(null);
  protected readonly options = signal<StaffOptions | null>(null);
  protected readonly busy = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly message = signal<string | null>(null);

  protected newPropertyId = '';
  protected newRole = '';

  /** Properties the actor manages that the user is not assigned to yet. */
  protected readonly unassigned = computed(() => {
    const assigned = new Set(this.user()?.assignments.map((a) => a.property.id));
    return (this.options()?.properties ?? []).filter((property) => !assigned.has(property.id));
  });

  ngOnInit(): void {
    if (this.notice() === 'invited') this.message.set('Invitation sent.');
    if (this.notice() === 'added') this.message.set('Access added. This person already had an account, so no invitation was needed.');

    this.run(this.api.get(this.id()));
  }

  protected changeRole(propertyId: string, role: string): void {
    this.run(this.api.assign(this.id(), propertyId, role), 'Role updated.');
  }

  protected removeAccess(propertyId: string, propertyName: string): void {
    if (confirm(`Remove ${this.user()?.name}'s access to ${propertyName}?`)) {
      this.run(this.api.unassign(this.id(), propertyId), 'Access removed.');
    }
  }

  protected addAccess(): void {
    if (this.newPropertyId && this.newRole) {
      this.run(this.api.assign(this.id(), this.newPropertyId, this.newRole), 'Access added.');
    }
  }

  protected setActive(active: boolean): void {
    const verb = active ? 'Reactivate' : 'Deactivate';
    if (confirm(`${verb} ${this.user()?.name}? ${active ? '' : 'They will be signed out immediately.'}`)) {
      this.run(this.api.updateAccount(this.id(), { is_active: active }), active ? 'Account reactivated.' : 'Account deactivated.');
    }
  }

  protected setSuperAdmin(superAdmin: boolean): void {
    const text = superAdmin
      ? `Make ${this.user()?.name} a Super Admin? They will manage the whole organisation.`
      : `Remove ${this.user()?.name}'s Super Admin role?`;
    if (confirm(text)) {
      this.run(this.api.updateAccount(this.id(), { is_super_admin: superAdmin }), 'Super Admin role updated.');
    }
  }

  protected resend(): void {
    this.busy.set(true);
    this.error.set(null);
    this.api.resendInvitation(this.id()).subscribe({
      next: () => {
        this.busy.set(false);
        this.message.set('A new invitation e-mail has been sent.');
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  private run(request: Observable<StaffResult>, success?: string): void {
    this.busy.set(true);
    this.error.set(null);
    this.message.set(success ? null : this.message());

    request.subscribe({
      next: ({ user, options }) => {
        this.user.set(user);
        this.options.set(options);
        this.busy.set(false);
        this.newPropertyId = this.unassigned()[0]?.id ?? '';
        this.newRole ||= options.roles.find((r) => r.name === 'front-desk-agent')?.name ?? options.roles[0]?.name ?? '';
        if (success) this.message.set(success);
      },
      error: (error: unknown) => this.fail(error),
    });
  }

  private fail(error: unknown): void {
    const problem = toProblem(error);
    this.busy.set(false);
    this.error.set(problem.errors?.[0]?.message ?? problem.detail ?? problem.title);
  }
}
