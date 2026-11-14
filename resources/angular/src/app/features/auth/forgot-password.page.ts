import { ChangeDetectionStrategy, Component, inject, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';

import { AuthApi } from '../../core/auth/auth.api';
import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { FieldError } from '../../shared/ui/field-error';

@Component({
  selector: 'pms-forgot-password-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, FieldError],
  template: `
    <div class="pt-6 pb-4">
      <h1 class="text-center font-display text-2xl font-medium text-heading">Reset Password</h1>
      <p class="mt-1 text-center text-sm">Enter your email and we will send you a reset link</p>
    </div>

    @if (sent(); as message) {
      <div class="pms-alert-success mb-4" role="status">{{ message }}</div>
    }
    @if (error(); as message) {
      <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-4" novalidate>
      <div>
        <label for="email" class="pms-label">Email</label>
        <input id="email" type="email" formControlName="email" autocomplete="username" class="pms-input" />
        <pms-field-error [control]="form.controls.email" />
      </div>

      <button type="submit" class="pms-btn-primary w-full" [disabled]="submitting()">
        {{ submitting() ? 'Sending…' : 'Email Password Reset Link' }}
      </button>

      <p class="text-sm"><a routerLink="/sign-in" class="hover:underline">Back to login</a></p>
    </form>
  `,
})
export class ForgotPasswordPage {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authApi = inject(AuthApi);

  protected readonly submitting = signal(false);
  protected readonly sent = signal<string | null>(null);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({ email: ['', [Validators.required, Validators.email]] });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.sent.set(null);
    this.error.set(null);

    this.authApi.forgotPassword(this.form.getRawValue().email).subscribe({
      next: (message) => {
        this.sent.set(message);
        this.submitting.set(false);
      },
      error: (error: unknown) => {
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
        this.submitting.set(false);
      },
    });
  }
}
