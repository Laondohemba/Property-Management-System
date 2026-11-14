import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, map } from 'rxjs';

import {
  BffEnvelope,
  PermissionGroup,
  RoleDetail,
  RoleInput,
  StaffListMetaData,
  StaffOptions,
  StaffResponseMetaData,
  StaffStatus,
  StaffUser,
} from '../../../core/bff/types';

export interface StaffFilters {
  page?: number;
  search?: string;
  property?: string;
  status?: StaffStatus | '';
}

export interface StaffResult {
  user: StaffUser;
  options: StaffOptions;
  invited?: boolean;
}

/** Data access for /bff/users and /bff/roles (SYS-01, SYS-02). */
@Injectable({ providedIn: 'root' })
export class StaffApi {
  private readonly http = inject(HttpClient);

  list(filters: StaffFilters = {}): Observable<{ items: StaffUser[]; meta: StaffListMetaData }> {
    let params = new HttpParams().set('page', filters.page ?? 1).set('per_page', 25);

    if (filters.search) params = params.set('filter[search]', filters.search);
    if (filters.property) params = params.set('filter[property]', filters.property);
    if (filters.status) params = params.set('filter[status]', filters.status);

    return this.http
      .get<BffEnvelope<StaffUser[], StaffListMetaData>>('/bff/users', { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }

  get(id: string): Observable<StaffResult> {
    return this.http.get<BffEnvelope<StaffUser, StaffResponseMetaData>>(`/bff/users/${id}`).pipe(map(toResult));
  }

  invite(input: { name: string; email: string; property_id: string; role: string }): Observable<StaffResult> {
    return this.http.post<BffEnvelope<StaffUser, StaffResponseMetaData>>('/bff/users', input).pipe(map(toResult));
  }

  updateAccount(id: string, input: { name?: string; is_active?: boolean; is_super_admin?: boolean }): Observable<StaffResult> {
    return this.http.patch<BffEnvelope<StaffUser, StaffResponseMetaData>>(`/bff/users/${id}`, input).pipe(map(toResult));
  }

  assign(id: string, propertyId: string, role: string): Observable<StaffResult> {
    return this.http.put<BffEnvelope<StaffUser, StaffResponseMetaData>>(`/bff/users/${id}/properties/${propertyId}`, { role }).pipe(map(toResult));
  }

  unassign(id: string, propertyId: string): Observable<StaffResult> {
    return this.http.delete<BffEnvelope<StaffUser, StaffResponseMetaData>>(`/bff/users/${id}/properties/${propertyId}`).pipe(map(toResult));
  }

  resendInvitation(id: string): Observable<void> {
    return this.http.post<void>(`/bff/users/${id}/invitation`, {});
  }

  // Roles ---------------------------------------------------------------------

  roles(): Observable<{ roles: RoleDetail[]; groups: PermissionGroup[] }> {
    return this.http
      .get<BffEnvelope<RoleDetail[], { permission_groups: PermissionGroup[] }>>('/bff/roles')
      .pipe(map((response) => ({ roles: response.data, groups: response.meta!.permission_groups })));
  }

  role(name: string): Observable<{ role: RoleDetail; groups: PermissionGroup[] }> {
    return this.http
      .get<BffEnvelope<RoleDetail, { permission_groups: PermissionGroup[] }>>(`/bff/roles/${name}`)
      .pipe(map((response) => ({ role: response.data, groups: response.meta!.permission_groups })));
  }

  createRole(input: RoleInput): Observable<RoleDetail> {
    return this.http.post<BffEnvelope<RoleDetail>>('/bff/roles', input).pipe(map((response) => response.data));
  }

  updateRole(name: string, input: Partial<RoleInput>): Observable<RoleDetail> {
    return this.http.patch<BffEnvelope<RoleDetail>>(`/bff/roles/${name}`, input).pipe(map((response) => response.data));
  }

  deleteRole(name: string): Observable<void> {
    return this.http.delete<void>(`/bff/roles/${name}`);
  }
}

function toResult(response: BffEnvelope<StaffUser, StaffResponseMetaData>): StaffResult {
  const { invited, ...options } = response.meta!;

  return { user: response.data, options, invited };
}
