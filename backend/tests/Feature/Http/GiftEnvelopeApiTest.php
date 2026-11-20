<?php

use App\Enums\Currency;
use App\Enums\GuestGroup;
use App\Models\GiftEnvelope;
use App\Models\User;
use App\Models\Wedding;

/**
 * @return array{0: User, 1: Wedding}
 */
function envelopeOwner(Currency $base = Currency::LAK): array
{
    $user = User::factory()->create();

    return [$user, Wedding::factory()->for($user)->create(['base_currency' => $base])];
}

it('records an envelope in the wedding base currency, ignoring a client-sent currency', function () {
    [$user, $wedding] = envelopeOwner(Currency::USD);

    $this->actingAs($user)->postJson(route('v1.weddings.gift-envelopes.store', $wedding), [
        'full_name' => 'ທ້າວ ບຸນມີ ແສງດາລາ', 'amount' => '50.00', 'guest_group' => 'groom_family',
        'received_date' => '2027-02-20', 'notes' => 'Table 4', 'currency' => 'THB',
    ])->assertCreated()
        ->assertJsonPath('data.full_name', 'ທ້າວ ບຸນມີ ແສງດາລາ')
        ->assertJsonPath('data.amount', ['amount' => '50.00', 'currency' => 'USD'])
        ->assertJsonPath('data.guest_group', 'groom_family')
        ->assertJsonPath('data.received_date', '2027-02-20')
        ->assertJsonPath('data.notes', 'Table 4');

    expect($wedding->giftEnvelopes()->sole()->amount)->toBe('50.00');
});

it('stores large LAK amounts exactly', function () {
    [$user, $wedding] = envelopeOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.gift-envelopes.store', $wedding), [
        'full_name' => 'Sponsor', 'amount' => '999999999999999999.99', 'guest_group' => 'other', 'received_date' => '2027-02-20',
    ])->assertCreated()->assertJsonPath('data.amount.amount', '999999999999999999.99');
});

it('validates the envelope', function (array $input, array $errors) {
    [$user, $wedding] = envelopeOwner();

    $this->actingAs($user)->postJson(route('v1.weddings.gift-envelopes.store', $wedding), [
        'full_name' => 'Noy', 'amount' => '100000.00', 'guest_group' => 'friends', 'received_date' => '2027-02-20', ...$input,
    ])->assertUnprocessable()->assertJsonValidationErrors($errors);

    expect($wedding->giftEnvelopes()->exists())->toBeFalse();
})->with([
    'missing name' => [['full_name' => ''], ['full_name']],
    'name too long' => [['full_name' => str_repeat('a', 151)], ['full_name']],
    'zero amount' => [['amount' => '0.00'], ['amount']],
    'float-style amount' => [['amount' => '1e6'], ['amount']],
    'unknown group' => [['guest_group' => 'enemies'], ['guest_group']],
    'missing date' => [['received_date' => null], ['received_date']],
    'bad date' => [['received_date' => '20/02/2027'], ['received_date']],
]);

it('searches, filters, sorts and paginates envelopes, with totals per group for the whole wedding', function () {
    [$user, $wedding] = envelopeOwner();
    $envelope = fn (string $name, string $amount, GuestGroup $group, string $date) => GiftEnvelope::factory()->for($wedding)
        ->create(['full_name' => $name, 'amount' => $amount, 'guest_group' => $group, 'received_date' => $date]);
    $envelope('Bounmy Sengdara', '300000.00', GuestGroup::Friends, '2027-02-20');
    $envelope('Mr Bounmy Keo', '500000.00', GuestGroup::Friends, '2027-02-20');
    $envelope('Bounmy Phan', '900000.00', GuestGroup::Colleagues, '2027-02-20');
    $envelope('Noy Inthavong', '200000.00', GuestGroup::Friends, '2027-01-10');
    $envelope('Bounmy Trashed', '700000.00', GuestGroup::Friends, '2027-02-20')->delete();
    GiftEnvelope::factory()->create(['full_name' => 'Bounmy Elsewhere']); // another wedding

    $response = $this->actingAs($user)->getJson(route('v1.weddings.gift-envelopes.index', [
        'wedding' => $wedding, 'search' => 'bounmy', 'guest_group' => 'friends',
        'date_from' => '2027-02-01', 'date_to' => '2027-02-28', 'sort' => '-amount', 'per_page' => 1,
    ]))->assertOk()
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('data.0.full_name', 'Mr Bounmy Keo')
        ->assertJsonPath('meta.summary.count', 4)
        ->assertJsonPath('meta.summary.total', ['amount' => '1900000.00', 'currency' => 'LAK']);

    expect(collect($response->json('meta.summary.groups'))->pluck('total.amount', 'guest_group')->all())->toBe([
        'groom_family' => '0.00', 'bride_family' => '0.00', 'relatives' => '0.00',
        'friends' => '1000000.00', 'colleagues' => '900000.00', 'neighbors' => '0.00', 'other' => '0.00',
    ]);
});

it('matches LIKE wildcards in the search literally', function () {
    [$user, $wedding] = envelopeOwner();
    GiftEnvelope::factory()->for($wedding)->create(['full_name' => 'Noy']);
    GiftEnvelope::factory()->for($wedding)->create(['full_name' => '100% Noy']);

    $this->actingAs($user)->getJson(route('v1.weddings.gift-envelopes.index', ['wedding' => $wedding, 'search' => '%']))
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.full_name', '100% Noy');
});

it('rejects unknown sort keys', function () {
    [$user, $wedding] = envelopeOwner();

    $this->actingAs($user)->getJson(route('v1.weddings.gift-envelopes.index', ['wedding' => $wedding, 'sort' => 'wedding_id']))
        ->assertUnprocessable()->assertJsonValidationErrors('sort');
});

it('updates and soft deletes an envelope', function () {
    [$user, $wedding] = envelopeOwner();
    $envelope = GiftEnvelope::factory()->for($wedding)->create(['amount' => '100000.00']);

    $this->actingAs($user)->patchJson(route('v1.weddings.gift-envelopes.update', [$wedding, $envelope]), [
        'amount' => '150000.00', 'guest_group' => 'neighbors',
    ])->assertOk()
        ->assertJsonPath('data.amount.amount', '150000.00')
        ->assertJsonPath('data.guest_group', 'neighbors');

    $this->deleteJson(route('v1.weddings.gift-envelopes.destroy', [$wedding, $envelope]))->assertNoContent();

    expect($envelope->refresh()->trashed())->toBeTrue();
});

it('shows the envelope total on the dashboard', function () {
    [$user, $wedding] = envelopeOwner();
    GiftEnvelope::factory()->for($wedding)->create(['amount' => '400000.00']);

    $this->actingAs($user)->getJson(route('v1.weddings.dashboard', $wedding))
        ->assertOk()
        ->assertJsonPath('data.summary.revenue.gift_envelopes', ['amount' => '400000.00', 'currency' => 'LAK'])
        ->assertJsonPath('data.summary.revenue.received', ['amount' => '400000.00', 'currency' => 'LAK']);
});
