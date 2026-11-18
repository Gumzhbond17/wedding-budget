<?php

use App\Enums\Currency;
use App\Models\ExchangeRate;
use App\Models\Expense;
use App\Models\ExpensePayment;
use App\Models\User;
use App\Models\Wedding;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Monolog\Handler\TestHandler;

/**
 * @return array{0: User, 1: Wedding, 2: Expense}
 */
function usdExpenseOwner(): array
{
    $user = User::factory()->create();
    $wedding = Wedding::factory()->for($user)->create();
    ExchangeRate::factory()->for($wedding)->create(['rate' => '21850.00000000', 'effective_date' => '2026-10-01']);
    $expense = Expense::factory()->for($wedding)->inCurrency(Currency::USD, '21850.00000000', '6500.00')->invoiced('6800.00')->create();

    return [$user, $wedding, $expense];
}

/**
 * A real uploaded file, so getMimeType() sniffs the content like in production.
 * (UploadedFile::fake() derives the MIME type from the extension, which would hide the check.)
 */
function uploadWithContent(string $clientName, string $content): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'receipt');
    file_put_contents($path, $content);

    return new UploadedFile($path, $clientName, test: true);
}

beforeEach(function () {
    $this->travelTo('2026-11-18');
    Storage::fake('receipts');
});

it('records a payment with its own rate snapshot and updates the expense', function () {
    [$user, $wedding, $expense] = usdExpenseOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.payments.store', [$wedding, $expense]), [
        'amount' => '2000.00', 'paid_date' => '2026-11-10', 'payment_method' => 'bank_transfer',
        'exchange_rate' => '1', 'base_amount' => '1.00',
    ])->assertCreated()
        ->assertJsonPath('data.amount', ['amount' => '2000.00', 'currency' => 'USD'])
        ->assertJsonPath('data.base_amount', ['amount' => '43700000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.receipt', null);

    expect($expense->refresh())->paid_amount->toBe('2000.00')->status->value->toBe('partially_paid');
});

it('validates payments', function (array $input, string $field) {
    [$user, $wedding, $expense] = usdExpenseOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.expenses.payments.store', [$wedding, $expense]), [
        'amount' => '100.00', 'paid_date' => '2026-11-10', ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'other currency than the expense' => [['currency' => 'LAK'], 'currency'],
    'zero amount' => [['amount' => '0'], 'amount'],
    'future date' => [['paid_date' => '2026-12-01'], 'paid_date'],
]);

it('updates and deletes a payment', function () {
    [$user, $wedding, $expense] = usdExpenseOwner();
    $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '1000.00']);

    $this->actingAs($user)->patchJson(route('v1.weddings.expenses.payments.update', [$wedding, $expense, $payment]), ['amount' => '1500.00'])
        ->assertOk()->assertJsonPath('data.amount.amount', '1500.00');
    expect($expense->refresh()->paid_amount)->toBe('1500.00');

    $this->deleteJson(route('v1.weddings.expenses.payments.destroy', [$wedding, $expense, $payment]))->assertNoContent();
    expect($expense->refresh()->paid_amount)->toBe('0.00');
});

