<?php

use App\Events\EmailReceived;
use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('the inbox shows a newly captured email when EmailReceived arrives', function () {
    $page = Livewire::test(ListEmails::class)->assertDontSee('Fresh arrival');

    $email = Email::create(['from' => 'a@x.test', 'to' => 'b@x.test', 'subject' => 'Fresh arrival']);

    $page->dispatch('native:\\'.EmailReceived::class, id: $email->id)
        ->assertSee('Fresh arrival');
});

test('emails cannot be created or edited by hand', function () {
    $this->get('/admin/emails/create')->assertNotFound();
    $this->get('/admin/emails/1/edit')->assertNotFound();
});
