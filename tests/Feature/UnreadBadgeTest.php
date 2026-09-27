<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Email;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\App;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['nativephp-internal.running' => true]);
    $this->counts = [];
    App::shouldReceive('badgeCount')->andReturnUsing(function (int $count) {
        $this->counts[] = $count;
    });
});

test('the badge follows captured, read, unread and deleted emails', function () {
    $first = Email::create(['from' => 'a@x.test']);
    $second = Email::create(['from' => 'b@x.test']);
    $first->markAsRead();
    $first->update(['is_read' => false]);
    $second->delete();

    expect($this->counts)->toBe([1, 2, 1, 2, 1]);
});

test('unrelated updates do not touch the badge', function () {
    $email = Email::create(['from' => 'a@x.test']);
    $email->update(['subject' => 'changed']);

    expect($this->counts)->toBe([1]);
});

test('mass deletes refresh the badge', function () {
    Email::create(['from' => 'a@x.test', 'received_at' => now()->subDays(30)]);
    Email::create(['from' => 'b@x.test']);
    $this->counts = [];

    Setting::set('retention_days', 7);
    Email::pruneNow();
    Livewire::test(ListEmails::class)->callAction('deleteAll');

    expect($this->counts)->toBe([1, 0]);
});

test('outside the desktop app the badge is never touched', function () {
    config(['nativephp-internal.running' => false]);

    Email::create(['from' => 'a@x.test']);

    expect($this->counts)->toBe([]);
});
