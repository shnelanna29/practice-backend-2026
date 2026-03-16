<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\ScheduleItem;
use App\Models\ClassType;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);

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
    public function client_cannot_create_schedule_item()
    {
        $response = $this->actingAs($this->client)
            ->postJson('/api/admin/schedule', [
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(5),
                'end_time' => now()->addDays(5)->addHour(),
                'capacity' => 10,
            ]);

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_schedule_item()
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/admin/schedule', [
                'class_type_id' => $this->classType->id,
                'start_time' => now()->addDays(5),
                'end_time' => now()->addDays(5)->addHour(),
                'capacity' => 10,
            ]);

        $response->assertStatus(201);
    }

    /** @test */
    public function client_can_book_a_class()
    {
        $response = $this->actingAs($this->client)
            ->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ]);

        $response->assertStatus(201)
                 ->assertJson(['message' => 'Вы успешно записаны!']);
    }

    /** @test */
    public function client_cannot_book_if_no_seats_left()
    {
        // Занимаем все места вручную
        $this->scheduleItem->update(['booked_count' => 2]);

        $response = $this->actingAs($this->client)
            ->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ]);

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

        $response = $this->actingAs($this->client)
            ->postJson('/api/bookings', [
                'schedule_item_id' => $this->scheduleItem->id,
            ]);

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
        $response = $this->actingAs($this->client)
            ->deleteJson("/api/bookings/{$otherBooking->id}");

        $response->assertStatus(403);
    }


        #[Test]
        public function admin_can_update_schedule_item()
        {
            $response = $this->actingAs($this->admin)
                ->putJson("/api/admin/schedule/{$this->scheduleItem->id}", [
                    'capacity' => 20,
                    'start_time' => now()->addDays(3),
                ]);
    
            $response->assertStatus(200)
                ->assertJson(['message' => 'Занятие обновлено']);
        }
    
        #[Test]
        public function client_cannot_update_schedule_item()
        {
            $response = $this->actingAs($this->client)
                ->putJson("/api/admin/schedule/{$this->scheduleItem->id}", ['capacity' => 20]);
    
            $response->assertStatus(403);
        }
    
        #[Test]
        public function admin_can_delete_schedule_item()
        {
            $response = $this->actingAs($this->admin)
                ->deleteJson("/api/admin/schedule/{$this->scheduleItem->id}");
    
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
    
            $response = $this->actingAs($this->client)
                ->deleteJson("/api/bookings/{$booking->id}");
    
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
    
            $response = $this->actingAs($this->client)
                ->getJson('/api/my-bookings');
    
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
    
            $response = $this->actingAs($this->admin)
                ->getJson('/api/admin/bookings');
    
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
            $this->actingAs($this->client)
                ->postJson('/api/bookings', ['schedule_item_id' => $firstSchedule->id])
                ->assertStatus(201);
    
            // Попытка записаться на пересекающееся время -> отказ
            $response = $this->actingAs($this->client)
                ->postJson('/api/bookings', ['schedule_item_id' => $conflictSchedule->id]);
    
            $response->assertStatus(422)
                     ->assertJson(['message' => 'У вас есть запись на другое занятие в это время']);
        }

        
}
