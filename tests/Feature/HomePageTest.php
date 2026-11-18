<?php

namespace Tests\Feature;

use Database\Seeders\LevelSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_lists_rooms_and_links_to_the_game(): void
    {
        $this->seed(LevelSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSeeText('12 connected rooms')
            ->assertSee('War Room')
            ->assertSee(route('game'))
            ->assertSee('images/screens/room-5.jpg')
            ->assertDontSee('id="game"', false);
    }
}
