import { HttpClient } from '@angular/common/http';
import { computed, inject } from '@angular/core';
import { patchState, signalStore, withComputed, withMethods, withState } from '@ngrx/signals';
import { firstValueFrom } from 'rxjs';

import { BffEnvelope, SessionData, SessionMeta } from '../bff/types';
import { skipAuthRedirect } from '../http/context';

export type SessionStatus = 'unknown' | 'authenticated' | 'guest';

interface SessionState {
  session: SessionData | null;
  status: SessionStatus;
}

const initialState: SessionState = { session: null, status: 'unknown' };

/**
 * The signed-in user's session, loaded from GET /bff/session (spec §8.2).
 * The SPA never holds a token: the browser's HttpOnly cookie is the credential.
 */
export const SessionStore = signalStore(
  { providedIn: 'root' },
  withState(initialState),
  withComputed(({ session, status }) => ({
    user: computed(() => session()?.user ?? null),
    organization: computed(() => session()?.organization ?? null),
    primaryRole: computed(() => session()?.roles[0] ?? null),
    activePropertyId: computed(() => session()?.active_property?.id ?? null),
    isAuthenticated: computed(() => status() === 'authenticated'),
  })),
  withMethods((store, http = inject(HttpClient)) => {
    let pending: Promise<boolean> | null = null;

    const fetchSession = async (): Promise<boolean> => {
      try {
        const response = await firstValueFrom(
          http.get<BffEnvelope<SessionData, SessionMeta>>('/bff/session', { context: skipAuthRedirect() }),
        );
        patchState(store, { session: response.data, status: 'authenticated' });

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
