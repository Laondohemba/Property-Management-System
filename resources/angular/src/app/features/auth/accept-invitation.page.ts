import { HttpClient } from '@angular/common/http';
import { ChangeDetectionStrategy, Component, inject, input, signal } from '@angular/core';
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { switchMap } from 'rxjs';

import { applyProblemToForm, toProblem } from '../../core/bff/problem';
import { CsrfService } from '../../core/http/csrf.service';
import { FieldError } from '../../shared/ui/field-error';

/** Invitees choose their password here (SYS-01), from the link in their invitation e-mail. */
@Component({
  selector: 'pms-accept-invitation-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [ReactiveFormsModule, RouterLink, FieldError],
  template: `
    <div class="pt-6 pb-4">
      <h1 class="text-center font-display text-2xl font-medium text-navy">Welcome to PMS</h1>
      <p class="mt-1 text-center text-sm">Choose a password to activate your account</p>
    </div>

    @if (error(); as message) {
      <div class="pms-alert-danger mb-4" role="alert">{{ message }}</div>
    }

    <form [formGroup]="form" (ngSubmit)="submit()" class="space-y-4" novalidate>
      <div>
        <label for="email" class="pms-label">Email</label>
        <input id="email" type="email" [value]="email() ?? ''" readonly autocomplete="username" class="pms-input bg-gray-50" />
      </div>
      <div>
        <label for="password" class="pms-label">Password</label>
        <input id="password" type="password" formControlName="password" autocomplete="new-password" class="pms-input" aria-describedby="password_help" />
        <p id="password_help" class="mt-1 text-xs text-muted">At least 12 characters, with upper and lower case letters, a number and a symbol.</p>
        <pms-field-error [control]="form.controls.password" />
      </div>
      <div>
        <label for="password_confirmation" class="pms-label">Confirm password</label>
        <input id="password_confirmation" type="password" formControlName="password_confirmation" autocomplete="new-password" class="pms-input" />
        <pms-field-error [control]="form.controls.password_confirmation" />
      </div>

      <button type="submit" class="pms-btn-primary w-full" [disabled]="submitting()">
        {{ submitting() ? 'Activating…' : 'Activate Account' }}
      </button>
      <p class="text-sm"><a routerLink="/sign-in" class="hover:underline">Already activated? Sign in</a></p>
    </form>
  `,
})
export class AcceptInvitationPage {
  /** Route parameter and query string. */
  readonly token = input.required<string>();
  readonly email = input<string>();

  private readonly http = inject(HttpClient);
  private readonly csrf = inject(CsrfService);
  private readonly router = inject(Router);

  protected readonly submitting = signal(false);
  protected readonly error = signal<string | null>(null);

  protected readonly form = inject(NonNullableFormBuilder).group({
    password: ['', [Validators.required, Validators.minLength(12)]],
    password_confirmation: ['', [Validators.required]],
  });

  protected submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.submitting.set(true);
    this.error.set(null);

    const payload = { token: this.token(), email: this.email() ?? '', ...this.form.getRawValue() };

    this.csrf
      .refresh()
      .pipe(switchMap(() => this.http.post('/accept-invitation', payload)))
      .subscribe({
        next: () => void this.router.navigate(['/sign-in'], { queryParams: { status: 'invitation-accepted' } }),
        error: (error: unknown) => {
          this.submitting.set(false);
          this.error.set(applyProblemToForm(this.form, toProblem(error)));
        },
      });
  }
}
