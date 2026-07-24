<?php

namespace Tests\Feature;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_location_code_and_internal_type_are_generated_automatically(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('locations.store'), [
            'name' => 'Main Store',
            'parent_id' => null,
            'active' => true,
            'code' => 'MANUAL-CODE',
            'type' => LocationType::Scrap->value,
        ]);

        $response->assertRedirect();

        $location = Location::query()->where('name', 'Main Store')->firstOrFail();

        $this->assertMatchesRegularExpression('/^LOC-\d{3,}$/', $location->code);
        $this->assertNotSame('MANUAL-CODE', $location->code);
        $this->assertSame(LocationType::Yard, $location->type);
    }

    public function test_updating_a_location_keeps_its_generated_code_and_internal_type(): void
    {
        $user = User::factory()->create();
        $location = Location::query()->create([
            'code' => 'LOC-007',
            'name' => 'Old Name',
            'type' => LocationType::Rack,
            'active' => true,
        ]);

        $response = $this->actingAs($user)->put(route('locations.update', $location), [
            'name' => 'New Name',
            'parent_id' => null,
            'active' => false,
            'code' => 'CHANGED',
            'type' => LocationType::Scrap->value,
        ]);

        $response->assertRedirect();

        $location->refresh();

        $this->assertSame('LOC-007', $location->code);
        $this->assertSame(LocationType::Rack, $location->type);
        $this->assertSame('New Name', $location->name);
        $this->assertFalse($location->active);
    }
}
