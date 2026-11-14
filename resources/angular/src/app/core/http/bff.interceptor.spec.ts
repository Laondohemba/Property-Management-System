import { HttpClient, provideHttpClient, withInterceptors } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';

import { AppVersionService } from './app-version.service';
import { bffInterceptor } from './bff.interceptor';
import { idempotent } from './context';

describe('bffInterceptor', () => {
  let client: HttpClient;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({
      providers: [
        provideRouter([]),
        provideHttpClient(withInterceptors([bffInterceptor])),
        provideHttpClientTesting(),
      ],
    });
    client = TestBed.inject(HttpClient);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('asks for JSON on same-origin requests', () => {
    client.get('/bff/anything').subscribe();

    const request = http.expectOne('/bff/anything').request;
    expect(request.headers.get('Accept')).toBe('application/json');
    expect(request.headers.get('X-Requested-With')).toBe('XMLHttpRequest');
  });

  it('leaves absolute URLs untouched', () => {
    client.get('https://example.com/data').subscribe();

    expect(http.expectOne('https://example.com/data').request.headers.has('X-Requested-With')).toBeFalse();
  });

  it('adds an Idempotency-Key when requested', () => {
    client.post('/bff/reservations', {}, { context: idempotent() }).subscribe();

    expect(http.expectOne('/bff/reservations').request.headers.get('Idempotency-Key')).toMatch(/^[0-9a-f-]{36}$/);
  });

  it('records X-App-Version and flags a newer release', () => {
    const version = TestBed.inject(AppVersionService);

    client.get('/bff/one').subscribe();
    http.expectOne('/bff/one').flush({}, { headers: { 'X-App-Version': '1.0.0' } });
    client.get('/bff/two').subscribe();
    http.expectOne('/bff/two').flush({}, { headers: { 'X-App-Version': '1.1.0' } });

    expect(version.version()).toBe('1.1.0');
    expect(version.updateAvailable()).toBeTrue();
  });

  it('refreshes the CSRF cookie and retries once after a 419', () => {
    let result: unknown;
    client.post('/bff/thing', {}).subscribe((body) => (result = body));

    http.expectOne('/bff/thing').flush({}, { status: 419, statusText: 'Page Expired' });
    http.expectOne('/sanctum/csrf-cookie').flush(null, { status: 204, statusText: 'No Content' });
    http.expectOne('/bff/thing').flush({ ok: true });

    expect(result).toEqual({ ok: true });
  });
});
