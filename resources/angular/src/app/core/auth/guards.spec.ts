import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { ActivatedRouteSnapshot, Router, RouterStateSnapshot, UrlTree, provideRouter } from '@angular/router';

import { authGuard, guestGuard, safeReturnUrl } from './guards';

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

describe('safeReturnUrl', () => {
  it('accepts in-app paths only', () => {
    expect(safeReturnUrl('/account/profile')).toBe('/account/profile');
    expect(safeReturnUrl('//evil.example')).toBe('/dashboard');
    expect(safeReturnUrl('https://evil.example')).toBe('/dashboard');
    expect(safeReturnUrl(undefined)).toBe('/dashboard');
  });
});
