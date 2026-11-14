import { Injectable, computed, signal } from '@angular/core';

/**
 * Tracks the X-App-Version header. When the server version changes after the SPA
 * loaded, a newer release is live and the user should reload (spec §4.4, §8.1).
 */
@Injectable({ providedIn: 'root' })
export class AppVersionService {
  private readonly loadedVersion = signal<string | null>(null);
  private readonly latestVersion = signal<string | null>(null);

  readonly version = this.latestVersion.asReadonly();
  readonly updateAvailable = computed(() => {
    const loaded = this.loadedVersion();
    const latest = this.latestVersion();

    return loaded !== null && latest !== null && loaded !== latest;
  });

  observe(version: string | null): void {
    if (!version) {
      return;
    }

    if (this.loadedVersion() === null) {
      this.loadedVersion.set(version);
    }

    this.latestVersion.set(version);
  }
}
