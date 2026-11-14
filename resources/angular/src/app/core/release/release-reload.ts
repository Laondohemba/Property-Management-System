import { DestroyRef, Injectable, InjectionToken, inject } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { NavigationError, NavigationStart, Router } from '@angular/router';

import { AppVersionService } from '../http/app-version.service';

/** Full page load of a URL. A token so tests can observe it instead of leaving the page. */
export const HARD_NAVIGATE = new InjectionToken<(url: string) => void>('HARD_NAVIGATE', {
  providedIn: 'root',
  factory: () => (url: string) => window.location.assign(url),
});

// Messages browsers use when a lazy chunk cannot be fetched (Chromium, Firefox, Safari).
const CHUNK_LOAD_FAILURE = /dynamically imported module|Importing a module script failed|Loading chunk [\w-]+ failed/i;

export function isChunkLoadError(error: unknown): boolean {
  const message = error instanceof Error ? error.message : typeof error === 'string' ? error : '';

  return CHUNK_LOAD_FAILURE.test(message);
}

/**
 * Version skew during zero-downtime rollouts (spec §4.4, DBM-08).
 *
 * - A newer release is live (X-App-Version changed): the banner offers a reload, and the
 *   next in-app navigation becomes a full page load so the new bundle is picked up at a
 *   safe point rather than mid-task.
 * - A lazy chunk of the old release is gone: reload the target URL from the server, which
 *   now serves the new release's index.html.
 */
@Injectable({ providedIn: 'root' })
export class ReleaseReloadService {
  private readonly router = inject(Router);
  private readonly version = inject(AppVersionService);
  private readonly hardNavigate = inject(HARD_NAVIGATE);

  constructor() {
    this.router.events.pipe(takeUntilDestroyed(inject(DestroyRef))).subscribe((event) => {
      if (event instanceof NavigationStart && this.version.updateAvailable()) {
        this.hardNavigate(event.url);
      }
    });
  }

  /** Router navigation error handler (`withNavigationErrorHandler`). */
  handleNavigationError(event: NavigationError): void {
    if (isChunkLoadError(event.error)) {
      this.hardNavigate(event.url);
    }
  }
}
