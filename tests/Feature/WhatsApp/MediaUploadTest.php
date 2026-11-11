<?php

namespace RSE\Delivra\Tests\Feature\WhatsApp;

use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RSE\Delivra\Exceptions\WhatsApp\ApiErrorException;
use RSE\Delivra\Exceptions\WhatsApp\WhatsAppException;
use RSE\Delivra\Facades\WhatsApp;
use RSE\Delivra\Tests\TestCase;
use RSE\Delivra\WhatsApp\Messages\TemplateMessage;
use RSE\Delivra\WhatsApp\Templates\Parameter;

class MediaUploadTest extends TestCase
{
    protected string $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pdf = tempnam(sys_get_temp_dir(), 'wa') . '.pdf';
        file_put_contents($this->pdf, "%PDF-1.4\n%test\n");
    }

    protected function tearDown(): void
    {
        @unlink($this->pdf);

        parent::tearDown();
    }

    public function test_uploads_file_to_phone_number_media_endpoint(): void
    {
        Http::fake(['graph.facebook.com/*/media' => Http::response(['id' => '777'])]);

        $id = whatsapp()->account('sales')->upload($this->pdf, 'invoice.pdf');

        $this->assertSame('777', $id);

        Http::assertSent(function (Request $request) {
            $parts = collect($request->data())->keyBy('name');

            return $request->url() === 'https://graph.facebook.com/v24.0/2001/media'
                && $request->hasHeader('Authorization', 'Bearer sales-token')
                && $request->isMultipart()
                && $parts['messaging_product']['contents'] === 'whatsapp'
                && $parts['type']['contents'] === 'application/pdf'
                && $parts['file']['filename'] === 'invoice.pdf'
                && str_starts_with($parts['file']['contents'], '%PDF');
        });
    }

    public function test_uploaded_file_uses_client_name(): void
    {
        Http::fake(['*' => Http::response(['id' => '778'])]);

        whatsapp()->upload(new UploadedFile($this->pdf, 'contract.pdf', null, null, true));

        Http::assertSent(fn (Request $request) => collect($request->data())->firstWhere('name', 'file')['filename'] === 'contract.pdf');
    }

    public function test_upload_contents_guesses_mime_type(): void
    {
        Http::fake(['*' => Http::response(['id' => '779'])]);

        whatsapp()->uploadContents(file_get_contents($this->pdf), 'report.pdf');

        Http::assertSent(fn (Request $request) => collect($request->data())->firstWhere('name', 'type')['contents'] === 'application/pdf');
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(WhatsAppException::class);

        whatsapp()->upload('/nope/missing.pdf');
    }

    public function test_meta_error_throws(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'Invalid file', 'code' => 131053]], 400)]);

        $this->expectException(ApiErrorException::class);

        whatsapp()->upload($this->pdf);
    }

    public function test_fake_records_uploads_without_calling_meta(): void
    {
        Http::fake();
        $fake = WhatsApp::fake();

        $id = whatsapp()->upload($this->pdf, 'invoice.pdf');

        whatsapp()->to('0500000001')->send(
            TemplateMessage::make('invoice_ready')
                ->header(Parameter::document($id, 'invoice.pdf'))
                ->body(['name' => 'Acme'])
        );

        $this->assertSame('fake-media-1', $id);

        $fake->assertUploaded('invoice.pdf')
            ->assertUploaded(fn (array $upload) => $upload['mimeType'] === 'application/pdf')
            ->assertTemplateSent('invoice_ready', fn ($message, $sent) => $sent->payload['template']['components'][0]['parameters'][0]['document']['id'] === $id);

        Http::assertNothingSent();
    }

    public function test_fake_assert_nothing_uploaded(): void
    {
        WhatsApp::fake()->assertNothingUploaded();
    }
}
