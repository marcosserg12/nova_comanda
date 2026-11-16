<?php

it('answers the health check', function () {
    $this->get('/up')->assertOk();
});
