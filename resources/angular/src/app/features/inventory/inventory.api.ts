import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import {
  BffEnvelope,
  InventoryBlock,
  InventoryBlockInput,
  InventoryGrid,
  InventoryMeta,
  OverbookingInput,
  OverbookingResult,
  PageMeta,
} from '../../core/bff/types';
import { idempotent } from '../../core/http/context';

/**
 * Master inventory and blocks of the active property (CHN-01, CAL-06).
 * X-Property-Id is added by the BFF interceptor.
 */
@Injectable({ providedIn: 'root' })
export class InventoryApi {
  private readonly http = inject(HttpClient);
  private readonly base = '/bff/inventory';

  grid(from: string, to: string): Observable<{ grid: InventoryGrid; meta: InventoryMeta }> {
    return this.http
      .get<BffEnvelope<InventoryGrid, InventoryMeta>>(this.base, { params: { from, to } })
      .pipe(map((response) => ({ grid: response.data, meta: response.meta! })));
  }

  setOverbooking(input: OverbookingInput): Observable<OverbookingResult> {
    return this.http.put<BffEnvelope<OverbookingResult>>(`${this.base}/overbooking`, input).pipe(map((r) => r.data));
  }

  blocks(status: 'active' | 'all' = 'active', page = 1): Observable<{ items: InventoryBlock[]; meta: PageMeta }> {
    const params = new HttpParams().set('filter[status]', status).set('page', page).set('per_page', 50);

    return this.http
      .get<BffEnvelope<InventoryBlock[], PageMeta>>(`${this.base}/blocks`, { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }

  /** Changes inventory, so it carries an Idempotency-Key: a retry cannot block twice. */
  createBlock(input: InventoryBlockInput): Observable<InventoryBlock> {
    return this.http
      .post<BffEnvelope<InventoryBlock>>(`${this.base}/blocks`, input, { context: idempotent() })
      .pipe(map((r) => r.data));
  }

  releaseBlock(id: string): Observable<InventoryBlock> {
    return this.http.post<BffEnvelope<InventoryBlock>>(`${this.base}/blocks/${id}/release`, {}).pipe(map((r) => r.data));
  }
}
