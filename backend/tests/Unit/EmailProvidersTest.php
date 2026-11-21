<?php

use App\DTOs\EmailMessage;
use App\Exceptions\EmailDeliveryException;
use App\Infrastructure\Email\LaravelMailEmailProvider;
use App\Infrastructure\Email\MockEmailProvider;
use Illuminate\Contracts\Mail\Mailer;

it('wraps transport failures without leaking transport details', function () {
    $mailer = Mockery::mock(Mailer::class);
    $mailer->shouldReceive('raw')->andThrow(new RuntimeException('smtp://user:hunter2@mail.internal refused'));

    try {
        (new LaravelMailEmailProvider($mailer))->send(new EmailMessage('sales@catalogue.test', 'S', 'B'));
        $this->fail('Expected EmailDeliveryException');
    } catch (EmailDeliveryException $e) {
        expect($e->getMessage())->not->toContain('hunter2')
            ->and($e->getPrevious()?->getMessage())->toContain('hunter2');
    }
});

it('rejects an unusable recipient as a delivery failure', function () {
    config(['mail.default' => 'array']);

    (new LaravelMailEmailProvider(app('mailer')))->send(new EmailMessage('not an address', 'S', 'B'));
})->throws(EmailDeliveryException::class);

it('can be told to fail so error handling can be exercised', function () {
    $mock = new MockEmailProvider;
    $mock->failWith = new RuntimeException('boom');

    $mock->send(new EmailMessage('sales@catalogue.test', 'S', 'B'));
})->throws(EmailDeliveryException::class);
