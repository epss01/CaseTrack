<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivateFileServingTest extends TestCase
{
    public function test_private_files_cannot_be_served_or_issued_temporary_urls(): void
    {
        $this->assertFalse(Route::has('storage.local'));
        $this->assertFalse(Storage::disk('local')->providesTemporaryUrls());

        $disk = Storage::fake('local');
        $path = 'imports/synthetic-private.csv';
        $disk->put($path, 'Synthetic confidential import');

        $this->get('/storage/'.$path)
            ->assertNotFound()
            ->assertDontSee('Synthetic confidential import');
        $this->head('/storage/'.$path)->assertNotFound();

        $disk->assertExists($path);
    }
}
