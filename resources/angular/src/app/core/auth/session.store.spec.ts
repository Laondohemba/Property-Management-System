import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { SessionData } from '../bff/types';
import { SessionStore } from './session.store';

const session: SessionData = {
  user: { id: '01JTEST', name: 'Ada Admin', email: 'ada@example.com', two_factor_enabled: false, last_login_at: null },
  organization: { id: '01JORG', name: 'Demo Hotels' },
  roles: [{ name: 'super-admin', label: 'Super Admin' }],
  permissions: [],
  properties: [],
  active_property: null,
};

describe('SessionStore', () => {
  let store: InstanceType<typeof SessionStore>;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    store = TestBed.inject(SessionStore);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('starts in the unknown state', () => {
    expect(store.status()).toBe('unknown');
    expect(store.isAuthenticated()).toBeFalse();
  });

  it('becomes authenticated when /bff/session succeeds', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ data: session, meta: { app_version: '0.1.0' } });

    expect(await loading).toBeTrue();
    expect(store.isAuthenticated()).toBeTrue();
    expect(store.user()?.name).toBe('Ada Admin');
    expect(store.primaryRole()?.label).toBe('Super Admin');
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
    http.expectOne('/bff/session').flush({ data: session });

    expect(await first).toBeTrue();
    expect(await second).toBeTrue();
  });

  it('updates the two-factor flag in place', async () => {
    const loading = store.load();
    http.expectOne('/bff/session').flush({ data: session });
    await loading;

    store.setTwoFactorEnabled(true);

    expect(store.user()?.two_factor_enabled).toBeTrue();
  });
});
