<?php

namespace Tests\Feature;

use App\SpotifyClient;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SpotifyClientTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testUserRelationship()
    {
        $user = factory(User::class)->create();
        $spotifyClient = $user->spotifyClient()->create(
            \factory(SpotifyClient::class)->make()->toArray()
        );

        $this->assertNotNull($spotifyClient);
        $this->assertEquals($user->spotifyClient->id, $spotifyClient->id );
    }
}
