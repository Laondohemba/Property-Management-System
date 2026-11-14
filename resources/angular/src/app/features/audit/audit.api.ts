import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { AuditEntry, AuditLog, BffEnvelope, PageMeta } from '../../core/bff/types';

export interface AuditFilters {
  page?: number;
  log?: AuditLog | '';
  search?: string;
  from?: string;
  to?: string;
}

/** GET /bff/audit-logs (AUD-03). */
@Injectable({ providedIn: 'root' })
export class AuditApi {
  private readonly http = inject(HttpClient);

  list(filters: AuditFilters = {}): Observable<{ items: AuditEntry[]; meta: PageMeta }> {
    let params = new HttpParams().set('page', filters.page ?? 1).set('per_page', 50);

    if (filters.log) params = params.set('filter[log]', filters.log);
    if (filters.search) params = params.set('filter[search]', filters.search);
    if (filters.from) params = params.set('filter[from]', filters.from);
    if (filters.to) params = params.set('filter[to]', filters.to);

    return this.http
      .get<BffEnvelope<AuditEntry[], PageMeta>>('/bff/audit-logs', { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }
}
