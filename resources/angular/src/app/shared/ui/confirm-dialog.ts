import { DIALOG_DATA, Dialog, DialogRef } from '@angular/cdk/dialog';
import { ChangeDetectionStrategy, Component, Injectable, inject } from '@angular/core';
import { firstValueFrom } from 'rxjs';

export interface ConfirmOptions {
  title: string;
  message: string;
  confirmLabel?: string;
  cancelLabel?: string;
  /** "danger" for destructive or access-removing actions. */
  tone?: 'primary' | 'danger';
}

/**
 * Accessible confirmation modal (focus trap, Escape to cancel, focus restored afterwards).
 * Use this instead of the browser's confirm(); see ConfirmService.
 */
@Component({
  selector: 'pms-confirm-dialog',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: { role: 'alertdialog', 'aria-modal': 'true', 'aria-labelledby': 'pms-confirm-title', 'aria-describedby': 'pms-confirm-message' },
  template: `
    <div class="w-[min(92vw,28rem)] rounded-[5px] bg-surface p-6 shadow-dropdown ring-1 ring-line">
      <h2 id="pms-confirm-title" class="font-display text-lg font-medium text-heading">{{ data.title }}</h2>
      <p id="pms-confirm-message" class="mt-2 text-sm text-body">{{ data.message }}</p>
      <div class="mt-6 flex justify-end gap-3">
        <button type="button" class="pms-btn-secondary" (click)="close(false)" cdkFocusInitial>
          {{ data.cancelLabel ?? 'Cancel' }}
        </button>
        <button type="button" [class]="data.tone === 'danger' ? 'pms-btn-danger' : 'pms-btn-primary'" (click)="close(true)">
          {{ data.confirmLabel ?? 'Confirm' }}
        </button>
      </div>
    </div>
  `,
})
export class ConfirmDialog {
  protected readonly data = inject<ConfirmOptions>(DIALOG_DATA);
  private readonly ref = inject<DialogRef<boolean>>(DialogRef);

  protected close(confirmed: boolean): void {
    this.ref.close(confirmed);
  }
}

/**
 * `if (await confirm.ask({...})) { ... }`. Resolves false when cancelled, dismissed with Escape
 * or by clicking the backdrop.
 */
@Injectable({ providedIn: 'root' })
export class ConfirmService {
  private readonly dialog = inject(Dialog);

  async ask(options: ConfirmOptions): Promise<boolean> {
    const ref = this.dialog.open<boolean, ConfirmOptions>(ConfirmDialog, {
      data: options,
      backdropClass: 'pms-dialog-backdrop',
      ariaModal: true,
      autoFocus: 'first-tabbable',
      restoreFocus: true,
    });

    return (await firstValueFrom(ref.closed)) === true;
  }
}
