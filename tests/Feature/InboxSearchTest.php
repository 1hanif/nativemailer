<?php

use App\Filament\Resources\Emails\Pages\ListEmails;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Email::create(['from' => 'a@x.test', 'to' => 'b@x.test', 'subject' => 'Welcome', 'body_text' => 'Your code is 834201']);
    Email::create(['from' => 'a@x.test', 'to' => 'b@x.test', 'subject' => 'Invoice', 'body_html' => '<p>Amount due: $99</p>']);
    Email::create(['from' => 'a@x.test', 'to' => 'b@x.test', 'bcc' => 'auditor@x.test', 'subject' => 'Report']);
});

test('search matches text in the message body', function (string $term, string $expected) {
    Livewire::test(ListEmails::class)
        ->searchTable($term)
        ->assertSee($expected)
        ->assertCountTableRecords(1);
})->with([
    'plain-text body' => ['834201', 'Welcome'],
    'HTML body' => ['Amount due', 'Invoice'],
    'bcc recipient' => ['auditor@', 'Report'],
    'subject still works' => ['Invoice', 'Invoice'],
]);
