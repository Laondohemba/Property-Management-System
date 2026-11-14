import { inject } from '@angular/core';
import { CanActivateFn, Router } from '@angular/router';

import { SessionStore } from './session.store';

/** Only signed-in users; others go to sign-in and come back afterwards. */
export const authGuard: CanActivateFn = async (_route, state) => {
  const session = inject(SessionStore);
  const router = inject(Router);

  if (session.status() === 'unknown') {
    await session.load();
  }

  return session.isAuthenticated()
    ? true
    : router.createUrlTree(['/sign-in'], { queryParams: { returnUrl: state.url } });
};

/** Only guests (sign-in, password reset); signed-in users go to the dashboard. */
export const guestGuard: CanActivateFn = async () => {
  const session = inject(SessionStore);
  const router = inject(Router);

  if (session.status() === 'unknown') {
    await session.load();
  }

  return session.isAuthenticated() ? router.createUrlTree(['/dashboard']) : true;
};

/** Accept only same-app paths as post-login destinations (prevents open redirects). */
export function safeReturnUrl(url: string | null | undefined): string {
  return url && url.startsWith('/') && !url.startsWith('//') ? url : '/dashboard';
}
