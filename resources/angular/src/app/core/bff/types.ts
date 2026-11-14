/**
 * BFF response contracts.
 *
 * Hand-written for now; ARC-11 requires these to be generated from the Laravel
 * Resources/DTOs (spatie/laravel-data + typescript-transformer) and checked in CI.
 */

export interface BffEnvelope<TData, TMeta = Record<string, unknown>> {
  data: TData;
  meta?: TMeta;
}

export interface PageMeta {
  page: number;
  per_page: number;
  total: number;
  last_page: number;
}

export interface SessionUser {
  id: string;
  name: string;
  email: string;
  two_factor_enabled: boolean;
  last_login_at: string | null;
}

export interface SessionOrganization {
  id: string;
  name: string;
}

export interface SessionRole {
  name: string;
  label: string;
}

export interface SessionProperty {
  id: string;
  name: string;
  code: string;
  timezone: string;
  currency: string;
  business_date: string;
  role: SessionRole | null;
}

export interface SessionData {
  user: SessionUser;
  organization: SessionOrganization | null;
  is_super_admin: boolean;
  roles: SessionRole[];
  /** Permissions held at the active property. */
  permissions: string[];
  properties: SessionProperty[];
  active_property: SessionProperty | null;
}

export interface SessionMeta {
  app_version: string;
}

export interface Property {
  id: string;
  name: string;
  code: string;
  timezone: string;
  currency: string;
  business_date: string;
  check_in_time: string;
  check_out_time: string;
  email: string | null;
  phone: string | null;
  address_line1: string | null;
  address_line2: string | null;
  city: string | null;
  state: string | null;
  postal_code: string | null;
  country: string | null;
  is_active: boolean;
  updated_at: string | null;
}

export type PropertyInput = Omit<Property, 'id' | 'business_date' | 'updated_at'>;

export interface DashboardData {
  property: {
    id: string;
    name: string;
    code: string;
    timezone: string;
    currency: string;
    business_date: string;
    check_in_time: string;
    check_out_time: string;
    local_time: string;
  };
  tiles: unknown[];
  alerts: unknown[];
}
