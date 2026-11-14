import { ChangeDetectionStrategy, Component } from '@angular/core';
import { RouterLink } from '@angular/router';

/** NiceAdmin pages-error-404 equivalent. */
@Component({
  selector: 'pms-not-found-page',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink],
  template: `
    <main class="flex min-h-screen flex-col items-center justify-center px-4 text-center">
      <h1 class="font-heading text-[120px] font-bold leading-none text-primary sm:text-[180px]">404</h1>
      <h2 class="mt-2 mb-8 font-heading text-2xl font-bold text-navy">The page you are looking for doesn't exist.</h2>
      <a routerLink="/dashboard" class="pms-btn-primary rounded-full! bg-navy! px-8 hover:bg-navy/90!">Back to home</a>
    </main>
  `,
})
export class NotFoundPage {}
