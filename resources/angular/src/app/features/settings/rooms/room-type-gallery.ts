import { CdkDrag, CdkDragDrop, CdkDragPlaceholder, CdkDropList, moveItemInArray } from '@angular/cdk/drag-drop';
import { ChangeDetectionStrategy, Component, DestroyRef, computed, inject, input, linkedSignal, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { EMPTY, Observable, catchError, concatMap, from, tap } from 'rxjs';

import { toProblem } from '../../../core/bff/problem';
import { RoomTypeImage } from '../../../core/bff/types';
import { ConfirmService } from '../../../shared/ui/confirm-dialog';
import { Icon } from '../../../shared/ui/icon';
import { InventoryApi } from './inventory.api';

export const MAX_IMAGES = 20;
export const MAX_BYTES = 5 * 1024 * 1024;
export const ACCEPTED_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

interface PendingUpload {
  key: number;
  name: string;
  progress: number;
  error?: string;
}

/**
 * Room-type gallery (SYS-04): upload (pick or drop), describe, reorder and remove images.
 * The first image is the cover. Reordering works by drag and drop or with the move buttons,
 * so it is keyboard accessible. The server validates everything again.
 */
@Component({
  selector: 'pms-room-type-gallery',
  changeDetection: ChangeDetectionStrategy.OnPush,
  imports: [CdkDropList, CdkDrag, CdkDragPlaceholder, Icon],
  templateUrl: './room-type-gallery.html',
})
export class RoomTypeGallery {
  readonly roomTypeId = input.required<string>();
  readonly images = input.required<RoomTypeImage[]>();

  private readonly api = inject(InventoryApi);
  private readonly confirm = inject(ConfirmService);
  private readonly destroyRef = inject(DestroyRef);

  /** Working copy: follows the input, updated optimistically by uploads, moves and removals. */
  protected readonly gallery = linkedSignal(() => [...this.images()]);
  protected readonly uploads = signal<PendingUpload[]>([]);
  protected readonly dragOver = signal(false);
  protected readonly error = signal<string | null>(null);
  protected readonly status = signal<string | null>(null);
  protected readonly busy = signal(false);

  protected readonly accept = ACCEPTED_TYPES.join(',');
  protected readonly maxImages = MAX_IMAGES;
  protected readonly inFlight = computed(() => this.uploads().filter((upload) => !upload.error).length);
  protected readonly remaining = computed(() => Math.max(0, MAX_IMAGES - this.gallery().length - this.inFlight()));

  private nextKey = 0;

  protected onFilesPicked(event: Event): void {
    const input = event.target as HTMLInputElement;
    this.addFiles(Array.from(input.files ?? []));
    input.value = '';
  }

  protected onDrop(event: DragEvent): void {
    event.preventDefault();
    this.dragOver.set(false);
    this.addFiles(Array.from(event.dataTransfer?.files ?? []));
  }

  protected onDragOver(event: DragEvent): void {
    if (event.dataTransfer?.types.includes('Files')) {
      event.preventDefault();
      this.dragOver.set(true);
    }
  }

  protected dismissUpload(key: number): void {
    this.uploads.update((list) => list.filter((upload) => upload.key !== key));
  }

  /**
   * Validates locally for quick feedback, then uploads one file at a time so the gallery keeps
   * the order the files were picked in (the first image is the cover).
   */
  addFiles(files: File[]): void {
    this.error.set(null);
    const accepted: { key: number; file: File }[] = [];
    let skipped = 0;

    for (const file of files) {
      const key = this.nextKey++;
      const problem = this.check(file);

      if (problem) {
        this.uploads.update((list) => [...list, { key, name: file.name, progress: 0, error: problem }]);
      } else if (accepted.length >= this.remaining()) {
        skipped++;
      } else {
        accepted.push({ key, file });
        this.uploads.update((list) => [...list, { key, name: file.name, progress: 0 }]);
      }
    }

    if (skipped) {
      this.error.set(`${skipped} file${skipped === 1 ? ' was' : 's were'} not added: a room type can have at most ${MAX_IMAGES} images.`);
    }

    from(accepted)
      .pipe(
        concatMap(({ key, file }) => this.uploadOne(key, file)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe();
  }

  protected async remove(image: RoomTypeImage): Promise<void> {
    const confirmed = await this.confirm.ask({
      title: 'Remove image',
      message: `"${image.file_name}" will be removed from this room type. This cannot be undone.`,
      confirmLabel: 'Remove',
      tone: 'danger',
    });
    if (!confirmed) {
      return;
    }

    this.api.deleteRoomTypeImage(this.roomTypeId(), image.id).subscribe({
      next: () => {
        this.gallery.update((list) => list.filter((item) => item.id !== image.id));
        this.status.set(`Removed ${image.file_name}.`);
      },
      error: (error: unknown) => this.error.set(toProblem(error).detail ?? 'The image could not be removed.'),
    });
  }

  protected saveAlt(image: RoomTypeImage, value: string): void {
    const alt = value.trim() || null;
    if (alt === image.alt) {
      return;
    }

    this.api.updateRoomTypeImage(this.roomTypeId(), image.id, alt).subscribe({
      next: (updated) => {
        this.replace(updated);
        this.status.set('Description saved.');
      },
      error: (error: unknown) => this.error.set(toProblem(error).detail ?? 'The description could not be saved.'),
    });
  }

  protected dropped(event: CdkDragDrop<RoomTypeImage[]>): void {
    if (event.previousIndex !== event.currentIndex) {
      this.move(event.previousIndex, event.currentIndex);
    }
  }

  /** Keyboard alternative to drag and drop. */
  protected move(from: number, to: number): void {
    const before = this.gallery();
    if (to < 0 || to >= before.length) {
      return;
    }

    const next = [...before];
    moveItemInArray(next, from, to);
    this.gallery.set(next);
    this.busy.set(true);

    this.api.reorderRoomTypeImages(this.roomTypeId(), next.map((image) => image.id)).subscribe({
      next: (saved) => {
        this.gallery.set(saved);
        this.busy.set(false);
        this.status.set(to === 0 ? `${saved[0].file_name} is now the cover image.` : 'Order saved.');
      },
      error: (error: unknown) => {
        this.gallery.set(before);
        this.busy.set(false);
        this.error.set(toProblem(error).detail ?? 'The new order could not be saved.');
      },
    });
  }

  /** A failed upload stays in the list with its reason; the others carry on. */
  private uploadOne(key: number, file: File): Observable<unknown> {
    return this.api.uploadRoomTypeImage(this.roomTypeId(), file).pipe(
      tap(({ progress, result }) => {
        this.patchUpload(key, { progress });
        if (result) {
          this.gallery.update((list) => [...list, result]);
          this.dismissUpload(key);
          this.status.set(`Uploaded ${file.name}.`);
        }
      }),
      catchError((error: unknown) => {
        const problem = toProblem(error);
        this.patchUpload(key, { error: problem.errors?.[0]?.message ?? problem.detail ?? 'Upload failed.' });
        return EMPTY;
      }),
    );
  }

  private check(file: File): string | null {
    if (!ACCEPTED_TYPES.includes(file.type)) {
      return 'Only JPEG, PNG or WebP images can be uploaded.';
    }
    if (file.size > MAX_BYTES) {
      return 'The image may not be larger than 5 MB.';
    }
    return null;
  }

  private patchUpload(key: number, patch: Partial<PendingUpload>): void {
    this.uploads.update((list) => list.map((upload) => (upload.key === key ? { ...upload, ...patch } : upload)));
  }

  private replace(updated: RoomTypeImage): void {
    this.gallery.update((list) => list.map((image) => (image.id === updated.id ? updated : image)));
  }
}
