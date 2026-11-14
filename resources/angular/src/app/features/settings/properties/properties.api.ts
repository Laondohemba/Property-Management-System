import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import { BffEnvelope, PageMeta, Property, PropertyInput } from '../../../core/bff/types';

export interface PropertyPage {
  items: Property[];
  meta: PageMeta;
}

/** Data access for /bff/settings/properties (SYS-03). */
@Injectable({ providedIn: 'root' })
export class PropertiesApi {
  private readonly http = inject(HttpClient);
  private readonly base = '/bff/settings/properties';

  list(options: { page?: number; perPage?: number; search?: string } = {}): Observable<PropertyPage> {
    let params = new HttpParams()
      .set('page', options.page ?? 1)
      .set('per_page', options.perPage ?? 25);

    if (options.search) {
      params = params.set('filter[search]', options.search);
    }

    return this.http
      .get<BffEnvelope<Property[], PageMeta>>(this.base, { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }

  get(id: string): Observable<Property> {
    return this.http.get<BffEnvelope<Property>>(`${this.base}/${id}`).pipe(map((response) => response.data));
  }

  create(input: PropertyInput): Observable<Property> {
    return this.http.post<BffEnvelope<Property>>(this.base, input).pipe(map((response) => response.data));
  }

  update(id: string, input: Partial<PropertyInput>): Observable<Property> {
    return this.http.patch<BffEnvelope<Property>>(`${this.base}/${id}`, input).pipe(map((response) => response.data));
  }
}