describe('receipts', function () {
    it('uploads a receipt to the private disk and exposes only metadata', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);

        $this->actingAs($user)->post(route('v1.weddings.expenses.payments.receipt.store', [$wedding, $expense, $payment]), [
            'receipt' => UploadedFile::fake()->image('deposit.png'),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.receipt.original_name', 'deposit.png')
            ->assertJsonPath('data.receipt.mime_type', 'image/png')
            ->assertJsonMissingPath('data.receipt.path');

        Storage::disk('receipts')->assertExists($payment->refresh()->receipt_path);
        expect($payment->receipt_path)->toStartWith("payments/{$payment->id}/")->not->toContain('deposit');
    });

    it('rejects files that are not images or PDFs, or too large', function (Closure $file) {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);

        $this->actingAs($user)->post(route('v1.weddings.expenses.payments.receipt.store', [$wedding, $expense, $payment]), [
            'receipt' => $file(),
        ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('receipt');
    })->with([
        'HTML renamed to .png' => fn () => uploadWithContent('receipt.png', '<html><script>alert(1)</script></html>'),
        'SVG renamed to .png' => fn () => uploadWithContent('receipt.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'over 5 MB' => fn () => UploadedFile::fake()->image('receipt.jpg')->size(6000),
    ]);

    it('serves a receipt only through a short-lived signed URL', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);
        $this->actingAs($user)->post(route('v1.weddings.expenses.payments.receipt.store', [$wedding, $expense, $payment]), [
            'receipt' => UploadedFile::fake()->image('deposit.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $url = $this->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))
            ->assertOk()
            ->assertJsonPath('data.expires_at', now()->addMinutes(2)->toIso8601String())
            ->json('data.url');

        auth()->guard('web')->logout();

        $this->get($url)
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename=deposit.png')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'no-referrer');
        $this->get($url.'0')->assertForbidden();
        $this->get(str_replace("receipts/{$payment->id}", 'receipts/'.($payment->id + 1), $url))->assertForbidden();

        $this->travel(121)->seconds();
        $this->get($url)->assertForbidden();
    });

    it('sends a safe file name whatever the client named the file', function (string $original, string $mime, string $disposition) {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);
        Storage::disk('receipts')->put('payments/r', 'content');
        $payment->forceFill(['receipt_path' => 'payments/r', 'receipt_original_name' => $original, 'receipt_mime_type' => $mime, 'receipt_size' => 7])->save();

        $url = $this->actingAs($user)
            ->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))
            ->json('data.url');

        $this->get($url)->assertOk()->assertHeader('Content-Disposition', $disposition);
    })->with([
        'path, quotes, CR/LF and percent' => ["../evil\"\r\n%name.png", 'image/png', 'inline; filename=evilname.png'],
        'wrong extension follows the real type' => ['invoice.exe', 'application/pdf', 'attachment; filename=invoice.pdf'],
        'nothing usable left' => ['"%/\\.png', 'image/webp', 'inline; filename=receipt.webp'],
        'Lao name keeps its marks, ASCII fallback' => ['ໃບຮັບເງິນ.jpg', 'image/jpeg', "inline; filename=receipt.jpg; filename*=utf-8''".rawurlencode('ໃບຮັບເງິນ.jpg')],
    ]);

    it('shows images inline but downloads PDFs', function (string $mime, string $disposition) {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);
        Storage::disk('receipts')->put('payments/r', 'content');
        $payment->forceFill(['receipt_path' => 'payments/r', 'receipt_original_name' => 'bill', 'receipt_mime_type' => $mime, 'receipt_size' => 7])->save();

        $url = $this->actingAs($user)
            ->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))
            ->json('data.url');

        $this->get($url)->assertOk()
            ->assertHeader('Content-Type', $mime)
            ->assertHeader('Content-Disposition', $disposition)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Cache-Control', 'no-store, private');
    })->with([
        'JPEG' => ['image/jpeg', 'inline; filename=bill.jpg'],
        'PNG' => ['image/png', 'inline; filename=bill.png'],
        'WebP' => ['image/webp', 'inline; filename=bill.webp'],
        'PDF' => ['application/pdf', 'attachment; filename=bill.pdf'],
    ]);

    it('uploads a real PDF and serves it as a download', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);

        $this->actingAs($user)->post(route('v1.weddings.expenses.payments.receipt.store', [$wedding, $expense, $payment]), [
            'receipt' => uploadWithContent('invoice.pdf', "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF\n"),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.receipt.mime_type', 'application/pdf');

        $url = $this->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))->json('data.url');

        $this->get($url)->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=invoice.pdf');
    });

    it('never writes signed receipt URLs to the logs', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);
        $payment->forceFill(['receipt_path' => 'payments/r', 'receipt_original_name' => 'r.png', 'receipt_mime_type' => 'image/png', 'receipt_size' => 7])->save();
        $url = $this->actingAs($user)
            ->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))
            ->json('data.url');
        $logs = new TestHandler;
        Log::driver()->getLogger()->pushHandler($logs);

        // A storage failure is reported (logged) with full exception context.
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->allows('exists')->andReturnTrue();
        $disk->allows('response')->andThrow(new RuntimeException('Receipt storage unavailable'));
        Storage::set('receipts', $disk);

        $this->get($url)->assertServerError();
        $this->get($url.'tampered')->assertForbidden();

        expect($logs->getRecords())->not->toBeEmpty();
        foreach ($logs->getRecords() as $record) {
            expect((string) $record->formatted.json_encode($record->context))
                ->not->toContain('signature')
                ->not->toContain('expires=');
        }
    });

    it('answers 404 for a receipt URL when the payment has no receipt', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);

        $this->actingAs($user)->getJson(route('v1.weddings.expenses.payments.receipt.show', [$wedding, $expense, $payment]))
            ->assertNotFound();
    });

    it('removes a receipt and its file', function () {
        [$user, $wedding, $expense] = usdExpenseOwner();
        $payment = ExpensePayment::factory()->for($expense)->create(['amount' => '100.00']);
        $this->actingAs($user)->post(route('v1.weddings.expenses.payments.receipt.store', [$wedding, $expense, $payment]), [
            'receipt' => UploadedFile::fake()->image('deposit.png'),
        ], ['Accept' => 'application/json']);
        $path = $payment->refresh()->receipt_path;

        $this->deleteJson(route('v1.weddings.expenses.payments.receipt.destroy', [$wedding, $expense, $payment]))
            ->assertOk()->assertJsonPath('data.receipt', null);

        Storage::disk('receipts')->assertMissing($path);
    });

    it('does not expose the local disk through a storage route', function () {
        $this->get('/storage/payments/1/receipt.png')->assertNotFound();
        $this->put('/storage/payments/1/receipt.png?upload=1')->assertNotFound();
    });
});
