<?php

use App\Enums\AuditAction;
use App\Enums\ChannelType;
use App\Enums\Rank;
use App\Models\AccessLog;
use App\Models\AdminAuditLog;
use App\Models\ChannelInvitation;
use App\Models\Room;
use App\Models\User;
use App\Services\AdminDashboardService;
use App\Services\ModerationService;
use Illuminate\Support\Str;

function panelChannel(User $creator, array $attributes = []): Room
{
    $room = Room::create(array_merge([
        'code' => 'PNL-'.strtoupper(Str::random(4)),
        'title' => 'Panel '.Str::random(4),
        'type' => ChannelType::PrivateFreeEntry,
        'status' => 'active',
        'created_by_user_id' => $creator->id,
        'created_by_ip' => '198.51.100.23',
    ], $attributes));
    $room->belongsToMany(User::class, 'room_user')->attach($creator->id, ['role' => 'owner', 'status' => 'active']);

    return $room;
}

function accessLogIn(Room $room, User $user): AccessLog
{
    return AccessLog::create([
        'room_id' => $room->id,
        'user_id' => $user->id,
        'session_id' => 'panel-'.$user->id,
        'alias' => $user->name,
        'ip_address' => '203.0.113.77',
        'city' => 'Lisbon',
        'country' => 'Portugal',
        'last_seen_at' => now(),
    ]);
}

function adminThree(array $attributes = []): User
{
    return User::factory()->rank(Rank::AdminThree)->create($attributes);
}

/* -------------------------------------------------------------
 * What each panel shows (Q9)
 * ------------------------------------------------------------- */

