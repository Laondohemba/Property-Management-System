import { HttpContext, HttpContextToken } from '@angular/common/http';

/** Do not redirect to sign-in when this request returns 401 (e.g. the initial session probe). */
export const SKIP_AUTH_REDIRECT = new HttpContextToken<boolean>(() => false);

/** Internal marker: the request has already been retried once after a 419. */
export const CSRF_RETRIED = new HttpContextToken<boolean>(() => false);

/** POSTs with money or inventory effects must carry an Idempotency-Key (spec §8.1). */
export const IDEMPOTENT = new HttpContextToken<boolean>(() => false);

export function skipAuthRedirect(): HttpContext {
  return new HttpContext().set(SKIP_AUTH_REDIRECT, true);
}

export function idempotent(): HttpContext {
  return new HttpContext().set(IDEMPOTENT, true);
}
