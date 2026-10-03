<?php

it('sends guests to the login page', function () {
    $this->get('/')->assertRedirect('/dashboard');
    $this->get('/dashboard')->assertRedirect('/login');
});