test('Admin I sees IP addresses, locations and the network map', function () {
    $room = panelChannel(User::factory()->create());
    accessLogIn($room, User::factory()->create());

    $this->actingAs(User::factory()->adminOne()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('203.0.113.77')
        ->assertSee('Lisbon')
        ->assertSee('id="intel-section"', false);
});

test('Admin II sees every channel and member, without IP addresses, locations or the map', function () {
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $room = panelChannel($owner, ['code' => 'PNL-OTHR']);
    accessLogIn($room, $owner);
    $viewer = User::factory()->adminTwo()->create();

    $this->actingAs($viewer)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('PNL-OTHR')
        ->assertDontSee('203.0.113.77')
        ->assertDontSee('Lisbon')
        ->assertDontSee('id="intel-section"', false)
        ->assertDontSee(route('admin.intel.index'), false)
        ->assertDontSee(route('admin.emails.index'), false);

    $this->actingAs($viewer)->get(route('admin.members.index'))
        ->assertOk()
        ->assertSee('owner@example.com')
        ->assertDontSee('203.0.113.77')
        ->assertDontSee(route('admin.users.destroy', ['id' => $owner->id]), false);

    $this->actingAs($viewer)->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('PNL-OTHR')
        ->assertDontSee('203.0.113.77')
        ->assertDontSee('Lisbon');
});

test('Admin III sees only their own channels and those channels\' members, without emails', function () {
    $viewer = adminThree();
    $own = panelChannel($viewer, ['code' => 'PNL-MINE']);
    $ownMember = memberOf($own, ['name' => 'Own Member', 'email' => 'own@example.com']);
    $other = panelChannel(User::factory()->create(['name' => 'Other Owner']), ['code' => 'PNL-OTHR']);
    memberOf($other, ['name' => 'Stranger']);
    accessLogIn($own, $ownMember);
    accessLogIn($other, $other->creator);

    $this->actingAs($viewer)->get(route('admin.channels.index'))
        ->assertOk()
        ->assertSee('PNL-MINE')
        ->assertDontSee('PNL-OTHR');

    $this->actingAs($viewer)->get(route('admin.members.index'))
        ->assertOk()
        ->assertSee('Own Member')
        ->assertDontSee('Stranger')
        ->assertDontSee('Other Owner')
        ->assertDontSee('own@example.com');

    $this->actingAs($viewer)->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('PNL-MINE')
        ->assertDontSee('PNL-OTHR')
        ->assertDontSee('203.0.113.77');
});

test('members\' hidden profile fields stay hidden from Admin II and III', function (Closure $viewer) {
    $admin = $viewer();
    $room = panelChannel($admin);
    $member = memberOf($room, ['birthday' => '1990-06-20', 'hide_age' => true, 'hide_birthday' => true, 'bio' => 'Private bio', 'hide_bio' => true, 'location' => 'Secret Town', 'hide_location' => true]);

    $this->actingAs($admin)->get(route('admin.members.index'))
        ->assertOk()
        ->assertDontSee('Private bio')
        ->assertDontSee('Secret Town')
        ->assertDontSee('Jun 20, 1990');

    $dossier = $member->adminDossierFor($admin);
    expect($dossier['birthday'])->toBeNull();
    expect($dossier['bio'])->toBeNull();
    expect($dossier['location'])->toBeNull();
    expect($dossier['latest_ip'])->toBeNull();
})->with([
    'Admin II' => [fn () => User::factory()->adminTwo()->create()],
    'Admin III' => [fn () => adminThree()],
]);

test('Admin I\'s dossier shows hidden fields and the latest IP', function () {
    $member = User::factory()->create(['bio' => 'Private bio', 'hide_bio' => true]);
    accessLogIn(panelChannel(User::factory()->create()), $member);

    $dossier = $member->adminDossierFor(User::factory()->adminOne()->create());

    expect($dossier['bio'])->toBe('Private bio');
    expect($dossier['latest_ip'])->toBe('203.0.113.77');
});

test('only Admin I can open the network map, email templates, delete members or create console channels', function (Closure $viewer) {
    $admin = $viewer();
    $member = User::factory()->create();

    $this->actingAs($admin)->get(route('admin.intel.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.emails.index'))->assertForbidden();
    $this->actingAs($admin)->delete(route('admin.users.destroy', ['id' => $member->id]))->assertForbidden();
    $this->actingAs($admin)->post(route('admin.channels.store'), ['title' => 'Console'])->assertForbidden();

    expect(User::find($member->id))->not->toBeNull();
    expect(Room::where('title', 'Console')->exists())->toBeFalse();
})->with([
    'Admin II' => [fn () => User::factory()->adminTwo()->create()],
    'Admin III' => [fn () => adminThree()],
]);

test('Admin II and III go to their profile after signing in only when they display as members', function () {
    expect(User::factory()->adminTwo()->create()->homeRoute())->toBe(route('admin.dashboard'));
    expect(adminThree()->homeRoute())->toBe(route('profile.show'));
});

test('Admin III reaches their panel from the profile', function () {
    $this->actingAs(adminThree())
        ->get(route('profile.show'))
        ->assertOk()
        ->assertSee('id="admin-panel-link"', false)
        ->assertSee('My channels');
});

/* -------------------------------------------------------------
 * Managing channels from the panel
 * ------------------------------------------------------------- */

test('Admin III manages their own channels from the panel', function () {
    $viewer = adminThree();
    $room = panelChannel($viewer);

    $this->actingAs($viewer)->put(route('admin.channels.update', ['id' => $room->id]), ['title' => 'Renamed', 'allowed_languages' => ['en']])->assertRedirect();
    $this->actingAs($viewer)->post(route('admin.channels.toggle-notifications', ['id' => $room->id]))->assertRedirect();
    $this->actingAs($viewer)->post(route('admin.channels.invite', ['id' => $room->id]), ['max_uses' => 3])->assertRedirect();

    expect($room->fresh()->title)->toBe('Renamed');
    expect($room->fresh()->notify_admin)->toBeTrue();
    expect(ChannelInvitation::where('room_id', $room->id)->count())->toBe(1);
});

test('Admin II and III cannot change channels they did not create', function (Closure $viewer, string $action) {
    $room = panelChannel(User::factory()->create(), ['title' => 'Untouched']);
    $admin = $viewer();

    $response = match ($action) {
        'update' => $this->actingAs($admin)->put(route('admin.channels.update', ['id' => $room->id]), ['title' => 'Hijacked', 'allowed_languages' => ['en']]),
        'archive' => $this->actingAs($admin)->post(route('admin.channels.toggle', ['id' => $room->id])),
        'invite' => $this->actingAs($admin)->post(route('admin.channels.invite', ['id' => $room->id]), ['max_uses' => 1]),
        'delete' => $this->actingAs($admin)->delete(route('admin.channels.destroy', ['id' => $room->id])),
    };

    $response->assertForbidden();
    expect($room->fresh()->title)->toBe('Untouched');
    expect($room->fresh()->status)->toBe('active');
    expect(ChannelInvitation::count())->toBe(0);
})->with([
    'Admin II' => [fn () => User::factory()->adminTwo()->create()],
    'Admin III' => [fn () => adminThree()],
])->with(['update', 'archive', 'invite', 'delete']);

/* -------------------------------------------------------------
 * Channel profile modal
 * ------------------------------------------------------------- */

test('the channel profile lists settings, members, pending invitations and removed members', function () {
    $creator = User::factory()->create(['name' => 'Founder', 'tag' => 'founder']);
    $room = panelChannel($creator, ['description' => 'Field notes']);
    memberOf($room, ['name' => 'Active One', 'tag' => 'active_one']);
    $room->inviteUser(User::factory()->create(['name' => 'Pending One', 'tag' => 'pending_one']), $creator->id);
    $removed = memberOf($room, ['name' => 'Removed One', 'tag' => 'removed_one']);
    app(ModerationService::class)->kick($creator, $room, $removed);
    $invitation = ChannelInvitation::createForRoom($room, $creator, maxUses: 5);

    $this->actingAs(User::factory()->adminTwo()->create())
        ->get(route('admin.channels.profile', ['id' => $room->id]))
        ->assertOk()
        ->assertSee('Field notes')
        ->assertSee('Founder @founder')
        ->assertSee('@active_one')
        ->assertSee('@pending_one')
        ->assertSee('@removed_one')
        ->assertSee($invitation->code)
        ->assertSee('Private · free entry')
        ->assertDontSee('198.51.100.23');
});

test('only Admin I sees the IP a channel was created from', function () {
    $room = panelChannel(User::factory()->create());

    $this->actingAs(User::factory()->adminOne()->create())
        ->get(route('admin.channels.profile', ['id' => $room->id]))
        ->assertOk()
        ->assertSee('198.51.100.23');
});

test('Admin III can open the profile of their own channels only', function () {
    $viewer = adminThree();
    $own = panelChannel($viewer);
    $other = panelChannel(User::factory()->create());

    $this->actingAs($viewer)->get(route('admin.channels.profile', ['id' => $own->id]))->assertOk();
    $this->actingAs($viewer)->get(route('admin.channels.profile', ['id' => $other->id]))->assertNotFound();
});

test('members without an admin rank cannot open a channel profile', function () {
    $member = User::factory()->create();
    $room = panelChannel($member);

    $this->actingAs($member)->get(route('admin.channels.profile', ['id' => $room->id]))->assertRedirect(route('profile.show'));
});

test('the channels table offers the profile button to every panel', function () {
    $viewer = adminThree();
    $room = panelChannel($viewer);

    $this->actingAs($viewer)->get(route('admin.channels.index'))
        ->assertOk()
        ->assertSee(route('admin.channels.profile', ['id' => $room->id]), false)
        ->assertSee('id="channel-profile-modal"', false);
});

/* -------------------------------------------------------------
 * Audit log (AU-8)
 * ------------------------------------------------------------- */

test('the audit log shows Admin II moderation entries only, and Admin III nothing', function () {
    $subject = User::factory()->create(['name' => 'Logged Subject']);
    AdminAuditLog::record(AuditAction::MemberKicked, subject: $subject);
    AdminAuditLog::record(AuditAction::ChannelAccessOverride, subject: User::factory()->create(['name' => 'Override Target']));

    $this->actingAs(User::factory()->adminTwo()->create())->get(route('admin.logs.index'))
        ->assertOk()
        ->assertSee('Member removed from channel')
        ->assertDontSee('Entered a channel as Admin I');

    $this->actingAs(User::factory()->adminOne()->create())->get(route('admin.logs.index'))
        ->assertSee('Entered a channel as Admin I');

    $this->actingAs(adminThree())->get(route('admin.logs.index'))
        ->assertOk()
        ->assertDontSee('id="audit-section"', false);
});

test('only Admin I sends direct invitations, which can create accounts', function (Closure $viewer) {
    $admin = $viewer();
    $room = panelChannel($admin);

    $this->actingAs($admin)
        ->post(route('admin.channels.invite', ['id' => $room->id]), ['email' => 'newcomer@example.com', 'send_email' => false])
        ->assertForbidden();

    expect(User::where('email', 'newcomer@example.com')->exists())->toBeFalse();
})->with([
    'Admin II' => [fn () => User::factory()->adminTwo()->create()],
    'Admin III' => [fn () => adminThree()],
]);

test('access-log data for Admin II and III carries no IP address or location', function (Closure $viewer) {
    $admin = $viewer();
    accessLogIn(panelChannel($admin), User::factory()->create());

    $entry = app(AdminDashboardService::class)->getRecentVisitors($admin)->sole();

    expect($entry['ip_address'])->toBeNull();
    expect($entry['city'])->toBeNull();
    expect($entry['country'])->toBeNull();
    expect($entry['latitude'])->toBeNull();
    expect($entry['user_agent'])->toBeNull();
})->with([
    'Admin II' => [fn () => User::factory()->adminTwo()->create()],
    'Admin III' => [fn () => adminThree()],
]);
