<?php

namespace Tests\Feature;

use App\User;
use App\Vote;
use App\Play;
use App\Track;
use App\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class VoteTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function testUserRelationShip()
    {
        $user = factory(User::class)->create();
	    $room = $user->ownedRooms()->create(factory(Room::class)->make()->toArray());
	    $track = factory(Track::class)->create();
        $play = factory(Play::class)->make(['track_id' => $track->id, 'added_by_user_id' => $user->id, 'room_id' => $room, 'played_at' => \Carbon\Carbon::now()]);
        $play->save();

	    $vote = factory(Vote::class)->create(['user_id' => $user->id, 'play_id' => $play->id]);
        

        $this->assertEquals($user->id, $vote->user->id);
        $this->assertEquals($play->id, $vote->play->id);
        $this->assertnotNull($user->votes()->find($vote->id));
        $this->assertnotNull($play->votes()->find($vote->id));
    }
}
