import { provideHttpClient, withFetch, withInterceptors, withXsrfConfiguration } from '@angular/common/http';
import { ApplicationConfig, inject, provideBrowserGlobalErrorListeners } from '@angular/core';
import { TitleStrategy, provideRouter, withComponentInputBinding, withNavigationErrorHandler } from '@angular/router';

import { routes } from './app.routes';
import { bffInterceptor } from './core/http/bff.interceptor';
import { ReleaseReloadService } from './core/release/release-reload';
import { PmsTitleStrategy } from './core/title.strategy';

export const appConfig: ApplicationConfig = {
  providers: [
    provideBrowserGlobalErrorListeners(),
    provideRouter(
      routes,
      withComponentInputBinding(),
      // A lazy chunk removed by a rollout reloads the page instead of failing (spec §4.4).
      withNavigationErrorHandler((error) => inject(ReleaseReloadService).handleNavigationError(error)),
    ),
    // Same-origin cookie session: HttpClient echoes XSRF-TOKEN as X-XSRF-TOKEN (spec §8.1).
    provideHttpClient(
      withFetch(),
      withXsrfConfiguration({ cookieName: 'XSRF-TOKEN', headerName: 'X-XSRF-TOKEN' }),
      withInterceptors([bffInterceptor]),
    ),
    { provide: TitleStrategy, useClass: PmsTitleStrategy },
  ],
};
