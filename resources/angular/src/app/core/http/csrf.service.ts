import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { skipAuthRedirect } from './context';

/**
 * Issues the XSRF-TOKEN cookie. Angular's HttpClient then sends it back as
 * X-XSRF-TOKEN on same-origin state-changing requests (spec §8.1).
 */
@Injectable({ providedIn: 'root' })
export class CsrfService {
  private readonly http = inject(HttpClient);

  refresh(): Observable<void> {
    return this.http.get('/sanctum/csrf-cookie', { context: skipAuthRedirect() }).pipe(map(() => undefined));
  }
}
