import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthApi } from '../../core/auth/auth.api';
import { safeReturnUrl } from '../../core/auth/guards';
import { SessionStore } from '../../core/auth/session.store';
import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { FieldError } from '../../shared/ui/field-error';

@Component({
  selector: 'pms-sign-in-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, FieldError],
  template: `
    <div class="pt-6 pb-4">
      <h1 class="text-center font-display text-2xl font-medium text-navy">Login to Your Account</h1>
      <p class="mt-1 text-center text-sm">Enter your email &amp; password to login</p>
    </div>

    @if (status() === 'password-reset') {
      <div class="pms-alert-success mb-4" role="status">Your password has been reset. You can now sign in.</div>
    }
    @if (status() === 'invitation-accepted') {
      <div class="pms-alert-success mb-4" role="status">Your account is ready. Sign in with your new password.</div>
    }
    @if (error(); as message) {
      <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-4" novalidate>
      <div>
        <label for="email" class="pms-label">Email</label>
        <input id="email" type="email" formControlName="email" autocomplete="username" class="pms-input"
               [class.is-invalid]="form.controls.email.invalid && form.controls.email.touched" />
        <pms-field-error [control]="form.controls.email" />
      </div>

      <div>
        <div class="mb-2 flex items-center justify-between">
          <label for="password" class="text-sm text-body">Password</label>
          <a routerLink="/password/forgot" class="text-sm hover:underline">Forgot password?</a>
        </div>
        <input id="password" type="password" formControlName="password" autocomplete="current-password" class="pms-input"
               [class.is-invalid]="form.controls.password.invalid && form.controls.password.touched" />
        <pms-field-error [control]="form.controls.password" />
      </div>

      <label for="remember" class="flex items-center gap-2">
        <input id="remember" type="checkbox" formControlName="remember" class="pms-checkbox" />
        <span class="text-sm">Remember me</span>
      </label>

      <button type="submit" class="pms-btn-primary w-full" [disabled]="submitting()">
        {{ submitting() ? 'Signing in…' : 'Login' }}
      </button>
    </form>
  `,
})
export class SignInPage {
  /** Bound from the query string. */
  readonly returnUrl = input<string>();
  readonly status = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authApi = inject(AuthApi);
  private readonly session = inject(SessionStore);
  private readonly router = inject(Router);

  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required]],
    remember: [false],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.authApi.login(this.form.getRawValue()).subscribe({
      next: async ({ twoFactor }) => {
        if (twoFactor) {
          await this.router.navigate(['/sign-in/two-factor'], { queryParams: { returnUrl: this.returnUrl() } });
          return;
        }

        await this.session.load();
        await this.router.navigateByUrl(safeReturnUrl(this.returnUrl()));
      },
      error: (error: unknown) => {
        this.form.controls.password.reset();
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
        this.submitting.set(false);
      },
    });
  }
}
