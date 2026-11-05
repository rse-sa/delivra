<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Tests\TestCase;

class MakeTemplateCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        File::deleteDirectory(app_path('WhatsApp'));

        parent::tearDown();
    }

    public function test_generates_class_from_meta_definition(): void
    {
        Http::fake(['*' => Http::response(['data' => [[
            'name'       => 'order_ready',
            'language'   => 'ar',
            'status'     => 'APPROVED',
            'components' => [
                ['type' => 'HEADER', 'format' => 'DOCUMENT'],
                ['type' => 'BODY', 'text' => 'Hi {{name}}, pay {{amount}}'],
                ['type' => 'BUTTONS', 'buttons' => [['type' => 'URL', 'url' => 'https://x.test/{{1}}']]],
            ],
        ]]])]);

        $this->artisan('make:whatsapp-template', ['name' => 'OrderReady'])->assertSuccessful();

        $class = File::get(app_path('WhatsApp/Templates/OrderReady.php'));

        $this->assertStringContainsString('namespace App\WhatsApp\Templates;', $class);
        $this->assertStringContainsString("protected ?string \$name = 'order_ready';", $class);
        $this->assertStringContainsString("protected ?string \$language = 'ar';", $class);
        $this->assertStringContainsString("return Parameter::document('');", $class);
        $this->assertStringContainsString("'amount' => '',", $class);
        $this->assertStringContainsString("0 => Button::url(''),", $class);
    }
}
