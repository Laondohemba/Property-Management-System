import { provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { SessionData, SessionProperty } from '../bff/types';
import { bffInterceptor } from '../http/bff.interceptor';
import { SessionStore } from './session.store';

const alpha: SessionProperty = {
  id: '01JALPHA',
  name: 'Alpha Hotel',
  code: 'ALP',
  timezone: 'Africa/Lagos',
  currency: 'NGN',
  business_date: '2026-11-14',
  role: { name: 'front-desk-agent', label: 'Front Desk Agent' },
};
const beta: SessionProperty = { ...alpha, id: '01JBETA', name: 'Beta Hotel', code: 'BET', role: { name: 'accountant', label: 'Accountant / Finance' } };

function session(active: SessionProperty | null, permissions: string[] = ['dashboard.view']): SessionData {
  return {
    user: { id: '01JTEST', name: 'Ada Admin', email: 'ada@example.com', two_factor_enabled: false, last_login_at: null },
    organization: { id: '01JORG', name: 'Demo Hotels' },
    is_super_admin: false,
    roles: [],
    permissions,
    properties: [alpha, beta],
    active_property: active,
  };
}

describe('SessionStore', () => {
  let store: InstanceType<typeof SessionStore>;
  let http: HttpTestingController;

  beforeEach(() => {
    localStorage.removeItem('pms.activeProperty');
    TestBed.configureTestingModule({
      providers: [provideRouter([]), provideHttpClient(withInterceptors([bffInterceptor])), provideHttpClientTesting()],
    });
    store = TestBed.inject(SessionStore);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => {
    http.verify();
    localStorage.removeItem('pms.activeProperty');
  });

  it('starts in the unknown state', () => {
    expect(store.status()).toBe('unknown');
    expect(store.isAuthenticated()).toBeFalse();
  });

  it('becomes authenticated when /bff/session succeeds', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ data: session(alpha), meta: { app_version: '0.1.0' } });

    expect(await loading).toBeTrue();
    expect(store.isAuthenticated()).toBeTrue();
    expect(store.activeProperty()?.name).toBe('Alpha Hotel');
    expect(store.primaryRole()?.label).toBe('Front Desk Agent');
    expect(store.can('dashboard.view')).toBeTrue();
    expect(store.can('rates.manage')).toBeFalse();
  });

  it('becomes a guest when /bff/session returns 401', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ code: 'unauthenticated' }, { status: 401, statusText: 'Unauthorized' });

    expect(await loading).toBeFalse();
    expect(store.status()).toBe('guest');
  });

  it('shares one request between concurrent loads', async () => {
    const first = store.load();
    const second = store.load();
    http.expectOne('/bff/session').flush({ data: session(alpha) });

    expect(await first).toBeTrue();
    expect(await second).toBeTrue();
  });

  it('remembers the active property and sends it as X-Property-Id', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ data: session(alpha) });
    await loading;

    expect(localStorage.getItem('pms.activeProperty')).toBe(alpha.id);

    const switching = store.selectProperty(beta.id);
    const request = http.expectOne('/bff/session');
    expect(request.request.headers.get('X-Property-Id')).toBe(beta.id);
    request.flush({ data: session(beta, ['billing.view']) });
    await switching;

    expect(store.activeProperty()?.id).toBe(beta.id);
    expect(store.can('billing.view')).toBeTrue();
    expect(store.can('dashboard.view')).toBeFalse();
  });

  it('updates the two-factor flag in place', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ data: session(alpha) });
    await loading;

    store.setTwoFactorEnabled(true);

    expect(store.user()?.two_factor_enabled).toBeTrue();
  });
});
