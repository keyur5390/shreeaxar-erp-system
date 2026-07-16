<?php

namespace Tests\Unit;

use App\Exceptions\InvalidImageUploadException;
use App\Http\Middleware\SanitizeInput;
use App\Services\StorageService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class SecurityHardeningTest extends TestCase
{
    public function test_sanitize_input_strips_script_tags_from_strings(): void
    {
        $middleware = new SanitizeInput();
        $request = Request::create('/api/customers', 'POST', [
            'company_name' => '<script>alert(1)</script>',
            'email' => 'test@example.com',
        ]);

        $middleware->handle($request, fn (Request $req): Response => new Response('OK'));

        $this->assertSame('alert(1)', $request->input('company_name'));
        $this->assertSame('test@example.com', $request->input('email'));
    }

    public function test_storage_service_rejects_non_image_mime_type(): void
    {
        $temp = tempnam(sys_get_temp_dir(), 'upload');
        file_put_contents($temp, '<?php echo "bad";');

        $file = new UploadedFile($temp, 'fake.jpg', 'application/x-php', null, true);
        $service = new StorageService();

        try {
            $this->expectException(InvalidImageUploadException::class);
            $this->expectExceptionMessage('Invalid file type.');
            $service->resizeAndStore($file, 'products');
        } finally {
            @unlink($temp);
        }
    }
}
