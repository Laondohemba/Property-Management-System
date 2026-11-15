import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { AuditEntry, AuditLog, BffEnvelope, PageMeta } from '../../core/bff/types';

export type AuditSubjectType =
  | 'property'
  | 'room_type'
  | 'room'
  | 'amenity'
  | 'user'
  | 'inventory_block'
  | 'rate_plan'
  | 'tax'
  | 'cancellation_policy'
  | 'deposit_policy';

export interface AuditFilters {
  page?: number;
  perPage?: number;
  log?: AuditLog | '';
  search?: string;
  from?: string;
  to?: string;
  /** History of a single record. */
  subjectType?: AuditSubjectType;
  subject?: string;
}

/** GET /bff/audit-logs (AUD-03). */
@Injectable({ providedIn: 'root' })
export class AuditApi {
  private readonly http = inject(HttpClient);

  list(filters: AuditFilters = {}): Observable<{ items: AuditEntry[]; meta: PageMeta }> {
    let params = new HttpParams().set('page', filters.page ?? 1).set('per_page', filters.perPage ?? 50);

    if (filters.subjectType && filters.subject) {
      params = params.set('filter[subject_type]', filters.subjectType).set('filter[subject]', filters.subject);
    }
    if (filters.log) params = params.set('filter[log]', filters.log);
    if (filters.search) params = params.set('filter[search]', filters.search);
    if (filters.from) params = params.set('filter[from]', filters.from);
    if (filters.to) params = params.set('filter[to]', filters.to);

    return this.http
      .get<BffEnvelope<AuditEntry[], PageMeta>>('/bff/audit-logs', { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }
}
