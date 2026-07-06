# Testing

**Last updated:** 2026-07-06

## Framework

- **Pest PHP v4** (on top of PHPUnit 12)
- **Config:** `phpunit.xml` at project root
- **Base TestCase:** `tests/TestCase.php`
- **Pest config:** `tests/Pest.php`

## Test Database

- **Engine:** SQLite in-memory (`:memory:`)
- **Connection:** `DB_CONNECTION=sqlite` (set in phpunit.xml)
- **Refresh:** `LazilyRefreshDatabase` trait per test file

## Running Tests

```bash
# Run all tests
php artisan test --compact

# Run specific test file
php artisan test --compact --filter=AuthTest

# Run with Pest directly
./vendor/bin/pest
```

## Test Files & Counts

### Feature Tests (10 files)

| File | Tests | Coverage Area |
|------|-------|---------------|
| `AuthTest.php` | 9 | Register, login, logout, me, token validation |
| `TuitionInvoiceTest.php` | 2 | Pay terminal invoice, pay non-terminal |
| `TuitionInvoiceStatusTest.php` | 7 | Status transitions, terminal detection, exception handling |
| `PaymentAttemptCancellationTest.php` | 6 | Cancel created attempt, reject paid, deactivate Midtrans link, annual_prepayment deletion |
| `BillTest.php` | 4 | Bill HTML, bill PDF, reject paid, auth required |
| `ReceiptTest.php` | 5 | Receipt HTML, receipt PDF, share URL, reject non-paid, auth required |
| `ReceiptPublicAccessTest.php` | 3 | Signed URL access, expired URL, tampered URL |
| `ImportTest.php` | 4 | Template download, student import preview, reject non-xlsx |
| `ExportTest.php` | 5 | Students export, filtered export, invoices, payments, invalid type |
| `ExampleTest.php` | 1 | Application returns 200 |

**Feature test total: ~46 tests**

### Unit Tests (2 files)

| File | Tests | Coverage Area |
|------|-------|---------------|
| `MidtransSignatureTest.php` | 4 | SHA-512 signature verification, invalid signature, wrong key, unique order ID |
| `ExampleTest.php` | 1 | Boolean assertion |

**Unit test total: ~5 tests**

**Grand total: ~51 tests**

## Testing Patterns

### 1. Pest Syntax
```php
it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [...]);
    $response->assertStatus(201);
});

test('the application returns a successful response', function () {
    $response = $this->get('/');
    $response->assertStatus(200);
});
```

### 2. Before Each Setup
```php
beforeEach(function () {
    $this->student = Student::factory()->create(['monthly_fee' => 200000]);
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
    Config::set('midtrans.server_key', 'SB-Mid-server-test-key');
    Config::set('midtrans.payment_link_url', 'https://api.sandbox.midtrans.com/v1/payment-links');
    Http::fake();
});
```

### 3. Authenticated Requests
```php
$this->withToken($this->token)
    ->getJson('/api/students')
    ->assertOk();
```

### 4. HTTP Mocking (No Real Midtrans)
```php
Http::fake();

// Verify specific request was sent
Http::assertSent(function ($request) {
    return $request->url() === 'https://api.sandbox.midtrans.com/v1/payment-links/PL-12345'
        && $request->method() === 'DELETE';
});
```

### 5. Model State Assertions
```php
$attempt->refresh();
expect($attempt->status)->toBe('cancelled');
expect($attempt->payment_url)->toBeNull();
```

### 6. Exception Testing
```php
expect(fn () => $invoice->transitionTo('draft'))
    ->toThrow(InvalidArgumentException::class);
```

### 7. Factory States
```php
$invoice = TuitionInvoice::factory()->pendingPayment()->create([
    'student_id' => $this->student->id,
]);
```

## Factory Coverage

| Factory | Model | Custom States |
|---------|-------|---------------|
| `UserFactory` | User | None (default Laravel) |
| `StudentFactory` | Student | None (default fields) |
| `TuitionInvoiceFactory` | TuitionInvoice | `pendingPayment()` |
| `PaymentAttemptFactory` | PaymentAttempt | Default fields |

## Test Setup (tests/Pest.php)

- Extends `TestCase` for Feature tests
- `RefreshDatabase` trait commented out (each file uses `LazilyRefreshDatabase` instead)
- Custom expectation: `toBeOne()` (asserts value is 1)

## Coverage Gaps

1. **AnnualPrepaymentController** - No dedicated test for annual prepayment flow
2. **DocumentService** - No unit test for PDF/HTML generation
3. **ImportController confirm** - Preview tested, but actual import confirmation not tested
4. **MidtransWebhookController** - No webhook processing test
5. **StudentController** - CRUD operations not fully tested (only through auth)
6. **ExportController** - Export content not validated (only headers)
7. **CORS middleware** - No test (no-op implementation)
8. **GenerateMonthlyInvoices** - No artisan command test
