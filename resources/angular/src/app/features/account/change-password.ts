import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';

import { AuthApi } from '../../core/auth/auth.api';
import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { FieldError } from '../../shared/ui/field-error';

@Component({
  selector: 'pms-change-password',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, FieldError],
  template: `
    <section class="pms-card">
      <h2 class="pms-card-title">Change Password</h2>
      <p class="text-sm text-muted">Use a long, random password: at least 12 characters with mixed case, a number and a symbol.</p>

      @if (saved()) {
        <div class="pms-alert-success mt-4" role="status">Your password has been changed.</div>
      }
      @if (error(); as message) {
        <div class="pms-alert-danger mt-4" role="alert">{{ message }}</div>
      }

      <form [formGroup]="form" (ngSubmit)="submit()" class="mt-5 max-w-xl space-y-4" novalidate>
        <div>
          <label for="current_password" class="pms-label">Current password</label>
          <input id="current_password" type="password" formControlName="current_password" autocomplete="current-password"
                 class="pms-input" />
          <pms-field-error [control]="form.controls.current_password" />
        </div>

        <div>
          <label for="new_password" class="pms-label">New password</label>
          <input id="new_password" type="password" formControlName="password" autocomplete="new-password" class="pms-input" />
          <pms-field-error [control]="form.controls.password" />
        </div>

        <div>
          <label for="new_password_confirmation" class="pms-label">Confirm new password</label>
          <input id="new_password_confirmation" type="password" formControlName="password_confirmation"
                 autocomplete="new-password" class="pms-input" />
          <pms-field-error [control]="form.controls.password_confirmation" />
        </div>

        <button type="submit" class="pms-btn-primary" [disabled]="submitting()">
          {{ submitting() ? 'Saving…' : 'Change Password' }}
        </button>
      </form>
    </section>
  `,
})
export class ChangePassword {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authApi = inject(AuthApi);

  protected readonly submitting = signal(false);
  protected readonly saved = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    current_password: ['', [Validators.required]],
    password: ['', [Validators.required, Validators.minLength(12)]],
    password_confirmation: ['', [Validators.required]],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.saved.set(false);
    this.error.set(null);

    this.authApi.updatePassword(this.form.getRawValue()).subscribe({
      next: () => {
        this.form.reset();
        this.saved.set(true);
        this.submitting.set(false);
      },
      error: (error: unknown) => {
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
        this.submitting.set(false);
      },
    });
  }
}
