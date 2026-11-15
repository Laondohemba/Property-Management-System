<?php

namespace Tests\Feature\Inventory;

use App\Modules\Audit\Models\AuditEntry;
use App\Modules\Identity\Enums\Role;
use App\Modules\Inventory\Models\RoomType;
use App\Modules\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Feature\Property\Concerns\CreatesPropertyUsers;
use Tests\TestCase;

/**
 * Room-type gallery (SYS-04): files on the media disk (ARC-08), validated uploads (spec §13),
 * signed URLs only, ordering, and an audit entry for every change (AUD-01).
 */
class RoomTypeImagesTest extends TestCase
{
    use CreatesPropertyUsers, RefreshDatabase;

    private Property $hotel;

    private RoomType $deluxe;

    private string $mediaRoot;

    protected function setUp(): void
    {
        parent::setUp();

        // The real private local disk (not Storage::fake), in a throwaway folder, so signed
        // URLs are generated and verified exactly as in development.
        $this->mediaRoot = storage_path('framework/testing/media-'.Str::lower(Str::random(10)));
        config(['filesystems.disks.local.root' => $this->mediaRoot]);
        Storage::forgetDisk('local');

        $this->setUpOrganization();
        $this->hotel = $this->property();
        $this->deluxe = RoomType::factory()->for($this->hotel)->create(['code' => 'DLX']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->mediaRoot);
        parent::tearDown();
    }

