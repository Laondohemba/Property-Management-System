import { DOCUMENT } from '@angular/common';
import { Injectable, computed, effect, inject, signal } from '@angular/core';

export type ThemePreference = 'system' | 'light' | 'dark';
export type Theme = 'light' | 'dark';

const STORAGE_KEY = 'pms.theme';
const DARK_QUERY = '(prefers-color-scheme: dark)';

/**
 * Light / dark theme. Follows the operating system by default; the user can override it per
 * browser (a UI preference, not account data). Applies data-theme to <html>, which the theme
 * tokens in styles.css and the Tailwind `dark:` variant key off.
 */
@Injectable({ providedIn: 'root' })
export class ThemeService {
  private readonly document = inject(DOCUMENT);
  private readonly media = this.document.defaultView?.matchMedia?.(DARK_QUERY) ?? null;

  private readonly systemDark = signal(this.media?.matches ?? false);
  readonly preference = signal<ThemePreference>(this.readPreference());
  readonly theme = computed<Theme>(() => {
    const preference = this.preference();

    return preference === 'system' ? (this.systemDark() ? 'dark' : 'light') : preference;
  });

  constructor() {
    this.media?.addEventListener('change', (event) => this.systemDark.set(event.matches));

    effect(() => {
      const root = this.document.documentElement;
      root.dataset['theme'] = this.theme();
      root.dataset['themePreference'] = this.preference();
    });
  }

  setPreference(preference: ThemePreference): void {
    this.preference.set(preference);

    try {
      if (preference === 'system') {
        localStorage.removeItem(STORAGE_KEY);
      } else {
        localStorage.setItem(STORAGE_KEY, preference);
      }
    } catch {
      // Storage unavailable (private mode): the choice lasts for this visit only.
    }
  }

  private readPreference(): ThemePreference {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);

      return stored === 'light' || stored === 'dark' ? stored : 'system';
    } catch {
      return 'system';
    }
  }
}
