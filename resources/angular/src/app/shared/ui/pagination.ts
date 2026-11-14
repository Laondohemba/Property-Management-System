import { ChangeDetectionStrategy, Component, input, output } from '@angular/core';

import { PageMeta } from '../../core/bff/types';

@Component({
  selector: 'pms-pagination',
  changeDetection: ChangeDetectionStrategy.OnPush,
  template: `
    @if (meta(); as page) {
      @if (page.last_page > 1) {
        <nav class="mt-4 flex items-center justify-end gap-2 text-sm" aria-label="Pagination">
          <button type="button" class="pms-btn-secondary px-3 py-1" [disabled]="page.page <= 1" (click)="pageChange.emit(page.page - 1)">
            Previous
          </button>
          <span class="text-muted">Page {{ page.page }} of {{ page.last_page }}</span>
          <button type="button" class="pms-btn-secondary px-3 py-1" [disabled]="page.page >= page.last_page" (click)="pageChange.emit(page.page + 1)">
            Next
          </button>
        </nav>
      }
    }
  `,
})
export class Pagination {
  readonly meta = input<PageMeta | null>(null);
  readonly pageChange = output<number>();
}
