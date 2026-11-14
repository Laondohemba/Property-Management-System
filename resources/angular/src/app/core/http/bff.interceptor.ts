import {
  HttpErrorResponse,
  HttpInterceptorFn,
  HttpRequest,
  HttpResponse,
  HttpXsrfTokenExtractor,
} from '@angular/common/http';
import { inject } from '@angular/core';
import { Router } from '@angular/router';
import { catchError, switchMap, tap, throwError } from 'rxjs';

import { SessionStore } from '../auth/session.store';
import { AppVersionService } from './app-version.service';
import { CSRF_RETRIED, IDEMPOTENT, SKIP_AUTH_REDIRECT } from './context';
import { CsrfService } from './csrf.service';

const ABSOLUTE_URL = /^[a-z][a-z\d+\-.]*:\/\//i;

/**
 * Same-origin request plumbing for the BFF (spec §11.1):
 * JSON headers, X-Property-Id, Idempotency-Key, X-App-Version tracking,
 * one silent retry after a 419, and redirect to sign-in when the session ends.
 */
export const bffInterceptor: HttpInterceptorFn = (req, next) => {
  if (ABSOLUTE_URL.test(req.url)) {
    return next(req);
  }

  const session = inject(SessionStore);
  const csrf = inject(CsrfService);
  const xsrf = inject(HttpXsrfTokenExtractor);
  const router = inject(Router);
  const appVersion = inject(AppVersionService);

  let headers = req.headers.set('Accept', 'application/json').set('X-Requested-With', 'XMLHttpRequest');

  const propertyId = session.activePropertyId();
  if (propertyId && req.url.startsWith('/bff')) {
    headers = headers.set('X-Property-Id', propertyId);
  }

  if (req.context.get(IDEMPOTENT) && !headers.has('Idempotency-Key')) {
    headers = headers.set('Idempotency-Key', crypto.randomUUID());
  }

  const request = req.clone({ headers });

  const signOut = (): void => {
    session.clear();
    void router.navigate(['/sign-in'], { queryParams: { returnUrl: router.url } });
  };

  return next(request).pipe(
    tap((event) => {
      if (event instanceof HttpResponse) {
        appVersion.observe(event.headers.get('X-App-Version'));
      }
    }),
    catchError((error: unknown) => {
      if (!(error instanceof HttpErrorResponse)) {
        return throwError(() => error);
      }

      appVersion.observe(error.headers?.get('X-App-Version') ?? null);

      // Expired CSRF token or session: refresh the cookie and retry once (spec §8.4).
      if (error.status === 419 && !request.context.get(CSRF_RETRIED)) {
        return csrf.refresh().pipe(
          switchMap(() => next(withFreshXsrfToken(request, xsrf.getToken()))),
          catchError((retryError: unknown) => {
            if (retryError instanceof HttpErrorResponse && retryError.status === 419) {
              signOut();
            }

            return throwError(() => retryError);
          }),
        );
      }

      if (error.status === 401 && !request.context.get(SKIP_AUTH_REDIRECT) && session.isAuthenticated()) {
        signOut();
      }

      // A role now requires 2FA (e.g. changed by an admin mid-session): send the user to set it up.
      if (error.status === 403 && (error.error as { code?: string } | null)?.code === 'two_factor_required') {
        void session.load().then(() => router.navigate(['/account/profile']));
      }

      return throwError(() => error);
    }),
  );
};

function withFreshXsrfToken(request: HttpRequest<unknown>, token: string | null): HttpRequest<unknown> {
  const headers = token ? request.headers.set('X-XSRF-TOKEN', token) : request.headers;

  return request.clone({ headers, context: request.context.set(CSRF_RETRIED, true) });
}
