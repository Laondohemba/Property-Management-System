import { HttpClient, HttpEventType, HttpParams, HttpProgressEvent } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable, filter, map } from 'rxjs';

import {
  Amenity,
  BffEnvelope,
  Room,
  RoomInput,
  RoomListMetaData,
  RoomRangeInput,
  RoomRangeMetaData,
  RoomType,
  RoomTypeImage,
  RoomTypeInput,
  RoomTypeListMetaData,
} from '../../../core/bff/types';

export interface Page<T, M> {
  items: T[];
  meta: M;
}

/** Progress of one upload; `result` arrives with the final event. */
export interface UploadEvent<T> {
  progress: number;
  result?: T;
}

export interface RoomFilters {
  page?: number;
  roomType?: string;
  floor?: string;
  search?: string;
  active?: boolean;
}

/**
 * Data access for rooms, room types and amenities of the active property (SYS-04).
 * X-Property-Id is added by the BFF interceptor.
 */
@Injectable({ providedIn: 'root' })
export class InventoryApi {
  private readonly http = inject(HttpClient);
  private readonly base = '/bff/settings';

  // Amenities ---------------------------------------------------------------

  amenities(): Observable<Amenity[]> {
    return this.http
      .get<BffEnvelope<Amenity[]>>(`${this.base}/amenities`, { params: { per_page: 100 } })
      .pipe(map((response) => response.data));
  }

  createAmenity(name: string): Observable<Amenity> {
    return this.http.post<BffEnvelope<Amenity>>(`${this.base}/amenities`, { name }).pipe(map((r) => r.data));
  }

  renameAmenity(id: string, name: string): Observable<Amenity> {
    return this.http.patch<BffEnvelope<Amenity>>(`${this.base}/amenities/${id}`, { name }).pipe(map((r) => r.data));
  }

  deleteAmenity(id: string): Observable<void> {
    return this.http.delete<void>(`${this.base}/amenities/${id}`);
  }

  // Room types --------------------------------------------------------------

  roomTypes(options: { page?: number; perPage?: number; active?: boolean } = {}): Observable<Page<RoomType, RoomTypeListMetaData>> {
    let params = new HttpParams().set('page', options.page ?? 1).set('per_page', options.perPage ?? 100);

    if (options.active !== undefined) {
      params = params.set('filter[active]', options.active ? 1 : 0);
    }

    return this.http
      .get<BffEnvelope<RoomType[], RoomTypeListMetaData>>(`${this.base}/room-types`, { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }

  roomType(id: string): Observable<RoomType> {
    return this.http.get<BffEnvelope<RoomType>>(`${this.base}/room-types/${id}`).pipe(map((r) => r.data));
  }

  createRoomType(input: RoomTypeInput): Observable<RoomType> {
    return this.http.post<BffEnvelope<RoomType>>(`${this.base}/room-types`, input).pipe(map((r) => r.data));
  }

  updateRoomType(id: string, input: Partial<RoomTypeInput>): Observable<RoomType> {
    return this.http.patch<BffEnvelope<RoomType>>(`${this.base}/room-types/${id}`, input).pipe(map((r) => r.data));
  }

  // Room-type gallery ---------------------------------------------------------

  /** One file per request; emits upload progress (0 to 100) and finally the stored image. */
  uploadRoomTypeImage(roomTypeId: string, file: File, alt?: string): Observable<UploadEvent<RoomTypeImage>> {
    const body = new FormData();
    body.append('image', file, file.name);
    if (alt) {
      body.append('alt', alt);
    }

    return this.http
      .post<BffEnvelope<RoomTypeImage>>(`${this.base}/room-types/${roomTypeId}/images`, body, {
        reportProgress: true,
        observe: 'events',
      })
      .pipe(
        filter((event) => event.type === HttpEventType.UploadProgress || event.type === HttpEventType.Response),
        map((event): UploadEvent<RoomTypeImage> => {
          if (event.type === HttpEventType.Response) {
            return { progress: 100, result: event.body!.data };
          }
          const { loaded, total } = event as HttpProgressEvent;
          // Hold at 99 until the server has stored the file and built the thumbnail.
          return { progress: total ? Math.min(99, Math.round((loaded / total) * 100)) : 0 };
        }),
      );
  }

  updateRoomTypeImage(roomTypeId: string, imageId: string, alt: string | null): Observable<RoomTypeImage> {
    return this.http
      .patch<BffEnvelope<RoomTypeImage>>(`${this.base}/room-types/${roomTypeId}/images/${imageId}`, { alt })
      .pipe(map((r) => r.data));
  }

  /** Sends every image id in the new order; the first becomes the cover. */
  reorderRoomTypeImages(roomTypeId: string, ids: string[]): Observable<RoomTypeImage[]> {
    return this.http
      .put<BffEnvelope<RoomTypeImage[]>>(`${this.base}/room-types/${roomTypeId}/images/order`, { ids })
      .pipe(map((r) => r.data));
  }

  deleteRoomTypeImage(roomTypeId: string, imageId: string): Observable<void> {
    return this.http.delete<void>(`${this.base}/room-types/${roomTypeId}/images/${imageId}`);
  }

  // Rooms -------------------------------------------------------------------

  rooms(filters: RoomFilters = {}): Observable<Page<Room, RoomListMetaData>> {
    let params = new HttpParams().set('page', filters.page ?? 1).set('per_page', 50);

    if (filters.roomType) params = params.set('filter[room_type]', filters.roomType);
    if (filters.floor) params = params.set('filter[floor]', filters.floor);
    if (filters.search) params = params.set('filter[search]', filters.search);
    if (filters.active !== undefined) params = params.set('filter[active]', filters.active ? 1 : 0);

    return this.http
      .get<BffEnvelope<Room[], RoomListMetaData>>(`${this.base}/rooms`, { params })
      .pipe(map((response) => ({ items: response.data, meta: response.meta! })));
  }

  room(id: string): Observable<Room> {
    return this.http.get<BffEnvelope<Room>>(`${this.base}/rooms/${id}`).pipe(map((r) => r.data));
  }

  createRoom(input: RoomInput): Observable<Room> {
    return this.http.post<BffEnvelope<Room>>(`${this.base}/rooms`, input).pipe(map((r) => r.data));
  }

  createRoomRange(input: RoomRangeInput): Observable<number> {
    return this.http
      .post<BffEnvelope<Room[], RoomRangeMetaData>>(`${this.base}/rooms/range`, input)
      .pipe(map((response) => response.meta!.created));
  }

  updateRoom(id: string, input: Partial<RoomInput>): Observable<Room> {
    return this.http.patch<BffEnvelope<Room>>(`${this.base}/rooms/${id}`, input).pipe(map((r) => r.data));
  }
}
