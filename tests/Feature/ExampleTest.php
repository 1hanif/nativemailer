<?php

test('the root redirects to the inbox', function () {
    $response = $this->get('/');

    $response->assertRedirect('/admin/emails');
});
