<?php

use App\Support\QrCode;

it('renders an SVG data URI', function () {
    expect(QrCode::dataUri('https://comanda.mswebsolutions.site/acompanhar'))->toStartWith('data:image/svg+xml;base64,');
});
