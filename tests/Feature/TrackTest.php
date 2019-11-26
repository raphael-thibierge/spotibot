<?php

namespace Tests\Feature;

use App\Play;
use App\SpotifyClient;
use App\Track;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class TrackTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testPlayRelationship()
    {
        $this->assertTrue(true);
    }
}
