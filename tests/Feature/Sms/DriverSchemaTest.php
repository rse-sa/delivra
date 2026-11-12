<?php

namespace RSE\Delivra\Tests\Feature\Sms;

use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\Sms\DriverNotFoundException;
use RSE\Delivra\Exceptions\Sms\InvalidDriverSettingsException;
use RSE\Delivra\Facades\Sms;
use RSE\Delivra\Sms\Drivers\MsegatDriver;
use RSE\Delivra\Sms\Schema\DriverDefinition;
use RSE\Delivra\Sms\SmsFeature;
use RSE\Delivra\Tests\TestCase;

class DriverSchemaTest extends TestCase
{
    public function test_lists_configured_drivers_without_null(): void
    {
        $drivers = Sms::drivers();

        $this->assertSame(['unifonic', 'msegat', 'yamamah', 'shamelsms'], $drivers->keys()->all());
        $this->assertContainsOnlyInstancesOf(DriverDefinition::class, $drivers);
        $this->assertSame('Msegat', $drivers['msegat']->getLabel());
        $this->assertTrue($drivers['msegat']->supports(SmsFeature::Credits));
    }

    public function test_definition_takes_the_config_key_as_name(): void
    {
        config(['delivra.sms.drivers.marketing' => ['class' => MsegatDriver::class]]);
        $this->app->forgetInstance('delivra-sms');
        Sms::clearResolvedInstance('delivra-sms');

        $this->assertSame('marketing', Sms::definition('marketing')->getName());
        $this->assertSame('msegat', Sms::definition('msegat')->getName());
    }

    public function test_unknown_driver_throws(): void
    {
        $this->expectException(DriverNotFoundException::class);

        Sms::definition('nope');
    }

    public function test_rules_for_a_driver(): void
    {
        $this->assertSame([
            'app_sid'  => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'sender'   => ['required', 'string', 'max:11'],
        ], Sms::rules('unifonic'));
    }

    public function test_invalid_settings_fail_at_send_time_not_in_via(): void
    {
        Http::fake();

        $sms = Sms::via('msegat', ['username' => 'u']);

        try {
            $sms->to('966500000000')->body('Hi')->send();
            $this->fail('Expected InvalidDriverSettingsException.');
        } catch (InvalidDriverSettingsException $e) {
            $this->assertSame('msegat', $e->getDriver());
            $this->assertSame(['key', 'sender'], array_keys($e->getErrors()));
        }

        Http::assertNothingSent();
    }
}
