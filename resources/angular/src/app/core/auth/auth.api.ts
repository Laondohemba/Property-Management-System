import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map, switchMap } from 'rxjs';

import { CsrfService } from '../http/csrf.service';

export interface Credentials {
  email: string;
  password: string;
  remember: boolean;
}

export interface TwoFactorChallenge {
  code?: string;
  recovery_code?: string;
}

export interface PasswordReset {
  token: string;
  email: string;
  password: string;
  password_confirmation: string;
}

export interface PasswordChange {
  current_password: string;
  password: string;
  password_confirmation: string;
}

interface MessageResponse {
  message: string;
}

/**
 * Session authentication endpoints served by Laravel Fortify (spec §8.2).
 * These live at the root (not /bff) and rely solely on the session cookie + CSRF.
 */
@Injectable({ providedIn: 'root' })
export class AuthApi {
  private readonly http = inject(HttpClient);
  private readonly csrf = inject(CsrfService);

  /** Resolves `twoFactor: true` when the user must complete the TOTP challenge. */
  login(credentials: Credentials): Observable<{ twoFactor: boolean }> {
    return this.csrf.refresh().pipe(
      switchMap(() => this.http.post<{ two_factor?: boolean }>('/login', credentials)),
      map((response) => ({ twoFactor: response?.two_factor === true })),
    );
  }

  twoFactorChallenge(payload: TwoFactorChallenge): Observable<void> {
    return this.http.post<void>('/two-factor-challenge', payload);
  }

  logout(): Observable<void> {
    return this.http.post<void>('/logout', {});
  }

  forgotPassword(email: string): Observable<string> {
    return this.csrf.refresh().pipe(
      switchMap(() => this.http.post<MessageResponse>('/forgot-password', { email })),
      map((response) => response.message),
    );
  }

  resetPassword(payload: PasswordReset): Observable<string> {
    return this.csrf.refresh().pipe(
      switchMap(() => this.http.post<MessageResponse>('/reset-password', payload)),
      map((response) => response.message),
    );
  }

  updatePassword(payload: PasswordChange): Observable<void> {
    return this.http.put<void>('/user/password', payload);
  }

  confirmPassword(password: string): Observable<void> {
    return this.http.post<void>('/user/confirm-password', { password });
  }

  enableTwoFactor(): Observable<void> {
    return this.http.post<void>('/user/two-factor-authentication', {});
  }

  disableTwoFactor(): Observable<void> {
    return this.http.delete<void>('/user/two-factor-authentication');
  }

  twoFactorQrCode(): Observable<{ svg: string; url: string }> {
    return this.http.get<{ svg: string; url: string }>('/user/two-factor-qr-code');
  }

  twoFactorSecretKey(): Observable<string> {
    return this.http.get<{ secretKey: string }>('/user/two-factor-secret-key').pipe(map((r) => r.secretKey));
  }

  confirmTwoFactor(code: string): Observable<void> {
    return this.http.post<void>('/user/confirmed-two-factor-authentication', { code });
  }

  recoveryCodes(): Observable<string[]> {
    return this.http.get<string[]>('/user/two-factor-recovery-codes');
  }

  regenerateRecoveryCodes(): Observable<void> {
    return this.http.post<void>('/user/two-factor-recovery-codes', {});
  }
}
