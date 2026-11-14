import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';

import { AuthApi } from '../../core/auth/auth.api';
import { safeReturnUrl } from '../../core/auth/guards';
import { SessionStore } from '../../core/auth/session.store';
import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { FieldError } from '../../shared/ui/field-error';

@Component({
  selector: 'pms-two-factor-challenge-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, FieldError],
  template: `
    <div class="pt-6 pb-4">
      <h1 class="text-center font-display text-2xl font-medium text-navy">Two-Factor Authentication</h1>
      <p class="mt-1 text-center text-sm">
        @if (useRecoveryCode()) {
          Enter one of your emergency recovery codes.
        } @else {
          Enter the 6-digit code from your authenticator app.
        }
      </p>
    </div>

    @if (error(); as message) {
      <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-4" novalidate>
      @if (useRecoveryCode()) {
        <div>
          <label for="recovery_code" class="pms-label">Recovery code</label>
          <input id="recovery_code" type="text" formControlName="recovery_code" autocomplete="one-time-code" class="pms-input" />
          <pms-field-error [control]="form.controls.recovery_code" />
        </div>
      } @else {
        <div>
          <label for="code" class="pms-label">Authentication code</label>
          <input id="code" type="text" inputmode="numeric" maxlength="6" formControlName="code" autocomplete="one-time-code"
                 class="pms-input tracking-[0.4em]" />
          <pms-field-error [control]="form.controls.code" />
        </div>
      }

      <button type="submit" class="pms-btn-primary w-full" [disabled]="submitting()">
        {{ submitting() ? 'Verifying…' : 'Verify' }}
      </button>

      <div class="flex items-center justify-between text-sm">
        <button type="button" class="cursor-pointer text-primary hover:underline" (click)="toggleMode()">
          {{ useRecoveryCode() ? 'Use an authentication code' : 'Use a recovery code' }}
        </button>
        <a routerLink="/sign-in" class="hover:underline">Back to login</a>
      </div>
    </form>
  `,
})
export class TwoFactorChallengePage {
  readonly returnUrl = input<string>();

  private readonly fb = inject(NonNullableFormBuilder);
  private readonly authApi = inject(AuthApi);
  private readonly session = inject(SessionStore);
  private readonly router = inject(Router);

  protected readonly useRecoveryCode = signal(false);
  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = this.fb.group({ code: [''], recovery_code: [''] });

  protected toggleMode(): void {
    this.useRecoveryCode.update((value) => !value);
    this.form.reset();
    this.error.set(null);
  }

  protected submit(): void {
    const { code, recovery_code } = this.form.getRawValue();
    const payload = this.useRecoveryCode() ? { recovery_code: recovery_code.trim() } : { code: code.trim() };

    if (!Object.values(payload)[0]) {
      const control = this.useRecoveryCode() ? this.form.controls.recovery_code : this.form.controls.code;
      control.setErrors({ required: true });
      control.markAsTouched();
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    this.authApi.twoFactorChallenge(payload).subscribe({
      next: async () => {
        await this.session.load();
        await this.router.navigateByUrl(safeReturnUrl(this.returnUrl()));
      },
      error: (error: unknown) => {
        this.error.set(applyProblemToForm(this.form, toProblem(error)));
        this.submitting.set(false);
      },
    });
  }
}
