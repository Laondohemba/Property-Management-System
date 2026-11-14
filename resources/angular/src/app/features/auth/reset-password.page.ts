import { ChangeDetectionStrategy, Component, OnInit, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthApi } from '../../core/auth/auth.api';
import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { FieldError } from '../../shared/ui/field-error';

@Component({
  selector: 'pms-reset-password-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, FieldError],
  template: `
    <div class="pt-6 pb-4">
      <h1 class="text-center font-display text-2xl font-medium text-heading">Choose a New Password</h1>
      <p class="mt-1 text-center text-sm">At least 12 characters, with upper and lower case letters, a number and a symbol.</p>
    </div>

    @if (error(); as message) {
      <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-4" novalidate>
      <div>
        <label for="email" class="pms-label">Email</label>
        <input id="email" type="email" formControlName="email" autocomplete="username" class="pms-input" />
        <pms-field-error [control]="form.controls.email" />
      </div>

      <div>
        <label for="password" class="pms-label">New password</label>
        <input id="password" type="password" formControlName="password" autocomplete="new-password" class="pms-input" />
        <pms-field-error [control]="form.controls.password" />
      </div>

      <div>
        <label for="password_confirmation" class="pms-label">Confirm password</label>
        <input id="password_confirmation" type="password" formControlName="password_confirmation" autocomplete="new-password"
               class="pms-input" />
        <pms-field-error [control]="form.controls.password_confirmation" />
      </div>

      <button type="submit" class="pms-btn-primary w-full" [disabled]="submitting()">
        {{ submitting() ? 'Saving…' : 'Reset Password' }}
      </button>

      <p class="text-sm"><a routerLink="/sign-in" class="hover:underline">Back to login</a></p>
    </form>
  `,
})
export class ResetPasswordPage implements OnInit {
  /** Route parameter and query string, bound by the router. */
  readonly token = input.required<string>();
  readonly email = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authApi = inject(AuthApi);
  private readonly router = inject(Router);

  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({
    email: ['', [Validators.required, Validators.email]],
    password: ['', [Validators.required, Validators.minLength(12)]],
    password_confirmation: ['', [Validators.required]],
  });

  ngOnInit(): void {
    this.form.patchValue({ email: this.email() ?? '' });
  }

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.authApi.resetPassword({ token: this.token(), ...this.form.getRawValue() }).subscribe({
      next: () => void this.router.navigate(['/sign-in'], { queryParams: { status: 'password-reset' } }),
      error: (error: unknown) => {
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
        this.submitting.set(false);
      },
    });
  }
}
