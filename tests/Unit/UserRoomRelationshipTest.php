<?php

namespace Tests\Unit;

use App\Room;
use App\User;
use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserRoomRelationshipTest extends TestCase
{
    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testOwnership()
    {
        $user = factory(User::class)->create();
        $this->assertEmpty($user->ownedRooms, 'user have no owned rooms by default');

        $room = $user->ownedRooms()->create(
            \factory(Room::class)->make()->toArray()
        );

        $this->assertNotNull($room);
        $this->assertNotNull($user->ownedRooms);
        $this->assertNotNull($room->owner);

        $this->assertEquals($room->id, $user->ownedRooms()->first()->id);
        $this->assertEquals($user->id, $room->owner->id);

    }

    /**
     * A basic unit test example.
     *
     * @return void
     */
    public function testMemberRelationship()
    {
        $user = factory(User::class)->create();
        $room = $user->ownedRooms()->create(
            \factory(Room::class)->make()->toArray()
        );

        $this->assertEquals(0, $room->members()->count());
        $user->rooms()->attach($room);
        $this->assertEquals(1, $user->rooms()->count());
        $this->assertEquals(1, $room->members()->count());
        $user->rooms()->detach($room);
        $room->members()->attach($user);
        $this->assertEquals(1, $room->members()->count());
        $this->assertEquals(1, $user->rooms()->count());

        $this->assertNotNull($user->ownedRooms);
        $this->assertNotNull($room->owner);

        $this->assertEquals($room->id, $user->ownedRooms()->first()->id);
        $this->assertEquals($user->id, $room->owner->id);

    }
}
