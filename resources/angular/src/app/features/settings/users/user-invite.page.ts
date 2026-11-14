import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { SessionStore } from '../../../core/auth/session.store';
import { applyProblemToForm, toProblem } from '../../../core/bff/problem';
import { StaffOptions } from '../../../core/bff/types';
import { FieldError } from '../../../shared/ui/field-error';
import { PageTitle } from '../../../shared/ui/page-title';
import { StaffApi } from './staff.api';

@Component({
  selector: 'pms-user-invite-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, PageTitle, FieldError],
  template: `
    <pms-page-title title="Invite Staff" [breadcrumbs]="[{ label: 'Settings' }, { label: 'Users', link: '/settings/users' }, { label: 'Invite' }]" />

    @if (error(); as message) {
      <div class="pms-alert-danger mb-5" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="max-w-3xl" novalidate>
      <section class="pms-card">
        <h2 class="pms-card-title">Who and where</h2>
        <p class="text-sm text-muted">
          New people receive an e-mail to set their password. If the address already belongs to a colleague, they simply gain access to the property.
        </p>

        <div class="mt-4 grid gap-4 sm:grid-cols-2">
          <div>
            <label for="name" class="pms-label">Full name</label>
            <input id="name" type="text" formControlName="name" autocomplete="off" class="pms-input" />
            <pms-field-error [control]="form.controls.name" />
          </div>
          <div>
            <label for="email" class="pms-label">Email</label>
            <input id="email" type="email" formControlName="email" autocomplete="off" class="pms-input" />
            <pms-field-error [control]="form.controls.email" />
          </div>
          <div>
            <label for="property_id" class="pms-label">Property</label>
            <select id="property_id" formControlName="property_id" class="pms-input">
              @for (property of options()?.properties ?? []; track property.id) {
                <option [value]="property.id">{{ property.name }} ({{ property.code }})</option>
              }
            </select>
            <pms-field-error [control]="form.controls.property_id" />
          </div>
          <div>
            <label for="role" class="pms-label">Role at this property</label>
            <select id="role" formControlName="role" class="pms-input">
              @for (role of options()?.roles ?? []; track role.name) {
                <option [value]="role.name">{{ role.label }}{{ role.is_system ? '' : ' (custom)' }}</option>
              }
            </select>
            <pms-field-error [control]="form.controls.role" />
          </div>
        </div>
      </section>

      <div class="flex gap-3">
        <button type="submit" class="pms-btn-primary" [disabled]="saving() || !options()">{{ saving() ? 'Sending…' : 'Send Invitation' }}</button>
        <a routerLink="/settings/users" class="pms-btn-secondary">Cancel</a>
      </div>
    </form>
  `,
})
export class UserInvitePage {
  private readonly api = inject(StaffApi);
  private readonly router = inject(Router);
  private readonly session = inject(SessionStore);

  protected readonly options = signal<StaffOptions | null>(null);
  protected readonly saving = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email]],
    property_id: ['', [Validators.required]],
    role: ['', [Validators.required]],
  });

  constructor() {
    this.api.list({ page: 1 }).subscribe({
      next: ({ meta }) => {
        this.options.set(meta);
        const active = this.session.activeProperty()?.id;
        const property = meta.properties.find((p) => p.id === active) ?? meta.properties[0];
        this.form.patchValue({
          property_id: property?.id ?? '',
          role: meta.roles.find((role) => role.name === 'front-desk-agent')?.name ?? meta.roles[0]?.name ?? '',
        });
      },
      error: (error: unknown) => this.error.set(toProblem(error).detail ?? 'Could not load the form.'),
    });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.saving.set(true);
    this.error.set(null);

    this.api.invite(this.form.getRawValue()).subscribe({
      next: ({ user, invited }) =>
        void this.router.navigate(['/settings/users', user.id], { queryParams: { notice: invited ? 'invited' : 'added' } }),
      error: (error: unknown) => {
        this.saving.set(false);
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
      },
    });
  }
}
