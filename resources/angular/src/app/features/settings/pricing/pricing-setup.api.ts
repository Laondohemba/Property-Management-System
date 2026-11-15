import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import {
  BffEnvelope,
  CancellationPolicy,
  CancellationPolicyInput,
  DepositPolicy,
  DepositPolicyInput,
  Policies,
  PoliciesMeta,
  Tax,
  TaxInput,
  TaxListMeta,
} from '../../../core/bff/types';

/** Taxes and fees (spec §2.8) and policies (RTE-07) of the active property. */
@Injectable({ providedIn: 'root' })
export class PricingSetupApi {
  private readonly http = inject(HttpClient);
  private readonly base = '/bff/settings';

  taxes(): Observable<{ items: Tax[]; meta: TaxListMeta }> {
    return this.http.get<BffEnvelope<Tax[], TaxListMeta>>(`${this.base}/taxes`).pipe(map((r) => ({ items: r.data, meta: r.meta! })));
  }

  saveTax(input: TaxInput, id?: string): Observable<Tax> {
    const request = id
      ? this.http.patch<BffEnvelope<Tax>>(`${this.base}/taxes/${id}`, input)
      : this.http.post<BffEnvelope<Tax>>(`${this.base}/taxes`, input);
    return request.pipe(map((r) => r.data));
  }

  policies(): Observable<{ policies: Policies; meta: PoliciesMeta }> {
    return this.http.get<BffEnvelope<Policies, PoliciesMeta>>(`${this.base}/policies`).pipe(map((r) => ({ policies: r.data, meta: r.meta! })));
  }

  saveCancellation(input: CancellationPolicyInput, id?: string): Observable<CancellationPolicy> {
    const request = id
      ? this.http.patch<BffEnvelope<CancellationPolicy>>(`${this.base}/cancellation-policies/${id}`, input)
      : this.http.post<BffEnvelope<CancellationPolicy>>(`${this.base}/cancellation-policies`, input);
    return request.pipe(map((r) => r.data));
  }

  saveDeposit(input: DepositPolicyInput, id?: string): Observable<DepositPolicy> {
    const request = id
      ? this.http.patch<BffEnvelope<DepositPolicy>>(`${this.base}/deposit-policies/${id}`, input)
      : this.http.post<BffEnvelope<DepositPolicy>>(`${this.base}/deposit-policies`, input);
    return request.pipe(map((r) => r.data));
  }
}
