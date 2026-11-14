import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';

import { authGuard, guestGuard, safeReturnUrl, twoFactorEnrollmentGuard } from './guards';
import { SessionStore } from './session.store';

describe('route guards', () => {
  let http: HttpTestingController;

  const run = (guard: typeof authGuard, url = '/account/profile') =>
    TestBed.runInInjectionContext(() => guard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot));

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  it('sends guests to sign-in with a return URL', async () => {
    const result = run(authGuard) as Promise<boolean | UrlTree>;
    await Promise.resolve();
    http.expectOne('/bff/session').flush({}, { status: 401, statusText: 'Unauthorized' });

    const tree = (await result) as UrlTree;
    expect(TestBed.inject(Router).serializeUrl(tree)).toBe('/sign-in?returnUrl=%2Faccount%2Fprofile');
  });

  it('sends signed-in users away from guest pages', async () => {
    const result = run(guestGuard) as Promise<boolean | UrlTree>;
    await Promise.resolve();
    http.expectOne('/bff/session').flush({ data: { user: {}, roles: [], permissions: [], properties: [] } });

    expect(TestBed.inject(Router).serializeUrl((await result) as UrlTree)).toBe('/dashboard');
  });
});

describe('twoFactorEnrollmentGuard', () => {
  let http: HttpTestingController;

  const run = (url: string) =>
    TestBed.runInInjectionContext(() => twoFactorEnrollmentGuard({} as ActivatedRouteSnapshot, { url } as RouterStateSnapshot));

  async function signIn(required: boolean, enabled: boolean): Promise<void> {
    const loading = TestBed.inject(SessionStore).load();
    http.expectOne('/bff/session').flush({
      data: {
        user: { id: '1', name: 'A', email: 'a@example.com', two_factor_enabled: enabled, two_factor_required: required, last_login_at: null },
        organization: null,
        is_super_admin: false,
        roles: [],
        permissions: [],
        properties: [],
        active_property: null,
      },
    });
    await loading;
  }

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideRouter([]), provideHttpClient(), provideHttpClientTesting()] });
    http = TestBed.inject(HttpTestingController);
  });

  it('sends users who must set up 2FA to their profile', async () => {
    await signIn(true, false);

    expect(TestBed.inject(Router).serializeUrl(run('/dashboard') as UrlTree)).toBe('/account/profile');
    expect(run('/account/profile')).toBeTrue();
  });

  it('lets everyone else through', async () => {
    await signIn(true, true);

    expect(run('/dashboard')).toBeTrue();
  });
});

describe('safeReturnUrl', () => {
  it('accepts in-app paths only', () => {
    expect(safeReturnUrl('/account/profile')).toBe('/account/profile');
    expect(safeReturnUrl('//evil.example')).toBe('/dashboard');
    expect(safeReturnUrl('https://evil.example')).toBe('/dashboard');
    expect(safeReturnUrl(undefined)).toBe('/dashboard');
  });
});
