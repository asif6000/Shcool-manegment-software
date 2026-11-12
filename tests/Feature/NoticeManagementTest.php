<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\School;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\SchoolContext;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSchoolData;
use Tests\TestCase;

class NoticeManagementTest extends TestCase
{
    use CreatesSchoolData;
    use RefreshDatabase;

    private School $school;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);

        $this->school = $this->makeSchool(['name' => 'Green Valley Model School']);
        $this->useSchoolContext($this->school->id);
        $this->admin = $this->makeUser($this->school, 'school_admin');
    }

    public function test_a_draft_notice_is_hidden_until_it_is_published(): void
    {
        $this->actingAs($this->admin)
            ->post(route('notices.store'), $this->noticePayload(['title' => 'Eid holiday']))
            ->assertRedirect();

        $notice = Notice::query()->firstOrFail();
        $this->assertNull($notice->published_at);

        $this->signOutFromWorkspace();
        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('Eid holiday');
        $this->get('/')->assertOk()->assertDontSee('Eid holiday');
        $this->getJson(route('public.live'))->assertOk()->assertDontSee('Eid holiday');

        $this->actingAs($this->admin)->post(route('notices.publish', $notice))->assertRedirect();

        $this->assertNotNull($notice->refresh()->published_at);

        $this->signOutFromWorkspace();
        $this->get(route('public.notices.index'))->assertOk()->assertSee('Eid holiday');
        $this->get(route('public.notices.show', $notice))->assertOk()->assertSee('Eid holiday');
        $this->getJson(route('public.live'))->assertOk()->assertSee('Eid holiday');
        $this->get('/')->assertOk()->assertSee('Eid holiday');
    }

    public function test_the_live_endpoint_only_exposes_public_published_notices(): void
    {
        Notice::query()->create([
            'title' => 'Term test routine',
            'body' => 'Tests start next week.',
            'audience' => 'all',
            'published_at' => now()->subMinute(),
        ]);

        Notice::query()->create([
            'title' => 'Staff meeting memo',
            'body' => 'Staff only memo.',
            'audience' => 'staff',
            'published_at' => now()->subMinute(),
        ]);

        Notice::query()->create([
            'title' => 'Closed for puja break',
            'body' => 'Already expired.',
            'audience' => 'all',
            'published_at' => now()->subDays(3),
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('public.live'))
            ->assertOk()
            ->assertJsonStructure(['stats' => ['students', 'teachers', 'classes', 'subjects'], 'notices', 'server_time']);

        $this->assertSame(
            ['Term test routine'],
            collect($response->json('notices'))->pluck('title')->all(),
        );
        $this->assertSame(0, $response->json('stats.students'));
    }

    public function test_a_teacher_can_read_notices_but_cannot_change_them(): void
    {
        $teacher = $this->makeUser($this->school, 'teacher');

        $this->actingAs($teacher)->get(route('notices.index'))->assertOk();

        $this->actingAs($teacher)
            ->post(route('notices.store'), $this->noticePayload())
            ->assertForbidden();

        $this->assertSame(0, Notice::query()->count());
    }

    public function test_a_notice_for_teachers_never_reaches_the_public_website(): void
    {
        $notice = Notice::query()->create([
            'title' => 'Checked answer scripts',
            'body' => 'Internal instruction for teachers.',
            'audience' => 'teachers',
            'published_at' => now()->subHour(),
        ]);

        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('Checked answer scripts');
        $this->get('/')->assertOk()->assertDontSee('Checked answer scripts');
        $this->get(route('public.notices.show', $notice))->assertNotFound();

        $this->actingAs($this->admin)
            ->get(route('notices.index'))
            ->assertOk()
            ->assertSee('Checked answer scripts')
            ->assertSee('Teachers')
            ->assertDontSee('Visible on website');
    }

    public function test_publishing_an_internal_notice_links_to_the_staff_list_not_a_public_page(): void
    {
        $teacherUser = $this->makeUser($this->school, 'teacher');
        Teacher::factory()->create(['user_id' => $teacherUser->id]);

        $this->actingAs($this->admin)
            ->post(route('notices.store'), $this->noticePayload([
                'title' => 'Invigilation duty roster',
                'audience' => 'teachers',
                'published_at' => now()->format('Y-m-d\TH:i'),
                'notify_audience' => '1',
            ]))
            ->assertRedirect();

        $notice = Notice::query()->firstOrFail();
        $record = $teacherUser->notifications()->first();

        $this->assertNotNull($record);
        $this->assertSame(route('notices.index'), $record->data['url']);
        $this->assertNotSame(route('public.notices.show', $notice), $record->data['url']);

        // The link the teacher is handed must actually open.
        $this->get($record->data['url'])->assertOk();
        $this->get(route('public.notices.show', $notice))->assertNotFound();
    }

    public function test_publishing_with_notification_reaches_linked_students_once(): void
    {
        $studentUser = $this->makeUser($this->school, 'student');
        $student = Student::factory()->create();
        $student->forceFill(['user_id' => $studentUser->id])->save();

        $this->actingAs($this->admin)
            ->post(route('notices.store'), $this->noticePayload([
                'title' => 'Class 9 assignment drop',
                'published_at' => now()->format('Y-m-d\TH:i'),
                'notify_audience' => '1',
            ]))
            ->assertRedirect();

        $notice = Notice::query()->firstOrFail();

        $this->assertSame(1, $studentUser->notifications()->count());

        $record = $studentUser->notifications()->first();
        $this->assertSame('notices', $record->category);
        $this->assertSame(route('public.notices.show', $notice), $record->data['url']);
        $this->assertStringContainsString('Class 9 assignment drop', $record->data['title']);

        // The publisher must not receive a copy of their own notice.
        $this->assertSame(0, $this->admin->notifications()->count());

        // Re-publishing must not spam the same audience again.
        $this->actingAs($this->admin)->post(route('notices.publish', $notice))->assertRedirect();
        $this->actingAs($this->admin)->post(route('notices.publish', $notice))->assertRedirect();

        $this->assertSame(1, $studentUser->notifications()->count());
    }

    public function test_unpublishing_and_deleting_remove_the_notice_from_the_website(): void
    {
        $notice = Notice::query()->create([
            'title' => 'Sports day notice',
            'body' => 'Annual sports day.',
            'audience' => 'all',
            'published_at' => now()->subHour(),
        ]);

        $this->assertWebsiteShows('Sports day notice');

        $this->actingAs($this->admin)->post(route('notices.unpublish', $notice))->assertRedirect();
        $this->assertNull($notice->refresh()->published_at);
        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('Sports day notice');

        $this->actingAs($this->admin)->delete(route('notices.destroy', $notice))->assertRedirect();
        $this->assertDatabaseMissing('notices', ['id' => $notice->id]);
        $this->get(route('public.notices.show', $notice))->assertNotFound();
    }

    public function test_scheduling_a_notice_keeps_it_off_the_website_until_the_moment_passes(): void
    {
        $this->actingAs($this->admin)
            ->post(route('notices.store'), $this->noticePayload([
                'title' => 'Result published tomorrow',
                'published_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ]))
            ->assertRedirect();

        $notice = Notice::query()->firstOrFail();
        $this->assertTrue($notice->published_at->isFuture());

        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('Result published tomorrow');
        $this->getJson(route('public.live'))->assertOk()->assertDontSee('Result published tomorrow');
    }

    public function test_a_notice_from_another_school_is_neither_editable_nor_public(): void
    {
        $otherSchool = $this->makeSchool(['name' => 'River View High School']);
        $otherNotice = null;

        app(SchoolContext::class)->run($otherSchool->id, function () use (&$otherNotice): void {
            $otherNotice = Notice::query()->create([
                'title' => 'River View fee notice',
                'body' => 'Belongs to a different workspace.',
                'audience' => 'all',
                'published_at' => now()->subMinute(),
            ]);
        });

        $this->actingAs($this->admin)
            ->get(route('notices.edit', $otherNotice))
            ->assertNotFound();

        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('River View fee notice');
    }

    public function test_the_body_is_required_and_the_expiry_must_follow_the_publish_time(): void
    {
        $this->actingAs($this->admin)
            ->from(route('notices.create'))
            ->post(route('notices.store'), $this->noticePayload(['body' => '']))
            ->assertRedirect(route('notices.create'))
            ->assertSessionHasErrors('body');

        $this->actingAs($this->admin)
            ->from(route('notices.create'))
            ->post(route('notices.store'), $this->noticePayload([
                'published_at' => now()->format('Y-m-d\TH:i'),
                'expires_at' => now()->subHour()->format('Y-m-d\TH:i'),
            ]))
            ->assertSessionHasErrors('expires_at');

        $this->assertSame(0, Notice::query()->count());
    }

    public function test_the_admin_screens_only_claim_website_visibility_for_live_notices(): void
    {
        $draft = Notice::query()->create([
            'title' => 'Draft website notice',
            'body' => 'Saved but never published.',
            'audience' => 'all',
        ]);

        $live = Notice::query()->create([
            'title' => 'Live website notice',
            'body' => 'Visitors can read this.',
            'audience' => 'all',
            'published_at' => now()->subHour(),
        ]);

        $expired = Notice::query()->create([
            'title' => 'Expired website notice',
            'body' => 'Its deadline has passed.',
            'audience' => 'all',
            'published_at' => now()->subDays(2),
            'expires_at' => now()->subDay(),
        ]);

        $html = $this->actingAs($this->admin)->get(route('notices.index'))->assertOk()->getContent();

        // Only the live record may carry the claim, although all three are
        // addressable to the public audience.
        $this->assertSame(1, substr_count($html, 'Visible on website'));

        $this->actingAs($this->admin)
            ->get(route('notices.edit', $draft))
            ->assertOk()
            ->assertSee('Not live yet');

        $this->actingAs($this->admin)
            ->get(route('notices.edit', $expired))
            ->assertOk()
            ->assertSee('Not live yet');

        $this->actingAs($this->admin)
            ->get(route('notices.edit', $live))
            ->assertOk()
            ->assertSee('Public page')
            ->assertDontSee('Not live yet');
    }

    public function test_publishing_an_expired_notice_clears_the_deadline_and_restores_it(): void
    {
        $notice = Notice::query()->create([
            'title' => 'Late result notice',
            'body' => 'The result is published.',
            'audience' => 'all',
            'published_at' => now()->subDays(5),
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('public.notices.index'))->assertOk()->assertDontSee('Late result notice');

        $this->actingAs($this->admin)->post(route('notices.publish', $notice))->assertRedirect();

        $this->assertNull($notice->refresh()->expires_at);
        $this->assertTrue($notice->isLiveOnWebsite());

        $this->signOutFromWorkspace();
        $this->get(route('public.notices.index'))->assertOk()->assertSee('Late result notice');
    }

    private function assertWebsiteShows(string $title): void
    {
        $this->get('/')->assertOk()->assertSee($title);
    }

    /**
     * Public pages are checked as a visitor, because an authenticated account
     * is redirected from `/` to its own workspace.
     */
    private function signOutFromWorkspace(): void
    {
        $this->app['auth']->guard('web')->logout();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function noticePayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Winter vacation starts',
            'body' => 'The school will remain closed from the first of January.',
            'audience' => 'all',
            'academic_session_id' => null,
            'published_at' => null,
            'expires_at' => null,
            'is_pinned' => null,
            'notify_audience' => null,
        ], $overrides);
    }
}
