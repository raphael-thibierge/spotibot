<?php

namespace Tests\Feature;

use App\Play;
use App\Room;
use App\Track;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class PlayTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testPlayRelationships()
    {
        $user = factory(User::class)->create();
        $room = $user->ownedRooms()->create(factory(Room::class)->make()->toArray());
        $track = factory(Track::class)->create();
        $play = factory(Play::class)->make(['track_id' => $track->id, 'added_by_user_id' => $user->id, 'room_id' => $room, 'played_at' => \Carbon\Carbon::now()]);

        $this->assertNotNull($user);
        $this->assertNotNull($room);
        $this->assertNotNull($track);
        $this->assertNotNull($play);
        $this->assertNotNull($play->track);
        $this->assertNotNull($play->room);
        $this->assertNotNull($play->addedBy);

        $this->assertEquals($play->addedBy->id, $user->id);
        $this->assertEquals($play->track->id, $track->id);
        $this->assertEquals($play->room->id, $room->id);

    }
}
