<?php

namespace RSE\Delivra\Tests\Unit\Sms\Schema;

use Illuminate\Support\Facades\Validator;
use LogicException;
use RSE\Delivra\Sms\Schema\AuthMode;
use RSE\Delivra\Sms\Schema\DriverDefinition;
use RSE\Delivra\Sms\Schema\Field;
use RSE\Delivra\Sms\SmsFeature;
use RSE\Delivra\Tests\TestCase;

class DriverDefinitionTest extends TestCase
{
    protected function twoModes(): DriverDefinition
    {
        return DriverDefinition::make()
            ->name('acme')
            ->features([SmsFeature::Balance])
            ->authModes([
                AuthMode::make('api_key')->fields([
                    Field::make('api_key')->secret()->rules('required|string'),
                ]),
                AuthMode::make('credentials')->fields([
                    Field::make('username')->rules('required|string'),
                    Field::make('password')->secret()->rules('required|string'),
                ]),
            ])
            ->fields([
                Field::make('sender')->rules('required|string|max:11'),
            ]);
    }

    public function test_rules_for_a_single_mode_have_no_auth_mode_key(): void
    {
        $definition = DriverDefinition::make()
            ->authModes([AuthMode::make('basic')->fields([Field::make('key')->rules('required')])])
            ->fields([Field::make('sender')->rules('required|max:11')]);

        $this->assertSame([
            'key'    => ['required'],
            'sender' => ['required', 'max:11'],
        ], $definition->rules());
    }

    public function test_rules_for_several_modes_require_auth_mode_and_scope_fields(): void
    {
        $rules = $this->twoModes()->rules();

        $this->assertSame(['required', 'in:api_key,credentials'], $rules['auth_mode']);
        $this->assertSame(['exclude_unless:auth_mode,credentials', 'required', 'string'], $rules['username']);
        $this->assertSame(['required', 'string', 'max:11'], $rules['sender']);
    }

    public function test_validation_only_checks_the_chosen_mode(): void
    {
        $rules = $this->twoModes()->rules();

        $valid = Validator::make(['auth_mode' => 'api_key', 'api_key' => 'k', 'sender' => 'ACME', 'username' => 'leftover'], $rules);

        $this->assertTrue($valid->passes());
        $this->assertSame(['auth_mode' => 'api_key', 'api_key' => 'k', 'sender' => 'ACME'], $valid->validated());

        $invalid = Validator::make(['auth_mode' => 'credentials', 'api_key' => 'k', 'sender' => 'ACME'], $rules);

        $this->assertTrue($invalid->fails());
        $this->assertSame(['username', 'password'], array_keys($invalid->errors()->toArray()));

        $this->assertTrue(Validator::make(['api_key' => 'k', 'sender' => 'ACME'], $rules)->fails());
    }

    public function test_auth_mode_for_settings(): void
    {
        $definition = $this->twoModes();

        $this->assertSame('credentials', $definition->authModeFor(['auth_mode' => 'credentials'])->getName());
        $this->assertNull($definition->authModeFor([]));

        $single = DriverDefinition::make()->authModes([AuthMode::make('basic')]);

        $this->assertSame('basic', $single->authModeFor([])->getName());
        $this->assertNull(DriverDefinition::make()->authModeFor([]));
    }

    public function test_supports_features(): void
    {
        $definition = $this->twoModes();

        $this->assertTrue($definition->supports(SmsFeature::Balance));
        $this->assertTrue($definition->supports('balance'));
        $this->assertFalse($definition->supports(SmsFeature::Bulk));
    }

    public function test_to_array_shape(): void
    {
        $array = json_decode(json_encode($this->twoModes()), true);

        $this->assertSame('acme', $array['name']);
        $this->assertSame('Acme', $array['label']);
        $this->assertSame(['balance'], $array['features']);
        $this->assertSame(['api_key', 'credentials'], array_column($array['auth_modes'], 'name'));

        $password = $array['auth_modes'][1]['fields'][1];

        $this->assertSame('password', $password['name']);
        $this->assertSame('Password', $password['label']);
        $this->assertSame('password', $password['type']);
        $this->assertTrue($password['secret']);
        $this->assertTrue($password['required']);
        $this->assertSame(['required', 'string'], $password['rules']);

        $this->assertSame('Sender name', $array['fields'][0]['label']);
    }

    public function test_labels_follow_the_app_locale(): void
    {
        app()->setLocale('ar');

        $this->assertSame('اسم المرسل', Field::make('sender')->getLabel());
        $this->assertSame('Custom', Field::make('sender')->label('Custom')->getLabel());
        $this->assertSame('Some Field', Field::make('some_field')->getLabel());
    }

    public function test_rejects_duplicate_field_names(): void
    {
        $this->expectException(LogicException::class);

        DriverDefinition::make()
            ->authModes([AuthMode::make('basic')->fields([Field::make('sender')])])
            ->fields([Field::make('sender')])
            ->rules();
    }
}
