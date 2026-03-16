<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScheduleItem;
use App\Models\ClassType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BookingApiTest extends TestCase
{
    use RefreshDatabase; // Очищает БД перед каждым тестом

    protected User $admin;
    protected User $client;
    protected ClassType $classType;
    protected ScheduleItem $scheduleItem;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Создаем тип занятия (это отсутствовало ранее!)
        $this->classType = ClassType::create([
            'name' => 'Yoga',
            'description' => 'Relax',
            'default_capacity' => 10,
        ]);

        // 2. Создаем пользователей
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'password' => Hash::make('password'),
        ]);
        $this->client = User::factory()->create([
            'role' => 'client',
            'password' => Hash::make('password'),
        ]);

        // 3. Создаем занятие, привязывая его к созданному типу
        $this->scheduleItem = ScheduleItem::create([
            'class_type_id' => $this->classType->id, // Используем ID созданного выше типа
            'start_time' => now()->addDays(2),
            'end_time' => now()->addDays(2)->addHour(),
            'capacity' => 2,
            'booked_count' => 0,
        ]);
    }

    /** @test */
    public function protected_route_requires_token()
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    /** @test */
    public function protected_route_allows_valid_token()
    {
        $headers = $this->authHeaders($this->client);
        $this->getJson('/api/me', $headers)->assertStatus(200);
    }

    /** @test */
    public function client_cannot_create_schedule_item()
    {
        $response = $this->postJson('/api/admin/schedule', [
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(5),
                'end_time' => now()->addDays(5)->addHour(),
                'capacity' => 10,
            ], $this->authHeaders($this->client));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_schedule_item()
    {
        $response = $this->postJson('/api/admin/schedule', [
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(5),
                'end_time' => now()->addDays(5)->addHour(),
                'capacity' => 10,
            ], $this->authHeaders($this->admin));

        $response->assertStatus(201);
    }

    /** @test */
    public function client_can_book_a_class()
    {
        $response = $this->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ], $this->authHeaders($this->client));

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Вы успешно записаны!']);
    }

    /** @test */
    public function client_cannot_book_if_no_seats_left()
    {
        // Занимаем все места вручную
        $this->scheduleItem->update(['booked_count' => 2]);

        $response = $this->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ], $this->authHeaders($this->client));

        $response->assertStatus(422)
                 ->assertJson(['message' => 'Мест нет']);
    }

    /** @test */
    public function client_cannot_book_if_already_booked()
    {
        // Создаем бронь для клиента
        $this->scheduleItem->bookings()->create([
            'user_id' => $this->client->id,
            'status' => 'confirmed',
        ]);

        $response = $this->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ], $this->authHeaders($this->client));

        $response->assertStatus(422)
                 ->assertJson(['message' => 'Вы уже записаны на это занятие']);
    }

    /** @test */
    public function client_cannot_cancel_others_booking()
    {
        // Создаем бронь для админа
        $otherBooking = $this->scheduleItem->bookings()->create([
            'user_id' => $this->admin->id,
            'status' => 'confirmed',
        ]);

        // Клиент пытается отменить чужую бронь
        $response = $this->deleteJson(
            "/api/bookings/{$otherBooking->id}",
            [],
            $this->authHeaders($this->client)
        );

        $response->assertStatus(403);
    }


        #[Test]
    public function admin_can_update_schedule_item()
    {
        $response = $this->putJson("/api/admin/schedule/{$this->scheduleItem->id}", [
                'capacity' => 20,
                'start_time' => now()->addDays(3),
            ], $this->authHeaders($this->admin));
    
            $response->assertStatus(200)
                ->assertJson(['message' => 'Занятие обновлено']);
        }
    
        #[Test]
    public function client_cannot_update_schedule_item()
    {
        $response = $this->putJson(
            "/api/admin/schedule/{$this->scheduleItem->id}",
            ['capacity' => 20],
            $this->authHeaders($this->client)
        );
    
            $response->assertStatus(403);
        }
    
        #[Test]
    public function admin_can_delete_schedule_item()
    {
        $response = $this->deleteJson(
            "/api/admin/schedule/{$this->scheduleItem->id}",
            [],
            $this->authHeaders($this->admin)
        );
    
            $response->assertStatus(200)
                ->assertJson(['message' => 'Занятие удалено']);
        }
    
        #[Test]
    public function client_can_cancel_own_booking()
    {
        $booking = $this->scheduleItem->bookings()->create([
            'user_id' => $this->client->id,
            'status' => 'confirmed',
        ]);

        $response = $this->deleteJson(
            "/api/bookings/{$booking->id}",
            [],
            $this->authHeaders($this->client)
        );
    
            $response->assertStatus(200)
                ->assertJson(['message' => 'Бронь отменена']);
        }
    
        #[Test]
    public function client_can_view_own_bookings()
    {
        $this->scheduleItem->bookings()->create([
            'user_id' => $this->client->id,
            'status' => 'confirmed',
        ]);

        $response = $this->getJson('/api/my-bookings', $this->authHeaders($this->client));
    
            $response->assertStatus(200)
                ->assertJsonFragment(['schedule_item_id' => $this->scheduleItem->id]);
        }
    
        #[Test]
    public function admin_can_view_all_bookings()
    {
            $this->scheduleItem->bookings()->create([
                'user_id' => $this->client->id,
                'status' => 'confirmed',
            ]);
    
        $response = $this->getJson('/api/admin/bookings', $this->authHeaders($this->admin));
    
            $response->assertStatus(200)
                ->assertJsonFragment(['email' => $this->client->email]);
        }

        #[Test]
    public function client_cannot_book_if_time_conflicts()
    {
            // Создаём первое занятие: 10:00 - 11:00
            $firstSchedule = ScheduleItem::create([
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(2)->setTime(10, 0),
                'end_time' => now()->addDays(2)->setTime(11, 0),
                'capacity' => 10,
                'booked_count' => 0,
            ]);
    
            // Создаём второе занятие: 10:30 - 11:30 (пересекается с первым)
            $conflictSchedule = ScheduleItem::create([
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(2)->setTime(10, 30),
                'end_time' => now()->addDays(2)->setTime(11, 30),
                'capacity' => 10,
                'booked_count' => 0,
            ]);
    
            // Клиент записывается на первое занятие
        $this->postJson(
            '/api/bookings',
            ['schedule_item_id' => $firstSchedule->id],
            $this->authHeaders($this->client)
        )->assertStatus(201);
    
            // Попытка записаться на пересекающееся время -> отказ
        $response = $this->postJson(
            '/api/bookings',
            ['schedule_item_id' => $conflictSchedule->id],
            $this->authHeaders($this->client)
        );
    
            $response->assertStatus(422)
                     ->assertJson(['message' => 'У вас есть запись на другое занятие в это время']);
        }

    private function authHeaders(User $user): array
    {
        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $token = $response->json('access_token');

        return ['Authorization' => "Bearer {$token}"];
    }
}
