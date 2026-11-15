import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { BffEnvelope, PageMeta, PricePreview, RatePlan, RatePlanInput, RatePlanOptions } from '../../core/bff/types';

/** Rate plans of the active property (RTE-01, RTE-07). */
@Injectable({ providedIn: 'root' })
export class RatesApi {
  private readonly http = inject(HttpClient);
  private readonly base = '/bff/rate-plans';

  ratePlans(page = 1): Observable<{ items: RatePlan[]; meta: PageMeta }> {
    const params = new HttpParams().set('page', page).set('per_page', 50);
    return this.http.get<BffEnvelope<RatePlan[], PageMeta>>(this.base, { params }).pipe(map((r) => ({ items: r.data, meta: r.meta! })));
  }

  options(): Observable<RatePlanOptions> {
    return this.http.get<BffEnvelope<RatePlanOptions>>(`${this.base}/options`).pipe(map((r) => r.data));
  }

  ratePlan(id: string): Observable<RatePlan> {
    return this.http.get<BffEnvelope<RatePlan>>(`${this.base}/${id}`).pipe(map((r) => r.data));
  }

  save(input: RatePlanInput, id?: string): Observable<RatePlan> {
    const request = id
      ? this.http.patch<BffEnvelope<RatePlan>>(`${this.base}/${id}`, input)
      : this.http.post<BffEnvelope<RatePlan>>(this.base, input);
    return request.pipe(map((r) => r.data));
  }

  preview(id: string, roomTypeId: string, adults: number, children: number, nights: number): Observable<PricePreview> {
    const params = new HttpParams().set('room_type_id', roomTypeId).set('adults', adults).set('children', children).set('nights', nights);
    return this.http.get<BffEnvelope<PricePreview>>(`${this.base}/${id}/preview`, { params }).pipe(map((r) => r.data));
  }
}
