import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';

import { RoomTypeImage } from '../../../core/bff/types';
import { ConfirmService } from '../../../shared/ui/confirm-dialog';
import { RoomTypeGallery } from './room-type-gallery';

function image(id: string, name: string): RoomTypeImage {
  return { id, file_name: name, alt: null, size: 1000, position: 1, thumb_url: `/storage/${id}-thumb.webp`, large_url: null };
}

describe('RoomTypeGallery', () => {
  let fixture: ComponentFixture<RoomTypeGallery>;
  let http: HttpTestingController;
  let confirmAnswer: boolean;
  const base = '/bff/settings/room-types/rt1/images';

  beforeEach(() => {
    confirmAnswer = true;
    TestBed.configureTestingModule({
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        { provide: ConfirmService, useValue: { ask: () => Promise.resolve(confirmAnswer) } },
      ],
    });

    fixture = TestBed.createComponent(RoomTypeGallery);
    fixture.componentRef.setInput('roomTypeId', 'rt1');
    fixture.componentRef.setInput('images', [image('a', 'lobby.jpg'), image('b', 'bath.jpg')]);
    fixture.detectChanges();
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  const element = () => fixture.nativeElement as HTMLElement;
  const buttons = (label: string) =>
    Array.from(element().querySelectorAll<HTMLButtonElement>('button')).filter((button) =>
      (button.getAttribute('aria-label') ?? button.textContent ?? '').trim().startsWith(label),
    );

  it('shows the images with the first one marked as the cover', () => {
    const tiles = element().querySelectorAll('li[cdkdrag]');

    expect(tiles.length).toBe(2);
    expect(tiles[0].textContent).toContain('Cover');
    expect(tiles[1].textContent).not.toContain('Cover');
    expect(element().textContent).toContain('2 of 20');
  });

  it('rejects files that are not images or are too large without uploading them', () => {
    const gallery = fixture.componentInstance;

    gallery.addFiles([
      new File(['%PDF'], 'rates.pdf', { type: 'application/pdf' }),
      new File([new Uint8Array(6 * 1024 * 1024)], 'huge.jpg', { type: 'image/jpeg' }),
    ]);
    fixture.detectChanges();

    http.expectNone(base);
    expect(element().textContent).toContain('Only JPEG, PNG or WebP images can be uploaded.');
    expect(element().textContent).toContain('The image may not be larger than 5 MB.');
  });

  it('uploads accepted files as multipart and adds them to the gallery', () => {
    fixture.componentInstance.addFiles([new File(['jpeg'], 'pool.jpg', { type: 'image/jpeg' })]);

    const request = http.expectOne(base);
    expect(request.request.method).toBe('POST');
    expect((request.request.body as FormData).get('image')).toEqual(jasmine.any(File));

    request.flush({ data: image('c', 'pool.jpg') });
    fixture.detectChanges();

    expect(element().querySelectorAll('li[cdkdrag]').length).toBe(3);
  });

  it('uploads one file at a time so the gallery keeps the picked order', () => {
    fixture.componentInstance.addFiles([
      new File(['a big photo'], 'first.jpg', { type: 'image/jpeg' }),
      new File(['small'], 'second.png', { type: 'image/png' }),
    ]);

    const first = http.expectOne(base);
    expect((first.request.body as FormData).get('image')).toEqual(jasmine.objectContaining({ name: 'first.jpg' }));
    first.flush({ data: image('c', 'first.jpg') });

    const second = http.expectOne(base);
    expect((second.request.body as FormData).get('image')).toEqual(jasmine.objectContaining({ name: 'second.png' }));
    second.flush({ data: image('d', 'second.png') });
    fixture.detectChanges();

    const names = Array.from(element().querySelectorAll('li[cdkdrag] p')).map((p) => p.textContent?.trim());
    expect(names).toEqual(['lobby.jpg', 'bath.jpg', 'first.jpg', 'second.png']);
  });

  it('shows the server reason when an upload is refused', () => {
    fixture.componentInstance.addFiles([new File(['jpeg'], 'tiny.jpg', { type: 'image/jpeg' })]);

    http.expectOne(base).flush(
      {
        type: 'about:blank',
        title: 'Validation failed',
        status: 422,
        code: 'validation_failed',
        errors: [{ field: 'image', message: 'The image must be at least 320 × 240 pixels.' }],
      },
      { status: 422, statusText: 'Unprocessable Content' },
    );
    fixture.detectChanges();

    expect(element().textContent).toContain('The image must be at least 320 × 240 pixels.');
    expect(element().querySelectorAll('li[cdkdrag]').length).toBe(2);
  });

  it('saves a new order with the keyboard buttons, making an image the cover', () => {
    buttons('Make cover')[0].click();

    const request = http.expectOne(`${base}/order`);
    expect(request.request.method).toBe('PUT');
    expect(request.request.body).toEqual({ ids: ['b', 'a'] });

    request.flush({ data: [image('b', 'bath.jpg'), image('a', 'lobby.jpg')] });
    fixture.detectChanges();

    expect(element().querySelector('li[cdkdrag]')?.textContent).toContain('bath.jpg');
  });

  it('puts the old order back if saving it fails', () => {
    buttons('Move lobby.jpg later')[0].click();
    http.expectOne(`${base}/order`).flush({ title: 'Server error' }, { status: 500, statusText: 'Server Error' });
    fixture.detectChanges();

    expect(element().querySelector('li[cdkdrag]')?.textContent).toContain('lobby.jpg');
    expect(element().querySelector('[role=alert]')).not.toBeNull();
  });

  it('removes an image only after confirmation', async () => {
    confirmAnswer = false;
    buttons('Remove')[0].click();
    await fixture.whenStable();
    http.expectNone(`${base}/a`);

    confirmAnswer = true;
    buttons('Remove')[0].click();
    await fixture.whenStable();
    const request = http.expectOne(`${base}/a`);
    expect(request.request.method).toBe('DELETE');
    request.flush(null, { status: 204, statusText: 'No Content' });
    fixture.detectChanges();

    expect(element().querySelectorAll('li[cdkdrag]').length).toBe(1);
  });

  it('saves a changed description when the field loses focus', () => {
    const field = element().querySelector<HTMLInputElement>('#alt-a')!;
    field.value = 'Lobby with fireplace';
    field.dispatchEvent(new Event('blur'));

    const request = http.expectOne(`${base}/a`);
    expect(request.request.method).toBe('PATCH');
    expect(request.request.body).toEqual({ alt: 'Lobby with fireplace' });
    request.flush({ data: { ...image('a', 'lobby.jpg'), alt: 'Lobby with fireplace' } });

    // Unchanged on the next blur: nothing is sent.
    fixture.detectChanges();
    field.dispatchEvent(new Event('blur'));
  });
});
