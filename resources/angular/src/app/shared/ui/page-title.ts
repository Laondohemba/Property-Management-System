import { ChangeDetectionStrategy, Component, input } from '@angular/core';
import { RouterLink } from '@angular/router';

export interface Breadcrumb {
  label: string;
  link?: string;
}

@Component({
  selector: 'pms-page-title',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [RouterLink],
  template: `
    <div class="mb-5">
      <h1 class="font-heading text-2xl font-semibold text-heading">{{ title() }}</h1>
      <nav aria-label="Breadcrumb">
        <ol class="mt-1 flex flex-wrap items-center font-heading text-sm font-semibold text-muted">
          <li><a routerLink="/dashboard" class="text-muted transition hover:text-body">Home</a></li>
          @for (crumb of breadcrumbs(); track crumb.label; let last = $last) {
            <li class="flex items-center">
              <span class="px-2 text-subtle">/</span>
              @if (crumb.link && !last) {
                <a [routerLink]="crumb.link" class="text-muted transition hover:text-body">{{ crumb.label }}</a>
              } @else {
                <span class="text-heading" [attr.aria-current]="last ? 'page' : null">{{ crumb.label }}</span>
              }
            </li>
          }
        </ol>
      </nav>
    </div>
  `,
})
export class PageTitle {
  readonly title = input.required<string>();
  readonly breadcrumbs = input<Breadcrumb[]>([]);
}
