import { HttpClient } from '@angular/common/http';
import { computed, inject } from '@angular/core';
import { patchState, signalStore, withComputed, withMethods, withState } from '@ngrx/signals';
import { firstValueFrom } from 'rxjs';

import { BffEnvelope, Permission, SessionData, SessionMeta } from '../bff/types';
import { skipAuthRedirect } from '../http/context';

export type SessionStatus = 'unknown' | 'authenticated' | 'guest';

interface SessionState {
  session: SessionData | null;
  status: SessionStatus;
  /** The property the user picked; sent as X-Property-Id (spec §2.7). */
  selectedPropertyId: string | null;
}

/** Remembering the selected property is a UI convenience, not a credential. */
const SELECTED_PROPERTY_KEY = 'pms.activeProperty';

function readSelectedProperty(): string | null {
  try {
    return localStorage.getItem(SELECTED_PROPERTY_KEY);
  } catch {
    return null;
  }
}

function writeSelectedProperty(id: string | null): void {
  try {
    if (id) {
      localStorage.setItem(SELECTED_PROPERTY_KEY, id);
    } else {
      localStorage.removeItem(SELECTED_PROPERTY_KEY);
    }
  } catch {
    // Storage unavailable (private mode): selection simply isn't remembered.
  }
}

/**
 * The signed-in user's session, loaded from GET /bff/session (spec §8.2).
 * The SPA never holds a token: the browser's HttpOnly cookie is the credential.
 */
export const SessionStore = signalStore(
  { providedIn: 'root' },
  withState<SessionState>(() => ({ session: null, status: 'unknown', selectedPropertyId: readSelectedProperty() })),
  withComputed(({ session, status, selectedPropertyId }) => ({
    user: computed(() => session()?.user ?? null),
    organization: computed(() => session()?.organization ?? null),
    isAuthenticated: computed(() => status() === 'authenticated'),
    isSuperAdmin: computed(() => session()?.is_super_admin ?? false),
    properties: computed(() => session()?.properties ?? []),
    activeProperty: computed(() => session()?.active_property ?? null),
    activePropertyId: computed(() => session()?.active_property?.id ?? selectedPropertyId()),
    /** Role at the active property (or the organisation-wide role for Super Admins). */
    primaryRole: computed(() => session()?.active_property?.role ?? session()?.roles[0] ?? null),
    permissions: computed(() => new Set<Permission>(session()?.permissions ?? [])),
    /** A role requires 2FA and the user has not enabled it yet (SYS-01). */
    mustSetUpTwoFactor: computed(() => {
      const user = session()?.user;

      return !!user && user.two_factor_required && !user.two_factor_enabled;
    }),
    /** May open staff administration (at least one property, or organisation-wide). */
    canManageStaff: computed(() => (session()?.is_super_admin ?? false) || (session()?.permissions ?? []).includes('users.manage')),
  })),
  withMethods((store, http = inject(HttpClient)) => {
    let pending: Promise<boolean> | null = null;

    const fetchSession = async (): Promise<boolean> => {
      try {
        const response = await firstValueFrom(
          http.get<BffEnvelope<SessionData, SessionMeta>>('/bff/session', { context: skipAuthRedirect() }),
        );
        const activeId = response.data.active_property?.id ?? null;
        writeSelectedProperty(activeId);
        patchState(store, { session: response.data, status: 'authenticated', selectedPropertyId: activeId });

        return true;
      } catch {
        patchState(store, { session: null, status: 'guest' });

        return false;
      }
    };

    return {
      /** Load (or reload) the session. Concurrent callers share one request. */
      load(): Promise<boolean> {
        pending ??= fetchSession().finally(() => (pending = null));

        return pending;
      },

      /** Switch the active property and reload permissions for it. */
      async selectProperty(id: string): Promise<boolean> {
        writeSelectedProperty(id);
        patchState(store, {
          selectedPropertyId: id,
          session: store.session() ? { ...store.session()!, active_property: null } : null,
        });

        return this.load();
      },

      can(permission: Permission): boolean {
        return store.permissions().has(permission);
      },

      clear(): void {
        patchState(store, { session: null, status: 'guest' });
      },

      setTwoFactorEnabled(enabled: boolean): void {
        const session = store.session();

        if (session) {
          patchState(store, { session: { ...session, user: { ...session.user, two_factor_enabled: enabled } } });
        }
      },
    };
  }),
);