    public function test_an_image_is_stored_with_a_thumbnail_and_a_full_size_version(): void
    {
        $response = $this->upload(UploadedFile::fake()->image('Sea view.JPG', 1600, 1000), ['alt' => '  King bed facing the sea  '])
            ->assertCreated()
            ->assertJsonPath('data.file_name', 'Sea view.JPG')
            ->assertJsonPath('data.alt', 'King bed facing the sea')
            ->assertJsonPath('data.position', 1);

        $media = Media::query()->sole();
        $this->assertSame('local', $media->disk);
        // Stored under a random name, never the uploader's.
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}\.jpg$/', $media->file_name);
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot());
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot('thumb'));
        Storage::disk('local')->assertExists($media->getPathRelativeToRoot('large'));

        // Signed, expiring URLs for the conversions only; the original is never linked.
        $thumb = (string) $response->json('data.thumb_url');
        $this->assertStringContainsString('signature=', $thumb);
        $this->assertStringContainsString('expires=', $thumb);
        $this->assertStringEndsWith('.webp', parse_url($thumb, PHP_URL_PATH) ?: '');

        // The full-size version is built by the queue after the upload, so the upload response
        // cannot carry it yet; once built, the room type returns it.
        $this->assertNull($response->json('data.large_url'));
        $large = $this->asManager()->getJson($this->url())->json('data.images.0.large_url');
        $this->assertStringContainsString('-large.webp', (string) $large);
    }

    public function test_signed_urls_serve_the_image_and_tampered_ones_are_refused(): void
    {
        $thumb = (string) $this->upload(UploadedFile::fake()->image('a.jpg', 800, 600))->json('data.thumb_url');
        $path = (string) parse_url($thumb, PHP_URL_PATH).'?'.parse_url($thumb, PHP_URL_QUERY);

        // No session needed: the signature is the permission, and it expires.
        $this->app['auth']->forgetGuards();
        $this->get($path)->assertOk()->assertHeader('Content-Type', 'image/webp');

        $this->get(str_replace('-thumb.webp', '-large.webp', $path))->assertForbidden();
        $this->get(preg_replace('/expires=\d+/', 'expires='.(time() + 86400), $path))->assertForbidden();
        $this->travel(61)->minutes();
        $this->get($path)->assertForbidden();
    }

    public function test_the_room_type_lists_its_images_in_order(): void
    {
        $first = $this->upload(UploadedFile::fake()->image('a.jpg', 800, 600))->json('data.id');
        $second = $this->upload(UploadedFile::fake()->image('b.png', 800, 600))->json('data.id');

        $this->asManager()->getJson("/bff/settings/room-types/{$this->deluxe->public_id}")
            ->assertOk()
            ->assertJsonPath('data.images.0.id', $first)
            ->assertJsonPath('data.images.1.id', $second);

        $this->asManager()->getJson('/bff/settings/room-types')
            ->assertJsonCount(2, 'data.0.images');
    }

    public function test_non_images_and_disguised_files_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('rates.pdf', 50, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.field', 'image');

        // A script renamed to .jpg: the content is sniffed, not the extension.
        $this->upload(UploadedFile::fake()->createWithContent('photo.jpg', '<script>alert(1)</script>'))
            ->assertUnprocessable();

        $this->upload(UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>'))
            ->assertUnprocessable();

        $this->assertSame(0, Media::query()->count());
    }

    public function test_images_must_be_large_enough_and_at_most_5_mb(): void
    {
        $this->upload(UploadedFile::fake()->image('tiny.jpg', 200, 150))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.message', 'The image must be at least 320 × 240 pixels and at most 10,000 pixels on each side.');

        $this->upload(UploadedFile::fake()->image('huge.jpg', 1200, 800)->size(6 * 1024))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.message', 'The image may not be larger than 5 MB.');
    }

    public function test_a_room_type_holds_at_most_twenty_images(): void
    {
        foreach (range(1, RoomType::MAX_IMAGES) as $i) {
            $this->deluxe->addMedia(UploadedFile::fake()->image("{$i}.jpg", 400, 300))->toMediaCollection(RoomType::IMAGES);
        }

        $this->upload(UploadedFile::fake()->image('one-more.jpg', 800, 600))
            ->assertUnprocessable()
            ->assertJsonPath('errors.0.message', 'A room type can have at most 20 images. Remove one first.');
    }

    public function test_images_can_be_reordered_and_the_first_becomes_the_cover(): void
    {
        $ids = collect(['a', 'b', 'c'])->map(fn (string $name) => $this->upload(UploadedFile::fake()->image("{$name}.jpg", 800, 600))->json('data.id'))->all();
        $newOrder = [$ids[2], $ids[0], $ids[1]];

        $this->asManager()->putJson($this->url('images/order'), ['ids' => $newOrder])
            ->assertOk()
            ->assertJsonPath('data.0.id', $ids[2])
            ->assertJsonPath('data.0.position', 1)
            ->assertJsonPath('data.2.id', $ids[1]);

        $this->asManager()->getJson($this->url())->assertJsonPath('data.images.0.id', $ids[2]);
    }

    public function test_reordering_must_list_every_image_exactly_once(): void
    {
        $ids = collect(['a', 'b'])->map(fn (string $name) => $this->upload(UploadedFile::fake()->image("{$name}.jpg", 800, 600))->json('data.id'))->all();

        $this->asManager()->putJson($this->url('images/order'), ['ids' => [$ids[0]]])
            ->assertUnprocessable()->assertJsonPath('errors.0.field', 'ids');

        $this->asManager()->putJson($this->url('images/order'), ['ids' => [$ids[0], $ids[0]]])
            ->assertUnprocessable();
    }

    public function test_the_description_can_be_changed_and_cleared(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.jpg', 800, 600), ['alt' => 'Old'])->json('data.id');

        $this->asManager()->patchJson($this->url("images/{$id}"), ['alt' => 'Twin beds, garden view'])
            ->assertOk()->assertJsonPath('data.alt', 'Twin beds, garden view');

        $this->asManager()->patchJson($this->url("images/{$id}"), ['alt' => ''])
            ->assertOk()->assertJsonPath('data.alt', null);
    }

    public function test_removing_an_image_deletes_its_files(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.jpg', 800, 600))->json('data.id');
        $media = Media::query()->sole();
        $paths = [$media->getPathRelativeToRoot(), $media->getPathRelativeToRoot('thumb'), $media->getPathRelativeToRoot('large')];

        $this->asManager()->deleteJson($this->url("images/{$id}"))->assertNoContent();

        $this->assertSame(0, Media::query()->count());
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_every_change_is_in_the_room_types_history(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('Lobby.jpg', 800, 600))->json('data.id');
        $this->asManager()->patchJson($this->url("images/{$id}"), ['alt' => 'Lobby']);
        $this->asManager()->deleteJson($this->url("images/{$id}"));

        $entries = AuditEntry::query()
            ->where('subject_type', $this->deluxe->getMorphClass())
            ->where('subject_id', $this->deluxe->id)
            ->whereIn('event', ['image_added', 'image_updated', 'image_removed'])
            ->orderBy('id')
            ->get();

        $this->assertSame(['image_added', 'image_updated', 'image_removed'], $entries->pluck('event')->all());
        $this->assertSame('Room type DLX: image Lobby.jpg added', $entries[0]->description);
        $this->assertSame($this->hotel->id, $entries[0]->property_id);
        $this->assertSame(['alt' => 'Lobby'], $entries[1]->properties['attributes']);
    }

    public function test_images_of_another_propertys_room_type_cannot_be_touched(): void
    {
        $otherHotel = $this->property();
        $other = RoomType::factory()->for($otherHotel)->create();
        $media = $other->addMedia(UploadedFile::fake()->image('x.jpg', 800, 600))->toMediaCollection(RoomType::IMAGES);

        // The room type belongs to a property outside the active one.
        $this->asManager()->deleteJson("/bff/settings/room-types/{$other->public_id}/images/{$media->uuid}")->assertNotFound();

        // An image id from another room type is not found under this one.
        $this->asManager()->deleteJson($this->url("images/{$media->uuid}"))->assertNotFound();

        $this->assertSame(1, Media::query()->count());
    }

    public function test_staff_without_room_settings_permission_cannot_upload(): void
    {
        $this->actingAs($this->staff($this->hotel, Role::FrontDeskAgent))
            ->withHeader('X-Property-Id', $this->hotel->public_id)
            ->post($this->url('images'), ['image' => UploadedFile::fake()->image('a.jpg', 800, 600)], ['Accept' => 'application/json'])
            ->assertForbidden();
    }

    /**
     * @param  array<string, string>  $fields
     */
    private function upload(UploadedFile $file, array $fields = []): TestResponse
    {
        return $this->asManager()->post($this->url('images'), ['image' => $file, ...$fields], ['Accept' => 'application/json']);
    }

    private function asManager(): static
    {
        return $this->actingAs($this->staff($this->hotel, Role::PropertyManager))
            ->withHeader('X-Property-Id', $this->hotel->public_id);
    }

    private function url(string $path = ''): string
    {
        return rtrim("/bff/settings/room-types/{$this->deluxe->public_id}/{$path}", '/');
    }
}
