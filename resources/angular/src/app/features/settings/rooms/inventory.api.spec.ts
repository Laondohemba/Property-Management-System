import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';

import { InventoryApi } from './inventory.api';

describe('InventoryApi', () => {
  let api: InventoryApi;
  let http: HttpTestingController;

  beforeEach(() => {
    TestBed.configureTestingModule({ providers: [provideHttpClient(), provideHttpClientTesting()] });
    api = TestBed.inject(InventoryApi);
    http = TestBed.inject(HttpTestingController);
  });

  afterEach(() => http.verify());

  it('sends room filters in the BFF filter[...] format', () => {
    api.rooms({ page: 2, roomType: '01JTYPE', floor: '3', search: '30', active: false }).subscribe();

    const request = http.expectOne((req) => req.url === '/bff/settings/rooms');
    const params = request.request.params;
    expect(params.get('page')).toBe('2');
    expect(params.get('filter[room_type]')).toBe('01JTYPE');
    expect(params.get('filter[floor]')).toBe('3');
    expect(params.get('filter[search]')).toBe('30');
    expect(params.get('filter[active]')).toBe('0');
    request.flush({ data: [], meta: { page: 2, per_page: 50, total: 0, last_page: 1, floors: [] } });
  });

  it('omits empty filters', () => {
    api.rooms().subscribe();

    const params = http.expectOne((req) => req.url === '/bff/settings/rooms').request.params;
    expect(params.has('filter[room_type]')).toBeFalse();
    expect(params.has('filter[active]')).toBeFalse();
  });

  it('returns the number of rooms created by a range', () => {
    let created = 0;
    api.createRoomRange({ room_type_id: '01JTYPE', floor: '1', prefix: null, from: 101, to: 105 }).subscribe((n) => (created = n));

    const request = http.expectOne('/bff/settings/rooms/range');
    expect(request.request.method).toBe('POST');
    request.flush({ data: [], meta: { created: 5 } });

    expect(created).toBe(5);
  });
});
