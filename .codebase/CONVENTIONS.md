# Conventions

**Last updated:** 2026-07-06

## Code Style

- **Formatter:** Laravel Pint (PSR-12 based)
- **Run Pint:** `vendor/bin/pint --dirty --format agent`
- **PHP version:** 8.3+ (uses constructor property promotion, enums, match expressions)

## PHP Patterns

### Constructor Property Promotion
```php
// app/Services/BundlePaymentService.php
class BundlePaymentService
{
    public function __construct(
        private readonly MidtransService $midtrans,
    ) {}
}

// app/Http/Controllers/Api/TuitionInvoiceController.php
class TuitionInvoiceController extends Controller
{
    public function __construct(
        private readonly MidtransService $midtrans,
    ) {}
}
```

### Return Type Declarations
All methods have explicit return types:
```php
public function isTerminal(): bool
public function canTransitionTo(string $newStatus): bool
public function transitionTo(string $newStatus): static
```

### PHP 8.4 Attributes (User Model)
```php
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
```

### Match Expressions (State Machines)
```php
$allowed = match ($this->status) {
    'draft' => ['pending_payment', 'cancelled'],
    'pending_payment' => ['paid', 'expired', 'cancelled', 'draft'],
    'expired' => ['pending_payment'],
    'paid', 'cancelled' => [],
    default => [],
};
```

## Laravel Conventions

### Controllers
- All API controllers in `App\Http\Controllers\Api\`
- Resource controllers use `apiResource()` for CRUD
- Return type hints on all methods: `JsonResponse`, `AnonymousResourceCollection`, specific Resource
- Services injected via constructor property promotion

### Form Requests
- Separate request classes per action: `StoreStudentRequest`, `UpdateStudentRequest`, `LoginRequest`, etc.
- Validation rules defined in `rules()` method
- `authorize()` returns `true` (auth handled by middleware)

### Eloquent Resources
- Every API response wrapped in a Resource class
- Resources handle nested loading: `StudentResource::make($this->whenLoaded('student'))`
- Consistent JSON shape across endpoints

### State Machines (Manual Implementation)
- Models implement `canTransitionTo(string): bool` and `transitionTo(string): static`
- Terminal states: paid, expired, cancelled — never overwritten
- InvalidArgumentException thrown on invalid transitions

### Service Layer
- Business logic extracted to Service classes (not in controllers)
- Constructor injection via Laravel container
- `MidtransService` handles all external API calls
- `BundlePaymentService` orchestrates complex multi-invoice operations

## Testing Conventions

### Framework: Pest PHP v4
```php
// tests/Feature/AuthTest.php
it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [...]);
    $response->assertStatus(201)->assertJsonStructure([...]);
});
```

### Database Refresh
- Uses `LazilyRefreshDatabase` trait (not `RefreshDatabase`)
- Applied per-file: `uses(LazilyRefreshDatabase::class);`

### Authentication in Tests
```php
$user = User::factory()->create();
$token = $user->createToken('test')->plainTextToken;
$this->withToken($token)->getJson('/api/...');
```

### HTTP Mocking
```php
Http::fake(); // No real Midtrans calls in tests
Http::assertSent(function ($request) { ... });
```

### Assertions
- Pest `expect()` for model state: `expect($attempt->status)->toBe('cancelled')`
- Laravel `assertJsonStructure()`, `assertJsonFragment()`, `assertStatus()`
- Exception testing: `expect(fn () => ...)->toThrow(InvalidArgumentException::class)`

### Factory Usage
- Factories for all major models: User, Student, TuitionInvoice, PaymentAttempt
- Factory states: `TuitionInvoice::factory()->pendingPayment()->create([...])`
- Inline attribute overrides in tests

## Naming Conventions

| Type | Convention | Example |
|------|-----------|---------|
| Classes | PascalCase | `BundlePaymentService` |
| Methods | camelCase | `createPaymentLink()` |
| Variables | camelCase | `$grossAmount` |
| Config keys | snake_case | `midtrans.server_key` |
| Routes | kebab-case | `/payment-attempts/bundle` |
| DB columns | snake_case | `payment_attempt_id` |
| Enums | snake_case values | `pending_payment`, `annual_prepayment` |
| Migrations | snake_case + timestamp | `2026_06_25_104750_create_students_table.php` |
