<?php

use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(LazilyRefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    $this->student = Student::factory()->create();
    Storage::fake('r2');
});

it('uploads a profile photo successfully', function () {
    $photo = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(100);

    $response = $this->withToken($this->token)
        ->postJson("/api/students/{$this->student->id}/photo", [
            'photo' => $photo,
        ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => ['id', 'photo_url']]);

    $this->student->refresh();
    expect($this->student->photo_url)->not->toBeNull();

    Storage::disk('r2')->assertExists($this->student->photo_url);
});

it('replaces an existing photo on re-upload', function () {
    $this->student->uploadPhoto(UploadedFile::fake()->image('old.jpg', 200, 200)->size(100));
    $oldPath = $this->student->fresh()->photo_url;

    $newPhoto = UploadedFile::fake()->image('new.jpg', 200, 200)->size(100);

    $response = $this->withToken($this->token)
        ->postJson("/api/students/{$this->student->id}/photo", [
            'photo' => $newPhoto,
        ]);

    $response->assertOk();

    $this->student->refresh();
    expect($this->student->photo_url)->not->toBe($oldPath);
    expect($this->student->photo_url)->not->toBeNull();

    Storage::disk('r2')->assertMissing($oldPath);
    Storage::disk('r2')->assertExists($this->student->photo_url);
});

it('deletes a profile photo', function () {
    $this->student->uploadPhoto(UploadedFile::fake()->image('photo.jpg', 200, 200)->size(100));
    $photoPath = $this->student->fresh()->photo_url;

    $response = $this->withToken($this->token)
        ->deleteJson("/api/students/{$this->student->id}/photo");

    $response->assertOk()
        ->assertJson(['message' => 'Profile photo deleted successfully.']);

    $this->student->refresh();
    expect($this->student->photo_url)->toBeNull();

    Storage::disk('r2')->assertMissing($photoPath);
});

it('rejects file larger than 2mb', function () {
    $photo = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(3000);

    $response = $this->withToken($this->token)
        ->postJson("/api/students/{$this->student->id}/photo", [
            'photo' => $photo,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('rejects non-image file type', function () {
    $file = UploadedFile::fake()->createWithContent('test.txt', 'not an image');

    $response = $this->withToken($this->token)
        ->postJson("/api/students/{$this->student->id}/photo", [
            'photo' => $file,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('rejects unsupported image format (gif)', function () {
    // Create a file with .gif extension — mimes rule rejects it
    $photo = UploadedFile::fake()->create('photo.gif', 100, 'image/gif');

    $response = $this->withToken($this->token)
        ->postJson("/api/students/{$this->student->id}/photo", [
            'photo' => $photo,
        ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors('photo');
});

it('returns 404 for non-existent student', function () {
    $photo = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(100);

    $response = $this->withToken($this->token)
        ->postJson('/api/students/99999/photo', [
            'photo' => $photo,
        ]);

    $response->assertNotFound();
});

it('requires authentication', function () {
    $photo = UploadedFile::fake()->image('photo.jpg', 200, 200)->size(100);

    $response = $this->postJson("/api/students/{$this->student->id}/photo", [
        'photo' => $photo,
    ]);

    $response->assertStatus(401);
});
