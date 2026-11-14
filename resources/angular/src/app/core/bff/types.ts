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
}

export interface SessionData {
  user: SessionUser;
  organization: SessionOrganization | null;
  roles: SessionRole[];
  permissions: string[];
  properties: SessionProperty[];
  active_property: SessionProperty | null;
}

export interface SessionMeta {
  app_version: string;
}
