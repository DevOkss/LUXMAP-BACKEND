<?php

test('the root redirects to the login page', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('login'));
});