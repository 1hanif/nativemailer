<?php

use App\Filament\Resources\Emails\Pages\ViewEmail;
use App\Models\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Native\Desktop\Facades\Shell;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->email = Email::create(['from' => 'a@x.test', 'body_html' => '<a href="https://shop.test">Shop</a>']);
});

test('links clicked in the preview open in the system browser', function () {
    Shell::shouldReceive('openExternal')->once()->with('https://shop.test/orders');

    Livewire::test(ViewEmail::class, ['record' => $this->email->id])->call('openLink', 'https://shop.test/orders');
});

test('non-web schemes are never handed to the shell', function (string $url) {
    Shell::shouldReceive('openExternal')->never();

    Livewire::test(ViewEmail::class, ['record' => $this->email->id])->call('openLink', $url);
})->with(['file:///etc/passwd', 'javascript:alert(1)', 'smb://share/x']);

test('the preview is sandboxed, forwards clicks and offers device widths', function () {
    $html = $this->get("/admin/emails/{$this->email->id}")->assertOk()->getContent();

    expect($html)
        ->toContain('sandbox="allow-scripts"')
        ->not->toContain('allow-same-origin')
        ->toContain('Content-Security-Policy')
        ->toContain('nativemailer:open-link')
        ->toContain("device = 'mobile'");
});
