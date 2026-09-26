<?php

use App\Enums\CompanyRole;
use App\Models\Community;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Laravel\Fortify\Fortify;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use PragmaRX\Google2FA\Google2FA;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

function signIn(array $overrides = []): TestResponse
{
    return postJson(route('api.v1.auth.token.store'), [
        'email' => 'rita@example.test', 'password' => 'password', 'device_name' => 'Rita\'s phone', ...$overrides,
    ]);
}

function ritaWithTwoFactor(): array
{
    $secret = app(Google2FA::class)->generateSecretKey();
    $user = User::factory()->create(['email' => 'rita@example.test']);
    $user->forceFill([
        'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
        'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(json_encode(['recovery-one', 'recovery-two'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    return [$user, $secret];
}

describe('signing in', function () {
    it('issues a token for the device, which then authenticates requests', function () {
        User::factory()->create(['email' => 'rita@example.test', 'name' => 'Rita Resident']);

        $token = signIn()->assertCreated()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.name', 'Rita Resident')
            ->json('token');

        expect(PersonalAccessToken::sole()->name)->toBe('Rita\'s phone');

        getJson(route('api.v1.me'), ['Authorization' => "Bearer {$token}"])->assertOk()->assertJsonPath('data.email', 'rita@example.test');
    });

    it('gives the same answer for an unknown email and a wrong password', function (string $email, string $password) {
        User::factory()->create(['email' => 'rita@example.test']);

        signIn(['email' => $email, 'password' => $password])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('errors.email.0', __('auth.failed'));

        expect(PersonalAccessToken::count())->toBe(0);
    })->with([
        'unknown email' => ['nobody@example.test', 'password'],
        'wrong password' => ['rita@example.test', 'wrong'],
    ]);

    it('refuses a deactivated account', function () {
        User::factory()->create(['email' => 'rita@example.test', 'deactivated_at' => now()]);

        signIn()->assertUnprocessable()->assertJsonValidationErrors('email');
    });

    it('validates the request', function () {
        postJson(route('api.v1.auth.token.store'), [])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['email', 'password', 'device_name']);
    });

    it('slows down repeated attempts', function () {
        User::factory()->create(['email' => 'rita@example.test']);

        foreach (range(1, 5) as $attempt) {
            signIn(['password' => 'wrong'])->assertUnprocessable();
        }

        signIn()->assertTooManyRequests()->assertJsonPath('code', 'too_many_requests')->assertHeader('Retry-After');
    });
});

describe('two-factor accounts', function () {
    it('asks for the code, then accepts a valid one', function () {
        [, $secret] = ritaWithTwoFactor();

        signIn()->assertUnprocessable()->assertJsonPath('code', 'two_factor_required');
        signIn(['code' => '000000'])->assertUnprocessable()->assertJsonPath('code', 'validation_failed');
        expect(PersonalAccessToken::count())->toBe(0);

        signIn(['code' => app(Google2FA::class)->getCurrentOtp($secret)])->assertCreated();
    });

    it('accepts a recovery code once', function () {
        [$user] = ritaWithTwoFactor();

        signIn(['recovery_code' => 'recovery-one'])->assertCreated();
        signIn(['recovery_code' => 'recovery-one'])->assertUnprocessable();

        expect($user->fresh()?->recoveryCodes())->not->toContain('recovery-one')->toContain('recovery-two');
    });
});

describe('tokens', function () {
    it('lists the account\'s devices, marking the current one', function () {
        $user = User::factory()->create();
        $user->createToken('Old tablet');
        $current = $user->createToken('Phone');
        User::factory()->create()->createToken('Someone else');

        getJson(route('api.v1.auth.tokens.index'), ['Authorization' => "Bearer {$current->plainTextToken}"])
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['device_name' => 'Phone', 'current' => true])
            ->assertJsonFragment(['device_name' => 'Old tablet', 'current' => false]);
    });

    it('revokes the current token, or another of the account\'s own', function () {
        $user = User::factory()->create();
        $tablet = $user->createToken('Tablet');
        $phone = $user->createToken('Phone');
        $headers = ['Authorization' => "Bearer {$phone->plainTextToken}"];

        deleteJson(route('api.v1.auth.tokens.destroy', $tablet->accessToken->id), [], $headers)->assertNoContent();
        expect(PersonalAccessToken::whereKey($tablet->accessToken->id)->exists())->toBeFalse();

        deleteJson(route('api.v1.auth.tokens.destroy', 'current'), [], $headers)->assertNoContent();
        expect(PersonalAccessToken::count())->toBe(0);

        app('auth')->forgetGuards();
        getJson(route('api.v1.me'), $headers)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });

    it('cannot revoke another person\'s token', function () {
        $other = User::factory()->create()->createToken('Theirs');
        Sanctum::actingAs(User::factory()->create());

        deleteJson(route('api.v1.auth.tokens.destroy', $other->accessToken->id))->assertNotFound()->assertJsonPath('code', 'not_found');
        expect(PersonalAccessToken::count())->toBe(1);
    });

    it('turns away a deactivated account and revokes the token it used', function () {
        $user = User::factory()->create();
        $token = $user->createToken('Phone');
        $user->forceFill(['deactivated_at' => now()])->save();

        getJson(route('api.v1.me'), ['Authorization' => "Bearer {$token->plainTextToken}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'account_deactivated');

        expect(PersonalAccessToken::count())->toBe(0);
    });
});

describe('me', function () {
    it('describes a team member: role, permissions and communities', function () {
        $community = Community::factory()->create(['name' => 'Harbour Towers']);
        Community::factory()->for($community->company)->create(['name' => 'Not mine']);
        Sanctum::actingAs(teamMember(CompanyRole::Staff, $community->company, [$community]));

        getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.role', CompanyRole::Staff->value)
            ->assertJsonPath('data.communities', [['id' => $community->id, 'name' => 'Harbour Towers']])
            ->assertJsonPath('data.homes', [])
            ->assertJsonPath('data.permissions', fn (array $permissions) => in_array('service-requests.view', $permissions, true) && ! in_array('finance.view', $permissions, true));
    });

    it('describes a resident: their homes and no team access', function () {
        $community = Community::factory()->create();
        $resident = residentOf($community, ['type' => 'owner']);
        Sanctum::actingAs($resident->user);

        getJson(route('api.v1.me'))
            ->assertOk()
            ->assertJsonPath('data.communities', [])
            ->assertJsonPath('data.homes.0.community.id', $community->id)
            ->assertJsonPath('data.homes.0.type', 'owner');
    });

    it('requires a token', function () {
        getJson(route('api.v1.me'))->assertUnauthorized()->assertExactJson(['message' => 'Unauthenticated.', 'code' => 'unauthenticated']);
    });
});

it('answers an unknown API route with the standard error shape', function () {
    getJson('/api/v1/nothing-here')->assertNotFound()->assertExactJson(['message' => 'Not found.', 'code' => 'not_found']);
});
